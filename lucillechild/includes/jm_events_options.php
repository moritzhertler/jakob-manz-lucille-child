<?php

/**
 * # Overview of options ids
 * 
 * ## General ids/slugs
 * 
 * page id/menu slug: jm-events-options
 * option group: 'jm-events-settings-group'
 * option name: jm-events
 * option page hook suffix: settings_page_jm-events-options
 * 
 * ## all sections by section id and their fields by field id and input type
 *  
 * - logs-section
 *     - logs-email                             (text/email)            
 *     - logs-include-response                  (checkbox)              
 * - system-one-section
 *     - system-one-api-url                     (text/url)              
 *     - system-one-entity                      (text)
 *     - system-one-upcoming-limit              (number/positive)
 *     - system-one-artists                     (textarea/json)
 * - system-one-hints-section
 *     - system-one-hints-email                 (text/email)
 *     - system-one-hints-incomplete-address    (checkbox)
 *     - system-one-hints-status-o              (checkbox)
 *     - system-one-hints-status-c              (checkbox)
 */

// add new options page 
add_action('admin_menu', 'jm_events_setup_page');
function jm_events_setup_page()
{
    add_options_page(
        'Jakob Manz Events Settings',       /* page title */
        'Jakob Manz Events Settings',       /* menu title */
        'manage_options',                   /* capability */
        'jm-events-options',                /* menu slug */
        'jm_events_render_settings_page'    /* function */
    );
}

// add js dependencies (code editor 'codemirror5')
add_action('admin_enqueue_scripts', 'jm_events_enqueue_dependencies');
function jm_events_enqueue_dependencies($hook)
{
    if ($hook != 'settings_page_jm-events-options') {
        return;
    }

    $dir = get_stylesheet_directory_uri();
    wp_enqueue_script('codemirror5', $dir . '/assets/codemirror5/codemirror.js');
    wp_enqueue_script('codemirror5-javascript', $dir . '/assets/codemirror5/modes/javascript.js');
    wp_enqueue_style('codemirror5-styles', $dir . '/assets/codemirror5/codemirror.css');
}

// add theme page shortcut to admin bar
add_action('admin_bar_menu', 'jm_events_add_settings_to_admin_bar', 9999);
function jm_events_add_settings_to_admin_bar($admin_bar)
{
    $admin_bar->add_menu(
        array(
            'id'    => 'jm-events-settings',
            'title' => 'Jakob Manz Events Settings',
            'href'  => admin_url('options-general.php?page=jm-events-options'),
            'meta'  => array(
                'title' => "Go to Jakob Manz Events Settings"
            )
        )
    );
}

function jm_events_render_settings_page()
{
?>
    <div class="wrap">
        <h2>Jakob Manz Events Options</h2>
        <form action="options.php" method="POST">
            <?php settings_fields('jm-events-settings-group'); ?>
            <?php do_settings_sections('jm-events-options'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

add_action('admin_init', 'jm_events_register_settings');
function jm_events_register_settings()
{
    register_setting('jm-events-settings-group', 'jm-events', 'jm_events_validate_settings');

    add_settings_section('logs-section', "Logs", 'jm_events_section_logs_callback', 'jm-events-options');
    add_settings_field(
        'logs-email',
        'Email address',
        'jm_events_email_field',
        'jm-events-options',
        'logs-section',
        array(
            'name'  => 'logs-email',
            'label' => 'The email address logs are sent to.'
        )
    );
    add_settings_field(
        'logs-include-response',
        'Include responses',
        'jm_events_checkbox_field',
        'jm-events-options',
        'logs-section',
        array(
            'name'  => 'logs-include-response',
            'label' => 'Include the api response bodies in the logs.'
        )
    );

    add_settings_section('system-one-section', "System One Events", 'jm_events_section_system_one_callback', 'jm-events-options');
    add_settings_field(
        'system-one-api-url',
        'API Endpoint URL',
        'jm_events_url_field',
        'jm-events-options',
        'system-one-section',
        array(
            'name'  => 'system-one-api-url',
            'label' => 'The URL of the system one API.'
        )
    );
    add_settings_field(
        'system-one-entity',
        'System One Entity',
        'jm_events_text_field',
        'jm-events-options',
        'system-one-section',
        array(
            'name'  => 'system-one-entity',
            'label' => 'The System One entity to crawl.'
        )
    );
    add_settings_field(
        'system-one-upcoming-limit',
        'System One Upcoming Limit',
        'jm_events_number_field',
        'jm-events-options',
        'system-one-section',
        array(
            'name'  => 'system-one-upcoming-limit',
            'label' => 'Specifies how far into the future events are fetched (in days).',
            'min'   => 0
        )
    );
    add_settings_field(
        'system-one-artists',
        'System One Artists',
        'jm_events_json_textarea_field',
        'jm-events-options',
        'system-one-section',
        array(
            'name'  => 'system-one-artists',
            'label' => 'The artists to crawl. Expects a JSON object where the system one ids are keys and each objects has a name (the display name - string), a category_id (to which category the events should be added - number) and a thumbnail_id (the image to show on the events detail page - string).'
        )
    );

    add_settings_section('system-one-hints-section', "System One Events Hints", 'jm_events_section_system_one_hints_callback', 'jm-events-options');
    add_settings_field(
        'system-one-hints-email',
        'System One Hints Email address',
        'jm_events_email_field',
        'jm-events-options',
        'system-one-hints-section',
        array(
            'name'  => 'system-one-hints-email',
            'label' => 'The email address hints are sent to.'
        )
    );
    add_settings_field(
        'system-one-hints-incomplete-address',
        'Incomplete Address',
        'jm_events_checkbox_field',
        'jm-events-options',
        'system-one-hints-section',
        array(
            'name'  => 'system-one-hints-incomplete-address',
            'label' => 'Send a hint if the response contains an incomplete event address.'
        )
    );
    add_settings_field(
        'system-one-hints-status-o',
        'Status "O"',
        'jm_events_checkbox_field',
        'jm-events-options',
        'system-one-hints-section',
        array(
            'name'  => 'system-one-hints-status-o',
            'label' => 'Send a hint if the response contains an event with the status "O".'
        )
    );
    add_settings_field(
        'system-one-hints-status-c',
        'Status "C"',
        'jm_events_checkbox_field',
        'jm-events-options',
        'system-one-hints-section',
        array(
            'name'  => 'system-one-hints-status-c',
            'label' => 'Send a hint if the response contains an event with the status "C".'
        )
    );
}

function jm_events_section_logs_callback()
{
    echo '<p>Settings related to logging behavior.</p>';
}

function jm_events_section_system_one_callback()
{
    echo '<p>Settings related to events managed in System One.</p>';
}

function jm_events_section_system_one_hints_callback()
{
    echo '<p>Settings related to hints about possible errors in the system one api response.</p>';
}

function jm_events_validate_settings($input)
{
    $output = get_option('jm-events');

    // logs section
    $output['logs-email'] = $input['logs-email'];
    jm_events_set_checkbox('logs-include-response', $input, $output);

    // system one section
    $output['system-one-api-url'] = $input['system-one-api-url'];
    $output['system-one-entity'] = $input['system-one-entity'];
    jm_events_check_empty_string($input, 'system-one-entity', $input['system-one-api-url'] !== '');
    $output['system-one-upcoming-limit'] = $input['system-one-upcoming-limit'];
    jm_events_check_empty_string($input, 'system-one-upcoming-limit', $input['system-one-api-url'] !== '');
    $output['system-one-artists'] = $input['system-one-artists'];
    jm_events_check_empty_string($input, 'system-one-artists', $input['system-one-api-url'] !== '');

    $artists = json_decode($input['system-one-artists']);
    if ($artists !== null) {
        $output['system-one-artists'] = $input['system-one-artists'];
    } else {
        add_settings_error('jm-events', 'artists-invalid-json', 'system-one-artists: Invalid json');
    }

    // system one hints section
    $output['system-one-hints-email'] = $input['system-one-hints-email'];
    jm_events_set_checkbox('system-one-hints-incomplete-address', $input, $output);
    jm_events_set_checkbox('system-one-hints-status-o', $input, $output);
    jm_events_set_checkbox('system-one-hints-status-c', $input, $output);

    return $output;
}

/**
 * Sets `$input[$field_id]` to `true` or `false` whether `$input[$field_id]` is set or not.
 */
function jm_events_set_checkbox(string $field_id, array $input, array &$output)
{
    $output[$field_id] = isset($input[$field_id]);
}

/**
 * Adds a settings error if `$add_error` is `true` and `$input[$field_id]` is an empty string.
 */
function jm_events_check_empty_string(array $input, string $field_id, bool $add_error)
{
    if ($add_error && $input[$field_id] === '') {
        add_settings_error('jm_events', "empty-field-{$field_id}", "{$field_id}: Empty field is not allowed.");
    }
}

/**
 * A text input.
 * 
 * ### Args 
 * `name`: The field id
 * `label`: The description displayed below the input
 */
function jm_events_text_field($args)
{
    $options = (array) get_option('jm-events');
    $value = esc_attr($options[$args['name']]);
    echo "<p><input type='text' id='{$args['name']}' name='jm-events[{$args['name']}]' value='$value' /></p>";
    echo "<p><label for='{$args['name']}'>{$args['label']}</label></p>";
}

/**
 * An email input (i.e. text input that only accepts valid emails).
 * 
 * ### Args 
 * `name`: The field id
 * `label`: The description displayed below the input
 */
function jm_events_email_field($args)
{
    $options = (array) get_option('jm-events');
    $value = esc_attr($options[$args['name']]);
    echo "<p><input type='email' id='{$args['name']}' name='jm-events[{$args['name']}]' value='$value' /></p>";
    echo "<p><label for='{$args['name']}'>{$args['label']}</label></p>";
}

/**
 * An url input (i.e. text input that only accepts valid urls).
 * 
 * ### Args 
 * `name`: The field id
 * `label`: The description displayed below the input
 */
function jm_events_url_field($args)
{
    $options = (array) get_option('jm-events');
    $value = esc_attr($options[$args['name']]);
    echo "<p><input type='url' id='{$args['name']}' name='jm-events[{$args['name']}]' value='$value' /></p>";
    echo "<p><label for='{$args['name']}'>{$args['label']}</label></p>";
}

/**
 * A number input.  
 * 
 * ### Args 
 * `name`: The field id
 * `label`: The description displayed below the input
 * `min`: The min value
 * `max`: The max value
 */
function jm_events_number_field($args)
{
    $options = (array) get_option('jm-events');
    $value = esc_attr($options[$args['name']]);
    echo "<p><input type='number' id='{$args['name']}' name='jm-events[{$args['name']}]' value='$value' min='{$args['min']}' max='{$args['max']}' /></p>";
    echo "<p><label for='{$args['name']}'>{$args['label']}</label></p>";
}

/**
 * A checkbox.  
 * 
 * ### Args 
 * `name`: The field id
 * `label`: The description displayed below the input
 */
function jm_events_checkbox_field($args)
{
    $options = (array) get_option('jm-events');
    $value = $options[$args['name']] ? 'checked' : '';
    echo "<p><input type='checkbox' id='{$args['name']}' name='jm-events[{$args['name']}]' $value/></p>";
    echo "<p><label for='{$args['name']}'>{$args['label']}</label></p>";
}

/**
 * A hidden textarea with a linked CodeMirror code editor with json syntax highlighting.  
 * 
 * ### Args 
 * `name`: The field id
 * `label`: The description displayed below the input
 */
function jm_events_json_textarea_field($args)
{
    $options = (array) get_option('jm-events');
    $value = esc_attr($options[$args['name']]);
    echo "<textarea id='json-textarea[{$args['name']}]' name='jm-events[{$args['name']}]'>$value</textarea>";
    echo "<p><label for='json-textarea[{$args['name']}]'>{$args['label']}</label></p>";
?>
    <script>
        {
            const jsonTextarea = document.getElementById("json-textarea[<?php echo $args['name'] ?>]");
            CodeMirror.fromTextArea(jsonTextarea, {
                name: "javascript",
                json: true
            });
        }
    </script>
<?php
}

?>
