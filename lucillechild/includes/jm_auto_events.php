<?php

class JMAutoEventsAction
{
    private JMLogger $logger;
    private JMLogger $hints;
    private array $options;

    private string $response = '';
    // the artists by system one id
    private $artists;
    private array $artists_ids;

    public function __construct()
    {
        $this->logger = new JMLogger();
        $this->hints = new JMLogger();
        $this->options = get_option('jm-events');
        $this->artists = json_decode($this->options['system-one-artists'], true);

        $this->artists_ids = array();
        foreach ($this->artists as $id => $artist) {
            array_push($this->artists_ids, $id);
        }
    }

    public function run(): void
    {
        $this->logger->log('Starting auto event script...');

        $posts = $this->get_posts();
        $events_response = $this->fetch_events();
        if (count($events_response) === 0) {
            $this->logger->log("Nothing to do");
            return;
        }

        $events = $this->create_event_map($events_response);

        $this->logger->log('Comparing with ' . count($posts) . ' existing posts...');
        foreach ($posts as $post) {
            $id = $post->jm_system_one_id;
            if (!array_key_exists($id, $events)) {
                $this->logger->log("Removing event $id because it is no longer in API response");
                $this->remove_event($post->ID);
                continue;
            }

            $old_hash = $post->jm_hash;

            $new_event_data = $events[$id];
            unset($events[$id]);

            $new_event = $this->sanitize_event_data($new_event_data);
            $new_hash = $this->hash($new_event);

            if ($old_hash === $new_hash) {
                $this->logger->log("Event {$new_event['id']} unchanged");
                continue;
            }

            if ($new_event['status'] !== 'D') {
                $this->logger->log("Removing event {$new_event['id']} because of new status {$new_event['status']}");
                $this->remove_event($post->ID);
                continue;
            }

            $this->logger->log("Updating event {$new_event['id']}");
            $this->update_event($post->ID, $new_event, $new_hash);
        }

        $this->logger->log('Checking ' . count($events) . ' remaining event(s)');

        foreach ($events as $event_data) {
            $event = $this->sanitize_event_data($event_data);

            if ($event === null) {
                // unrecoverable error in jm_sanitize_event_data => skip event
                continue;
            }

            // possible values for status: 'D' = Definitive, 'C' = Cancelled, 'O' = Option
            if ($event['status'] !== 'D') {
                $this->logger->log("Skipping {$event['id']} because of status {$event['status']}");
                continue;
            }

            $this->add_event($event, $this->hash($event));
        }

        $suffix = '';
        if ($this->options['logs-include-response']) {
            $suffix = 'RESPONSE:' . PHP_EOL . $this->response;
        }

        $this->logger->send($this->options['logs-email'], $suffix);
        if ($this->hints->hasError() || $this->hints->hasWarning()) {
            $this->hints->send($this->options['logs-email']);
            $this->hints->send($this->options['system-one-hints-email']);
        }
    }

    function hash(array $sanitized_event): string
    {
        return hash('sha256', print_r($sanitized_event, true));
    }

    private function get_posts(): array
    {
        $args = array(
            'numberposts' => -1,
            'post_type' => 'js_events',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'jm_system_one_id',
                    'compare_key' => 'EXISTS'
                ),
                array(
                    'key' => 'event_date',
                    'compare' => '>=',
                    'value' => $this->get_event_date_string()
                )
            )
        );

        return get_posts($args);
    }

    private function get_event_date_string(?int $timestamp = null): string
    {
        return date('Y/m/d', $timestamp);
    }

    private function fetch_events(): array
    {
        $url = $this->get_request_url();

        $this->logger->log("Fetching \"$url\"");

        $res = $this->fetch($url);
        if ($res === '') {
            $this->logger->error("Response was empty");
            return array();
        }

        $data = json_decode($res, false);
        if ($data === null) {
            $this->logger->error("Failed to decode json: $res");
            return array();
        }

        $page_info = $data->pagination;
        if ($page_info->pageCount != 1) {
            $this->logger->error("More than one page in API response: $url");
            return array();
        }

        $this->logger->log("Fetched $page_info->total events");
        $this->response = json_encode($data, JSON_PRETTY_PRINT);

        return $data->data;
    }

    private function get_request_url()
    {
        $url = $this->options['system-one-api-url'];
        $entity = $this->options['system-one-entity'];
        $api_base = $url . $entity;

        $upcoming_limit = $this->options['system-one-upcoming-limit'];
        $artist_ids = urlencode(join(',', $this->artists_ids));
        $api_config = '?lang=de-DE&per_page=500&upcoming_limit=' . $upcoming_limit . '&artistIds=' . $artist_ids . '&page=1';

        return $api_base . $api_config;
    }

    private function fetch(string $url): string
    {
        $res = wp_remote_get($url);
        $code = wp_remote_retrieve_response_code($res);

        if ($code !== 200) {
            $this->logger->error("Received unexpected response code: $code");
            return '';
        }

        return wp_remote_retrieve_body($res);
    }


    /**
     * [Object, Object, ...] to [id => Object, id => Object, ...]
     * where 'id' is the system one id of the event
     */
    private function create_event_map(array $events)
    {
        $map = array();
        foreach ($events as $event) {
            $map[$event->id] = $event;
        }
        return $map;
    }

    private function sanitize_event_data(object $event): ?array
    {
        $id = $event->id;

        $status = $event->status->phase;

        $artist_id = $event->artist->id;
        if (!array_key_exists($artist_id, $this->artists)) {
            $this->logger->error("Unknown artist id in event $id: $artist_id");
            return null;
        }
        $artist = $this->artists[$artist_id];

        $performance_time = ($event->performanceTime !== null) ? $event->performanceTime : '';
        if ($performance_time !== '' && preg_match('/^\d\d:\d\d.*$/', $event->performanceTime) !== 1) {
            $this->logger->error("Illegal performanceTime format in event '$id': $performance_time");
            return null;
        }
        $performance_time = substr($performance_time, 0, 5);

        if ($event->date->value === null || $event->date->value === '') {
            $this->logger->error("Event $id has no date value");
            return null;
        }
        $timestamp = strtotime($event->date->value);
        if ($timestamp === false) {
            $this->logger->error("Failed to parse date value '{$event->date->value}' (event $id)");
            return null;
        }
        $date = $this->get_event_date_string($timestamp);

        if ($event->venue->name === null || $event->venue->name === '') {
            $this->logger->error("Venue {$event->venue->id} of event $id has no name");
            return null;
        }
        $venue_name = $event->venue->name;

        $venue_url = ($event->venue->url !== null) ? $event->venue->url : '';

        $tickets_url = '';
        if ($event->tickets->url !== null) {
            $tickets_url = $event->tickets->url;
        } else if ($event->eventUrl !== null) {
            $tickets_url = $event->eventUrl;
        } else if ($venue_url !== '') {
            $tickets_url = $venue_url;
        }
        $tickets_message = ($tickets_url !== '') ? 'Buy Tickets' : '';

        $location = '';
        if ($event->venue->city !== null && $event->venue->city !== '') {
            $location = $event->venue->city;
        } else if ($event->venue->street !== null && $event->venue->street !== '') {
            $message = "The venue {$event->venue->name} ({$event->venue->id}) of event {$event->eventName} ($id) has no city but a street ({$event->venue->street}).";
            $this->logger->warn($message);
            if ($this->options['system-one-hints-incomplete-address']) {
                $this->hints->warn($message);
            }
        } else {
            $message = "The venue {$event->venue->id} of event $id has no city and no street.";
            $this->logger->warn($message);
            if ($this->options['system-one-hints-incomplete-address']) {
                $this->hints->warn($message);
            }
        }

        $name = ($event->eventName !== null && $event->eventName !== $venue_name) ? $event->eventName : '';

        return array(
            'id' => $id,
            'status' => $status,
            'artist' => $artist,
            'performance_time' => $performance_time,
            'tickets_url' => $tickets_url,
            'tickets_message' => $tickets_message,
            'date' => $date,
            'venue_name' => $venue_name,
            'venue_url' => $venue_url,
            'location' => $location,
            'name' => $name
        );
    }

    private function add_event(array $event, string $hash): void
    {
        $this->logger->log("Adding event {$event['id']}");

        $artist = $event['artist'];

        $post = array(
            'post_author' => 2,
            'post_title' => $artist['name'],
            'post_status' => 'publish',
            'post_type' => 'js_events',
            'post_content' => $event['name'],
            'meta_input' => array(
                '_thumbnail_id' => $artist['thumbnail_id'],
                'event_buy_tickets_message' => $event['tickets_message'],
                'event_buy_tickets_target' => '_blank',
                'event_buy_tickets_url' => $event['tickets_url'],
                'event_currency' => 'euro',
                'event_date' => $event['date'],
                'event_end_date' => '',
                'event_fb_message' => '',
                'event_fb_url' => '',
                'event_location' => $event['location'],
                'event_map_url' => '',
                'event_multiday' => '0',
                'event_price' => '',
                'event_time' => $event['performance_time'],
                'event_venue' => $event['venue_name'],
                'event_venue_target' => '_blank',
                'event_venue_url' => $event['venue_url'],
                'event_vimeo_url' => '',
                'event_youtube_url' => '',
                'js_swp_meta_bg_image' => '',
                'lc_swp_contact_string1' => '',
                'lc_swp_contact_string2' => '',
                'lc_swp_meta_disco_it_on_row' => '3',
                'lc_swp_meta_heading_bg_image' => '',
                'lc_swp_meta_heading_color_theme' => '',
                'lc_swp_meta_heading_full_color' => '',
                'lc_swp_meta_heading_overlay_color' => '',
                'lc_swp_meta_page_logo' => '',
                'lc_swp_meta_page_menu_bg' => '',
                'lc_swp_meta_page_menu_txt_color' => '',
                'lc_swp_meta_page_overlay_color' => '',
                'lc_swp_meta_page_remove_footer' => '0',
                'lc_swp_meta_subtitle' => '',
                'jm_system_one_id' => $event['id'],
                'jm_hash' => $hash
            )
        );

        $error = wp_insert_post($post, true);
        if (is_wp_error($error)) {
            $this->logger->error("Inserting post failed: {$error->get_error_message()}");
            $this->logger->dump('Event: ', $event);
            $this->logger->dump('Post: ', $post);
            return;
        }

        $post_id = $error;
        $error = wp_set_post_terms($post_id, array($artist['category_id']), 'event_category');
        if (!$error || is_wp_error($error)) {
            $this->logger->error("Setting post terms failed: {$error->get_error_message()}");
            $this->logger->dump('Event: ', $event);
            $this->logger->dump('Post: ', $post);
            return;
        }
    }

    private function update_event(int $post_id, array $event, string $hash): void
    {
        $artist = $event['artist'];

        $post = array(
            'ID' => $post_id,
            'post_title' => $artist['name'],
            'post_category' => array($artist['category_id']),
            'post_content' => $event['name'],
            'meta_input' => array(
                '_thumbnail_id' => $artist['thumbnail_id'],
                'event_buy_tickets_url' => $event['tickets_url'],
                'event_buy_tickets_message' => $event['tickets_message'],
                'event_date' => $event['date'],
                'event_location' => $event['location'],
                'event_time' => $event['performance_time'],
                'event_venue' => $event['venue_name'],
                'event_venue_url' => $event['venue_url'],
                'jm_system_one_id' => $event['id'],
                'jm_hash' => $hash
            )
        );

        $error = wp_update_post($post, true);
        if (is_wp_error($error)) {
            $this->logger->error("Updating post failed: {$error->get_error_message()}");
            $this->logger->dump('Event: ', $event);
            $this->logger->dump('Post: ', $post);
        }

        $error = wp_set_post_terms($post_id, array($artist['category_id']), 'event_category');
        if (!$error || is_wp_error($error)) {
            $this->logger->error("Updating post terms failed: {$error->get_error_message()}");
            $this->logger->dump('Event: ', $event);
            $this->logger->dump('Post: ', $post);
            return;
        }
    }

    private function remove_event(int $post_id): void
    {
        $post = array(
            'ID' => $post_id,
            'post_status' => 'private'
        );

        $error = wp_update_post($post, true);
        if (is_wp_error($error)) {
            $this->logger->error("Removing post (setting as private) failed: {$error->get_error_message()}");
        }
    }
}
