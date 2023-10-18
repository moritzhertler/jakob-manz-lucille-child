<?php

require_once(get_theme_file_path('includes/jm_events_options.php'));
require_once(get_theme_file_path('includes/jm_logger.php'));
require_once(get_theme_file_path('includes/jm_auto_events.php'));

add_action('jm_auto_events_action', 'jm_auto_events');
function jm_auto_events()
{
    $action = new JMAutoEventsAction();
    $action->run();
}
