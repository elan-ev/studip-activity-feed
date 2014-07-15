<?php
/*
 * ActivityFeed.class.php - activity feed plugin for Stud.IP
 * Copyright (c) 2010  Elmar Ludwig
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

class ActivityFeedBase extends StudipPlugin implements HomepagePlugin, StandardPlugin, SystemPlugin
{
    /**
     * plugin template factory
     */
    protected $template_factory;

    /**
     * Stud.IP API version
     */
    public $api_version;

    /**
     * Initialize a new instance of the plugin.
     */
    public function __construct()
    {
        parent::__construct();

        $template_path = $this->getPluginPath() . '/templates';
        $this->template_factory = new Flexi_TemplateFactory($template_path);
        $this->api_version = class_exists('PageLayout') ? '2.0' : '1.11';

        $page = basename($_SERVER['PHP_SELF']);

        if ($page === 'meine_seminare.php') {
            $this->add_feed_indicator();
        } else if (in_array($page, words('seminar_main.php institut_main.php'))) {
            $this->add_feed_indicator($_SESSION['SessionSeminar']);
            $GLOBALS['_include_additional_header'] .=
                Assets::stylesheet($this->getPluginURL() . '/css/activities.css');
        }

        if (Navigation::hasItem('/browse/my_courses')) {
            $navigation = new Navigation(_('Neueste Aktivitäten'));
            $navigation->setURL(PluginEngine::getURL('activityfeed/activities'));
            Navigation::insertItem('/browse/my_courses/activities', $navigation, 'archive');
        }
    }

    public function getTabNavigation($course_id)
    {
        return array();
    }

    /**
     * Add feed indicator for HTML head element.
     */
    private function add_feed_indicator($range = NULL)
    {
        global $user;

        $user_id = $user->id;
        $key = $this->get_user_key($user_id);

        if ($key) {
            $link_template = $this->template_factory->open('atom_link');
            $link_template->action = "activityfeed/atom/$user_id/$key";
            $link_template->range = $range;

            $GLOBALS['_include_additional_header'] .= $link_template->render();
        }
    }

    /**
     * Set a UserConfig value.
     */
     private function user_config_get($user_id, $key)
     {
        $user_config = new UserConfig($user_id);

        if ($this->api_version === '2.0') {
            return $user_config->getValue($key);
        } else {
            return $user_config->getValue(NULL, $key);
        }
     }

    /**
     * Get a UserConfig value.
     */
     private function user_config_set($user_id, $key, $value)
     {
        $user_config = new UserConfig($user_id);

        if ($this->api_version === '2.0') {
            $user_config->store($key, $value);
        } else {
            $user_config->setValue($value, NULL, $key);
        }
     }

    /**
     * Remove a UserConfig setting.
     */
     private function user_config_delete($user_id, $key)
     {
        $user_config = new UserConfig($user_id);

        if ($this->api_version === '2.0') {
            $user_config->delete($key);
        } else {
            $user_config->unsetValue(NULL, $key);
        }
     }

    /**
     * Return the user specific access key.
     */
    private function get_user_key($user_id)
    {
        if (!get_config('ACTIVITY_FEED_ENABLED')) {
            return NULL;
        }

        return $this->user_config_get($user_id, 'ACTIVITY_FEED_KEY');
    }

    /**
     * Calculate user specific access key.
     */
    private function set_user_key($user_id)
    {
        $key = '';

        for ($i = 0; $i < 32; ++$i) {
            $key .= chr(mt_rand(0, 63) + 48);
        }

        $key = sha1($key);
        $this->user_config_set($user_id, 'ACTIVITY_FEED_KEY', $key);
    }

    /**
     * Clear the user specific access key.
     */
    private function clear_user_key($user_id)
    {
        $this->user_config_delete($user_id, 'ACTIVITY_FEED_KEY');
    }

    /**
     * Filter activities list by category.
     */
    private function filter_category($items, $category)
    {
        $result = array();

        if ($category === NULL || $category === '') {
            return $items;
        }

        foreach ($items as $item) {
            if ($item['category'] === $category) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Delete all characters outside the valid character range for XML
     * documents (#x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD]).
     */
    private function filter_xml_string($xml)
    {
        return preg_replace("/[^\t\n\r -\xFF]/", '', $xml);
    }

    /**
     * Display the activities view for this user.
     */
    public function activities_action()
    {
        global $user, $perm;

        $days = Request::int('days', 14);
        $category = Request::option('category');
        $enable = Request::int('enable');
        $perm->check('autor');

        $GLOBALS['CURRENT_PAGE'] = _('Neueste Aktivitäten');
        $GLOBALS['_include_additional_header'] .=
            Assets::stylesheet($this->getPluginURL() . '/css/activities.css');
        Navigation::activateItem('/browse/my_courses/activities');

        if ($enable === 1) {
            $this->set_user_key($user->id);
        } else if ($enable === 0) {
            $this->clear_user_key($user->id);
        }

        $template = $this->template_factory->open('activities');
        $layout = $GLOBALS['template_factory']->open('layouts/base');

        $template->items = $this->get_activities($user->id, NULL, $days);
        $template->items = $this->filter_category($template->items, $category);
        $template->days = $days;
        $template->category = $category;
        $template->categories = array(
            'forum'   => _('Forum'),
            'files'   => _('Dateibereich'),
            'wiki'    => _('Wiki'),
            'info'    => _('Information'),
            'news'    => _('Ankündigung'),
            'votings' => _('Umfrage'),
            'surveys' => _('Evaluation'),
        );
        $template->user = $user->id;
        $template->plugin = $this;
        $template->key = $this->get_user_key($user->id);
        $template->feed_enabled = get_config('ACTIVITY_FEED_ENABLED');
        $template->enable = $enable;
        $template->set_layout($layout);
        $this->add_feed_indicator();

        echo $template->render();
    }

    /**
     * Display the atom activity feed for this user.
     */
    public function atom_action($user, $key)
    {
        $user_id = preg_replace('/\W/', '', $user);
        $user_key = $this->get_user_key($user_id);
        $range = Request::option('range');
        $days = Request::int('days', 14);
        $category = Request::option('category');
        $days = min($days, 28);

        if ($user_key === NULL || $user_key !== $key) {
            if ($this->api_version === '2.0') {
                throw new AccessDeniedException('invalid access key');
            } else {
                throw new Studip_AccessDeniedException('invalid access key');
            }
        }

        header('Content-Type: application/atom+xml');
        $template = $this->template_factory->open('atom');

        $template->base_url = $GLOBALS['ABSOLUTE_URI_STUDIP'];
        $template->author_name = $GLOBALS['UNI_NAME_CLEAN'];
        $template->author_email = $GLOBALS['UNI_CONTACT'];

        if (isset($range)) {
            $template->title = $_SESSION['SessSemName'][0];
        } else {
            $template->title = $GLOBALS['UNI_NAME_CLEAN'];
        }

        $template->items = $this->get_activities($user_id, $range, $days);
        $template->items = $this->filter_category($template->items, $category);

        if (count($template->items)) {
            $template->updated = $template->items[0]['updated'];
        } else {
            $template->updated = time();
        }

        echo $this->filter_xml_string($template->render());
    }

    /**
     * Display the activity stream of this user.
     */
    public function getHomepageTemplate($user_id)
    {
        global $user;

        $days = Request::int('days', 14);
        $as_public = Request::int('as_public');
        $my_profile = $user->id == $user_id;

        if ($my_profile) {
            if ($as_public === 1) {
                $this->user_config_set($user_id, 'ACTIVITY_STREAM_PUBLIC', 1);
            } else if ($as_public === 0) {
                $this->user_config_delete($user_id, 'ACTIVITY_STREAM_PUBLIC');
            }
        } else if (!$this->user_config_get($user_id, 'ACTIVITY_STREAM_PUBLIC')) {
            return NULL;
        }

        $GLOBALS['_include_additional_header'] .=
            Assets::stylesheet($this->getPluginURL() . '/css/activities.css');

        $template = $this->template_factory->open('user_activities');

        $template->title = _('Neueste Aktivitäten');
        $template->icon_url = $this->api_version === '2.0' ? 'icons/16/white/community.png' : 'nutzer.gif';
        $template->items = $this->get_activities($user_id, 'user', $days);
        $template->items = array_slice($template->items, 0, 5);
        $template->user = $user->id;
        $template->plugin = $this;
        $template->my_profile = $my_profile;
        $template->public = $this->user_config_get($user_id, 'ACTIVITY_STREAM_PUBLIC');

        if ($template->public) {
            $username = get_username($user_id);
            $link_template = $this->template_factory->open('atom_link');
            $link_template->action = "activityfeed/atom_user/$username";

            $GLOBALS['_include_additional_header'] .= $link_template->render();

            if ($my_profile) {
                $template->title .= ' ' . _('(öffentlich)');
            }
        }

        return count($template->items) ? $template : NULL;
    }

    /**
     * Display the atom activity stream of this user.
     */
    public function atom_user_action($user)
    {
        $username = preg_replace('/[^\w@.-]/', '', $user);
        $user_id = get_userid($username);
        $days = Request::int('days', 14);
        $category = Request::option('category');
        $days = min($days, 28);

        if (!$user_id || !$this->user_config_get($user_id, 'ACTIVITY_STREAM_PUBLIC')) {
            if ($this->api_version === '2.0') {
                throw new AccessDeniedException('access denied');
            } else {
                throw new Studip_AccessDeniedException('access denied');
            }
        }

        header('Content-Type: application/atom+xml');
        $template = $this->template_factory->open('atom_user');

        $template->base_url = $GLOBALS['ABSOLUTE_URI_STUDIP'];
        $template->author_name = $GLOBALS['UNI_NAME_CLEAN'];
        $template->author_email = $GLOBALS['UNI_CONTACT'];
        $template->title = get_fullname($user_id);
        $template->items = $this->get_activities($user_id, 'user', $days);
        $template->items = $this->filter_category($template->items, $category);

        if (count($template->items)) {
            $template->updated = $template->items[0]['updated'];
        } else {
            $template->updated = time();
        }

        echo $this->filter_xml_string($template->render());
    }

    /**
     * Return a navigation object representing this plugin in the
     * course overview table or return NULL if you want to display
     * no icon for this plugin (or course).
     */
    function getIconNavigation($course_id, $last_visit, $user_id)
    {
        return NULL;
    }

    function getNotificationObjects($course_id, $since, $user_id)
    {
        return array();
    }

    /**
     * Return a template (an instance of the Flexi_Template class)
     * to be rendered on the course summary page. Return NULL to
     * render nothing for this plugin.
     */
    function getInfoTemplate($course_id)
    {
        global $user;

        $days = Request::int('days', 14);

        $template = $this->template_factory->open('user_activities');

        $template->title = _('Neueste Aktivitäten');
        $template->icon_url = $this->api_version === '2.0' ? 'icons/16/white/community.png' : 'nutzer.gif';
        $template->admin_url = PluginEngine::getURL('activityfeed/activities');
        $template->admin_title = _('Einstellungen');
        $template->items = $this->get_activities($user->id, $course_id, $days);
        $template->items = array_slice($template->items, 0, 5);
        $template->user = $user->id;
        $template->plugin = $this;

        return count($template->items) ? $template : NULL;
    }

    /**
     * Get all activities for this user as an array.
     */
    public function get_activities($user_id, $range, $days)
    {
        $db = DBManager::get();
        $now = time();
        $chdate = $now - 24 * 60 * 60 * $days;
        $items = array();
        $limit = " LIMIT 100";

        if ($range === 'user') {
            $sem_filter = "seminar_user.user_id = '$user_id' AND auth_user_md5.user_id = '$user_id'";
            $inst_filter = "user_inst.user_id = '$user_id' AND auth_user_md5.user_id = '$user_id'";
        } else if (isset($range)) {
            $sem_filter = "seminar_user.user_id = '$user_id' AND Seminar_id = '$range'";
            $inst_filter = "user_inst.user_id = '$user_id' AND Institut_id = '$range'";
        } else {
            $sem_filter = "seminar_user.user_id = '$user_id'";
            $inst_filter = "user_inst.user_id = '$user_id'";
        }

        $sem_fields = 'auth_user_md5.user_id AS author_id, auth_user_md5.Vorname, auth_user_md5.Nachname, seminare.Name, auth_user_md5.username';
        $inst_fields = 'auth_user_md5.user_id AS author_id, auth_user_md5.Vorname, auth_user_md5.Nachname, Institute.Name, auth_user_md5.username';
        $user_fields = 'auth_user_md5.user_id AS author_id, auth_user_md5.Vorname, auth_user_md5.Nachname, auth_user_md5.username';

        // news

        if ($range === 'user') {
            $sql = "SELECT news.*, news_range.range_id, $user_fields
                    FROM news
                    JOIN news_range USING (news_id)
                    JOIN auth_user_md5 USING (user_id)
                    WHERE range_id = '$user_id' AND news.date BETWEEN $chdate AND $now $limit";

            $result = $db->query($sql);

            foreach ($result as $row) {
                $items[] = array(
                    'id' => $row['news_id'],
                    'title' => 'Ankündigung: ' . $row['topic'],
                    'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                    'author_id' => $row['author_id'],
                    'link' => URLHelper::getLink('about.php#anker',
                        array('username' => $row['username'], 'nopen' => $row['news_id'])),
                    'updated' => max($row['date'], $row['chdate']),
                    'summary' => sprintf('%s %s hat die persönliche Ankündigung "%s" eingestellt.',
                        $row['Vorname'], $row['Nachname'], $row['topic']),
                    'content' => $row['body'],
                    'username' => $row['username'],
                    'item_name' => $row['topic'],
                    'category' => 'news'
                );
            }
        }

        $sql = "SELECT news.*, news_range.range_id, $sem_fields
                FROM news
                JOIN news_range USING (news_id)
                JOIN auth_user_md5 USING (user_id)
                JOIN seminar_user ON (range_id = Seminar_id)
                JOIN seminare USING (Seminar_id)
                WHERE $sem_filter AND news.date BETWEEN $chdate AND $now $limit";

        $result = $db->query($sql);

        foreach ($result as $row) {
            $items[] = array(
                'id' => $row['news_id'],
                'title' => 'Ankündigung: ' . $row['topic'],
                'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                'author_id' => $row['author_id'],
                'link' => URLHelper::getLink('seminar_main.php#anker',
                    array('cid' => $row['range_id'], 'nopen' => $row['news_id'])),
                'updated' => max($row['date'], $row['chdate']),
                'summary' => sprintf('%s %s hat in der Veranstaltung "%s" die Ankündigung "%s" eingestellt.',
                    $row['Vorname'], $row['Nachname'], $row['Name'], $row['topic']),
                'content' => $row['body'],
                'username' => $row['username'],
                'item_name' => $row['topic'],
                'range_name' => $row['Name'],
                'category' => 'news'
            );
        }

        $sql = "SELECT news.*, news_range.range_id, $inst_fields
                FROM news
                JOIN news_range USING (news_id)
                JOIN auth_user_md5 USING (user_id)
                JOIN user_inst ON (range_id = Institut_id)
                JOIN Institute USING (Institut_id)
                WHERE $inst_filter AND news.date BETWEEN $chdate AND $now $limit";

        $result = $db->query($sql);

        foreach ($result as $row) {
            $items[] = array(
                'id' => $row['news_id'],
                'title' => 'Ankündigung: ' . $row['topic'],
                'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                'author_id' => $row['author_id'],
                'link' => URLHelper::getLink('institut_main.php#anker',
                    array('cid' => $row['range_id'], 'nopen' => $row['news_id'])),
                'updated' => max($row['date'], $row['chdate']),
                'summary' => sprintf('%s %s hat in der Einrichtung "%s" die Ankündigung "%s" eingestellt.',
                    $row['Vorname'], $row['Nachname'], $row['Name'], $row['topic']),
                'content' => $row['body'],
                'username' => $row['username'],
                'item_name' => $row['topic'],
                'range_name' => $row['Name'],
                'category' => 'news'
            );
        }

        // votings

        if ($range === 'user') {
            $sql = "SELECT vote.*, $user_fields
                    FROM vote
                    JOIN auth_user_md5 ON (author_id = user_id)
                    WHERE range_id = '$user_id' AND vote.startdate BETWEEN $chdate AND $now $limit";

            $result = $db->query($sql);

            foreach ($result as $row) {
                $items[] = array(
                    'id' => $row['vote_id'],
                    'title' => 'Umfrage: ' . $row['title'],
                    'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                    'author_id' => $row['author_id'],
                    'link' => URLHelper::getLink('about.php#openvote',
                        array('username' => $row['username'], 'voteopenID' => $row['vote_id'])),
                    'updated' => max($row['startdate'], $row['chdate']),
                    'summary' => sprintf('%s %s hat die persönliche Umfrage "%s" gestartet.',
                        $row['Vorname'], $row['Nachname'], $row['title']),
                    'content' => $row['question'],
                    'username' => $row['username'],
                    'item_name' => $row['title'],
                    'category' => 'votings'
                );
            }
        }

        $sql = "SELECT vote.*, $sem_fields
                FROM vote
                JOIN auth_user_md5 ON (author_id = user_id)
                JOIN seminar_user ON (range_id = Seminar_id)
                JOIN seminare USING (Seminar_id)
                WHERE $sem_filter AND vote.startdate BETWEEN $chdate AND $now $limit";

        $result = $db->query($sql);

        foreach ($result as $row) {
            $items[] = array(
                'id' => $row['vote_id'],
                'title' => 'Umfrage: ' . $row['title'],
                'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                'author_id' => $row['author_id'],
                'link' => URLHelper::getLink('seminar_main.php#openvote',
                    array('cid' => $row['range_id'], 'voteopenID' => $row['vote_id'])),
                'updated' => max($row['startdate'], $row['chdate']),
                'summary' => sprintf('%s %s hat in der Veranstaltung "%s" die Umfrage "%s" gestartet.',
                    $row['Vorname'], $row['Nachname'], $row['Name'], $row['title']),
                'content' => $row['question'],
                'username' => $row['username'],
                'item_name' => $row['title'],
                'range_name' => $row['Name'],
                'category' => 'votings'
            );
        }

        $sql = "SELECT vote.*, $inst_fields
                FROM vote
                JOIN auth_user_md5 ON (author_id = user_id)
                JOIN user_inst ON (range_id = Institut_id)
                JOIN Institute USING (Institut_id)
                WHERE $inst_filter AND vote.startdate BETWEEN $chdate AND $now $limit";

        $result = $db->query($sql);

        foreach ($result as $row) {
            $items[] = array(
                'id' => $row['vote_id'],
                'title' => 'Umfrage: ' . $row['title'],
                'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                'author_id' => $row['author_id'],
                'link' => URLHelper::getLink('institut_main.php#openvote',
                    array('cid' => $row['range_id'], 'voteopenID' => $row['vote_id'])),
                'updated' => max($row['startdate'], $row['chdate']),
                'summary' => sprintf('%s %s hat in der Einrichtung "%s" die Umfrage "%s" gestartet.',
                    $row['Vorname'], $row['Nachname'], $row['Name'], $row['title']),
                'content' => $row['question'],
                'username' => $row['username'],
                'item_name' => $row['title'],
                'range_name' => $row['Name'],
                'category' => 'votings'
            );
        }

        // surveys

        if ($range === 'user') {
            $sql = "SELECT eval.*, $user_fields
                    FROM eval
                    JOIN eval_range USING (eval_id)
                    JOIN auth_user_md5 ON (author_id = user_id)
                    WHERE range_id = '$user_id' AND eval.startdate BETWEEN $chdate AND $now $limit";

            $result = $db->query($sql);

            foreach ($result as $row) {
                $items[] = array(
                    'id' => $row['eval_id'],
                    'title' => 'Evaluation: ' . $row['title'],
                    'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                    'author_id' => $row['author_id'],
                    'link' => URLHelper::getLink('about.php#openvote',
                        array('username' => $row['username'], 'voteopenID' => $row['eval_id'])),
                    'updated' => max($row['startdate'], $row['chdate']),
                    'summary' => sprintf('%s %s hat die persönliche Evaluation "%s" gestartet.',
                        $row['Vorname'], $row['Nachname'], $row['title']),
                    'content' => $row['text'],
                    'username' => $row['username'],
                'item_name' => $row['title'],
                    'category' => 'surveys'
                );
            }
        }

        $sql = "SELECT eval.*, $sem_fields
                FROM eval
                JOIN eval_range USING (eval_id)
                JOIN auth_user_md5 ON (author_id = user_id)
                JOIN seminar_user ON (range_id = Seminar_id)
                JOIN seminare USING (Seminar_id)
                WHERE $sem_filter AND eval.startdate BETWEEN $chdate AND $now $limit";

        $result = $db->query($sql);

        foreach ($result as $row) {
            $items[] = array(
                'id' => $row['eval_id'],
                'title' => 'Evaluation: ' . $row['title'],
                'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                'author_id' => $row['author_id'],
                'link' => URLHelper::getLink('seminar_main.php#openvote',
                    array('cid' => $row['range_id'], 'voteopenID' => $row['eval_id'])),
                'updated' => max($row['startdate'], $row['chdate']),
                'summary' => sprintf('%s %s hat in der Veranstaltung "%s" die Evaluation "%s" gestartet.',
                    $row['Vorname'], $row['Nachname'], $row['Name'], $row['title']),
                'content' => $row['text'],
                'username' => $row['username'],
                'item_name' => $row['title'],
                'range_name' => $row['Name'],
                'category' => 'surveys'
            );
        }

        $sql = "SELECT eval.*, $inst_fields
                FROM eval
                JOIN eval_range USING (eval_id)
                JOIN auth_user_md5 ON (author_id = user_id)
                JOIN user_inst ON (range_id = Institut_id)
                JOIN Institute USING (Institut_id)
                WHERE $inst_filter AND eval.startdate BETWEEN $chdate AND $now $limit";

        $result = $db->query($sql);

        foreach ($result as $row) {
            $items[] = array(
                'id' => $row['eval_id'],
                'title' => 'Evaluation: ' . $row['title'],
                'author' => $row['Vorname'] . ' ' . $row['Nachname'],
                'author_id' => $row['author_id'],
                'link' => URLHelper::getLink('institut_main.php#openvote',
                    array('cid' => $row['range_id'], 'voteopenID' => $row['eval_id'])),
                'updated' => max($row['startdate'], $row['chdate']),
                'summary' => sprintf('%s %s hat in der Einrichtung "%s" die Evaluation "%s" gestartet.',
                    $row['Vorname'], $row['Nachname'], $row['Name'], $row['title']),
                'content' => $row['text'],
                'username' => $row['username'],
                'item_name' => $row['title'],
                'range_name' => $row['Name'],
                'category' => 'surveys'
            );
        }


        // activity providing plugins
        if ($this->api_version === '2.0') {
            $plugin_items = PluginEngine::sendMessage('ActivityProvider',
                                                      'getActivities',
                                                      $user_id, $range, $days);
            foreach ($plugin_items as $array) {
                $items = array_merge($items, $array);
            }
        }

        // get content-elements from all modules and plugins
        $result = DBManager::get()->query("SELECT seminare.* FROM seminar_user
            LEFT JOIN auth_user_md5 USING (user_id)
            LEFT JOIN seminare USING (Seminar_id)
            WHERE " . $sem_filter);

        # 'forum participants documents news scm schedule wiki vote literature elearning_interface'
        $module_slots = words('forum documents scm wiki');

        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $seminar) {
            $sem_class = $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][$seminar['status']]["class"]];
            foreach ($module_slots as $slot) {
                if ($module = $sem_class->getModule($slot)) {
                    $notifications = $module->getNotificationObjects($seminar['Seminar_id'], $chdate, $user_id);
                    if ($notifications) foreach ($notifications as $ce) {
                        // show only one users activites if activity stream on profile is used
                        if ($range === 'user' && $ce->getCreatorId() != $user_id) {
                            continue;
                        }

                        $items[] = array(
                            'title'     => $ce->getTitle(),
                            'author'    => $ce->getCreator(),
                            'author_id' => $ce->getCreatorId(),
                            'link'      => $ce->getUrl(),
                            'updated'   => $ce->getDate(),
                            'summary'   => $ce->getSummary(),
                            'content'   => $ce->getContent(),
                            'category'  => $slot
                        );
                    }
                }
            }
        }

        $result = DBManager::get()->query("SELECT Institute.*
            FROM user_inst
            LEFT JOIN auth_user_md5 USING (user_id)
            LEFT JOIN Institute USING (Institut_id)
            WHERE " . $inst_filter);        

        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $institute) {
            foreach ($module_slots as $slot) {
                $class = 'Core' . $slot;
                if ($module = new $class) {
                    $notifications = $module->getNotificationObjects($institute['Institut_id'], $chdate, $user_id);
                    if ($notifications) foreach ($notifications as $ce) {
                        // show only one users activites if activity stream on profile is used
                        if ($range === 'user' && $ce->getCreatorId() != $user_id) {
                            continue;
                        }

                        $items[] = array(
                            'title'     => $ce->getTitle(),
                            'author'    => $ce->getCreator(),
                            'author_id' => $ce->getCreatorId(),
                            'link'      => $ce->getUrl(),
                            'updated'   => $ce->getDate(),
                            'summary'   => $ce->getSummary(),
                            'content'   => $ce->getContent(),
                            'category'  => $slot
                        );
                    }
                }
            }        
        }

        // sort everything

        usort($items, create_function('$a, $b', 'return $b["updated"] - $a["updated"];'));
        $items = array_slice($items, 0, 100);

        return $items;
    }

    /**
     * Display a readable time format for past activities.
     */
    public function readableTime($from_time, $to_time = null, $include_seconds = false)
    {
        $to_time = $to_time ? $to_time: time();

        $distance_in_minutes = floor(abs($to_time - $from_time) / 60);
        $distance_in_seconds = floor(abs($to_time - $from_time));

        $string = '';
        $parameters = array();

        if ($distance_in_minutes <= 1) {
            if (!$include_seconds) {
                $string = $distance_in_minutes == 0 ? _('weniger als einer Minute') : _('1 Minute');
            } else {
                if ($distance_in_seconds <= 5) {
                    $string = _('weniger als 5 Sekunden');
                } else if ($distance_in_seconds >= 6 && $distance_in_seconds <= 10) {
                    $string = _('weniger als 10 Sekunden');
                } else if ($distance_in_seconds >= 11 && $distance_in_seconds <= 20) {
                    $string = _('weniger als 20 Sekunden');
                } else if ($distance_in_seconds >= 21 && $distance_in_seconds <= 40) {
                    $string = _('einer halben Minute');
                } else if ($distance_in_seconds >= 41 && $distance_in_seconds <= 59) {
                    $string = _('weniger als einer Minute');
                } else {
                    $string = _('1 Minute');
                }
            }
        } else if ($distance_in_minutes >= 2 && $distance_in_minutes <= 44) {
            $string = _('%minutes% Minuten');
            $parameters['%minutes%'] = $distance_in_minutes;
        } else if ($distance_in_minutes >= 45 && $distance_in_minutes <= 89) {
            $string = _('ca. 1 Stunde');
        } else if ($distance_in_minutes >= 90 && $distance_in_minutes <= 1439) {
            $string = _('ca. %hours% Stunden');
            $parameters['%hours%'] = round($distance_in_minutes / 60);
        } else if ($distance_in_minutes >= 1440 && $distance_in_minutes <= 2879) {
            $string = _('1 Tag');
        } else if ($distance_in_minutes >= 2880 && $distance_in_minutes <= 43199) {
            $string = _('%days% Tagen');
            $parameters['%days%'] = round($distance_in_minutes / 1440);
        } else if ($distance_in_minutes >= 43200 && $distance_in_minutes <= 86399) {
            $string = _('ca. 1 Monat');
        } else if ($distance_in_minutes >= 86400 && $distance_in_minutes <= 525959) {
            $string = _('%months% Monaten');
            $parameters['%months%'] = round($distance_in_minutes / 43200);
        } else if ($distance_in_minutes >= 525960 && $distance_in_minutes <= 1051919) {
            $string = _('ca. einem Jahr');
        } else {
            $string = _('über %years% Jahren');
            $parameters['%years%'] = round($distance_in_minutes / 525960);
        }

        return strtr($string, $parameters);
    }

    public function describeRoutes()
    {
        return array(
            '/activities(/:range_id)' => _('Aktivitäten'),
        );
    }

    public function routes(&$router)
    {
        $router->get('/activities(/:range_id)', function ($range_id = '') use ($router) {
            URLHelper::setBaseUrl($GLOBALS['ABSOLUTE_URI_STUDIP']);
            $activities = ActivityFeed::get_activities($GLOBALS['user']->id, $range_id ?: null, 14);

            foreach ($activities as &$item) {
                if ($item['link'][0] === '/') {
                    $item['link'] = str_replace($GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP'], '', $item['link']);
                    $item['link'] = URLHelper::getURL($item['link']);
                }
                if ($item['category'] === 'files') {
                    $query = "SELECT seminar_id FROM dokumente WHERE dokument_id = ?";
                    $statement = DBManager::get()->prepare($query);
                    $statement->execute(array($item['id']));
                    $seminar_id = $statement->fetchColumn();

                    $item['action'] = sprintf('studip://course/%s/documents/%s', $seminar_id, $item['id']);
                } else if ($item['category'] === 'forum') {
                    $query = "SELECT seminar_id FROM forum_entries WHERE topic_id = ?";
                    $statement = DBManager::get()->prepare($query);
                    $statement->execute(array($item['id']));
                    $seminar_id = $statement->fetchColumn();

                    $item['action'] = sprintf('studip://course/%s/forum/%s', $seminar_id, $item['id']);
                } else if ($item['category'] === 'news') {
                    $parsed = parse_url($item['link']);
                    parse_str($parsed['query'] ?: '', $parameters);
                    if (strpos($parsed['path'], 'about.php') !== false) {
                        $user = User::findByUsername($parameters['username']);
                        $item['action'] = sprintf('studip://user/%s/news/%s', $user->user_id, $item['id']);
                    } else if (strpos($parsed['path'], 'seminar_main.php') !== false) {
                        $item['action'] = sprintf('studip://course/%s/news/%s', $parameters['cid'], $item['id']);
                    } else if (strpos($parsed['path'], 'institut_main.php') !== false) {
                        $item['action'] = sprintf('studip://institute/%s/news/%s', $parmeters['cid'], $item['id']);
                    }
                } else if ($item['category'] === 'wiki') {
                    $parsed = parse_url($item['link']);
                    parse_str($parsed['query'] ?: '', $parameters);
                    $item['action'] = sprintf('studip://course/%s/wiki/%s', $parameters['cid'], $parameters['keyword']);
                } else {
                    $item['action'] = sprintf('studip://%s/%s', $item['category'], $item['id']);
                }
//                unset($item['author']);
            }

            $router->render(compact('activities'));
        })->conditions(array('range_id' => '|[a-f0-9]{32}|user'));
    }
}

if (interface_exists('APIPlugin')) {
    class ActivityFeed extends ActivityFeedBase implements APIPlugin {}
} else {
    class ActivityFeed extends ActivityFeedBase {}
}

