<link rel="alternate"
      type="application/atom+xml"
      title="ActivityFeed"
      href="<?= PluginEngine::getLink($action, array_fill_keys(array_keys(URLHelper::getLinkParams()), NULL) + array('range' => $range)) ?>">
