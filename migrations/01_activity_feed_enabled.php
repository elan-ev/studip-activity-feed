<?php
class ActivityFeedEnabled extends Migration
{
    public function up()
    {
        $config = Config::get();

        $config->create('ACTIVITY_FEED_ENABLED', array(
            'description' => 'Erlaubt Nutzern, die globale Aktivitätsübersicht als Feed zu exportieren.',
            'section'     => 'global',
            'type'        => 'boolean',
            'value'       => 0
        ));
    }

    public function down()
    {
        $config = Config::get();

        $config->delete('ACTIVITY_FEED_ENABLED');
    }
}
?>
