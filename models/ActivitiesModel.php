<?php

class ActivitiesModel
{
    public function getUserNews($user_id){
        $user_news = array();
        if(StudipNews::haveRangePermission('view', $user_id, $user_id)){
            $user_news[] = StudipNews::GetNewsByRange($user_id, false, false);
        }
        return self::NewsToActivityItem($user_news);
    }
    
    public function getUserNewsForSystem($user_id){
        $system_news = array();
        if(StudipNews::haveRangePermission('view', 'studip', $user_id)){
            $system_news[] = StudipNews::GetNewsByRange('studip', false, false);
        }
        return $system_news;
    }
    
    public function getUserNewsForCourses($user_id, $course_ids = array()) {
        $course_news = array();
        foreach($course_ids  as $course_id){
            if(StudipNews::haveRangePermission('view', $course_id['seminar_id'], $user_id)){
                $course_news[] = StudipNews::GetNewsByRange($course_id['seminar_id'], true, true);
            }
        }
        return self::NewsToActivityItem($course_news, 'courses');
    }
    
    public function getUserNewsForInstitutes($user_id, $institute_ids = array()) {
        $institute_news = array();
        foreach($institute_ids as $institute_id){
            if(StudipNews::haveRangePermission('view', $institute_id['institut_id'], $user_id)){
                $institute_news[] = StudipNews::GetNewsByRange($institute_id['institut_id'], true, true);
            } 
        }
        return self::NewsToActivityItem($institute_news, 'institues');
    }

    static function NewsToActivityItem($news_entries, $range ='') {
        
        if(in_array($range, array('courses','institues'))){
            foreach($news_entries as $news_entry) {
                foreach($news_entry as $key => $entry) {
                    $ranges = $entry->getRanges();
                    $entry_arr = $entry->toArray();
                    if($range == 'courses') {
                        $range_label = _("Veranstaltung");
                        if(is_array($ranges)){
                            $course = Course::find($ranges[0]);
                            $coursearray = $course->toArray();
                        }
                        $range_name = $coursearray['name'];
                    } else if ($range == 'institues') {
                        $range_label = _("Einrichtung");
                        if(is_array($ranges)){
                            $inst = Institute::findBySQL("institut_id=?", array($ranges[0]));
                            $instarray = $inst[0]->toArray();
                        }
                        $range_name = $instarray['name'];
                    }
                    //todo fix link to news
                    $items = array(
                        'id' => $entry_arr['news_id'],
                        'title' => _('Ankündigung: ') . $entry_arr['topic'],
                        'author' => $entry_arr['author'],
                        'author_id' => $entry_arr['user_id'],
                        'link' => URLHelper::getLink('about.php#anker',
                            array('username' => $entry_arr['author'], 'nopen' => $entry_arr['news_id'])),
                        'updated' => max($entry_arr['date'], $entry_arr['chdate']),
                        'summary' => sprintf('%s hat in der %s "%s" die Ankündigung "%s" eingestellt.',
                            $entry_arr['author'], $range_label, $range_name, $entry_arr['topic']),
                        'content' => $entry_arr['body'],
                        'username' => $entry_arr['auhter'],
                        'item_name' => $entry_arr['topic'],
                        'category' => 'news'
                    );
                }
            }
        } else {
            foreach($news_entries as $entries) {
                foreach($entries as $entry){
                    $items[] = array(
                        'id' => $entry['news_id'],
                        'title' => 'Ankündigung: ' . $entry['topic'],
                        'author' => $entry['author'],
                        'author_id' => $entry['user_id'],
                        'link' => URLHelper::getLink('about.php#anker',
                            array('username' => $entry['username'], 'nopen' => $entry['news_id'])),
                        'updated' => max($entry['date'], $entry['chdate']),
                        'summary' => sprintf('%s hat die persönliche Ankündigung "%s" eingestellt.',
                            $entry['author'], $entry['topic']),
                        'content' => $entry['body'],
                        'username' => $entry['username'],
                        'item_name' => $entry['topic'],
                        'category' => 'news'
                    );
                }
            }
        }
        return $items;
    }
}

?>