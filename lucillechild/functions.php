<?php

require_once(get_theme_file_path('includes/jm_events_options.php'));


    function jm_log(string $message): string
    {
        static $log = '';

        if ($message !== '') {
            $date_time = jm_get_log_date();
            $log = $log . "[$date_time] [log] $message" . PHP_EOL;
            if (WP_DEBUG) {
                error_log('[jm_auto] [log] ' . $message);
            }
        }

        return $log;
    }

    function jm_warn(string $message): string
    {
        static $warnings = '';

        if ($message !== '') {
            $date_time = jm_get_log_date();
            $warnings = $warnings . "[$date_time] [warning] $message" . PHP_EOL;
            if (WP_DEBUG) {
                error_log('[jm_auto] [warning] ' . $message);
            }
        }

        return $warnings;
    }

    function jm_error(string $message): string
    {
        static $errors = '';

        if ($message !== '') {
            $date_time = jm_get_log_date();
            $errors = $errors . "[$date_time] [error] $message" . PHP_EOL;
            if (WP_DEBUG) {
                error_log('[jm_auto] [error] ' . $message);
            }
        }

        return $errors;
    }

    function jm_send_logs()
    {
        $log = jm_log('');
        $warnings = jm_warn('');
        $errors = jm_error('');

        $subject = 'Jakob Manz Auto Events Report';

        $message = '------------------- LOG -------------------' . PHP_EOL . PHP_EOL;
        $message = $message . $log;

        if ($warnings !== '') {
            $subject = $subject . ' [WARNING]';

            $message = $message . PHP_EOL . PHP_EOL;
            $message = $message . '------------------- WARNINGS -------------------' . PHP_EOL . PHP_EOL;
            $message = $message . $warnings;
        }

        if ($errors !== '') {
            $subject = $subject . ' [ERROR]';

            $message = $message . PHP_EOL . PHP_EOL;
            $message = $message . '------------------- ERRORS -------------------' . PHP_EOL . PHP_EOL;
            $message = $message . $errors;
        }

        jm_send_email($subject, $message);
    }

    function jm_send_email(string $subject, string $message): void
    {
        $admin_email = get_bloginfo('admin_email');
        wp_mail($admin_email, $subject, $message);
    }
    
    function jm_dump($value): void
    {
        if (WP_DEBUG) {
            error_log('[jm_auto_events] [dump] ' . print_r($value, true));
        }
    }

    function jm_get_log_date(): string
    {
        return date('d-M-Y H:i:s T');
    }
?>
