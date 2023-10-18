<?php

class JMLogger
{

    private $logs = array();
    private bool $hasWarning = false;
    private bool $hasError = false;

    public function hasWarning(): bool
    {
        return $this->hasWarning;
    }

    public function hasError(): bool
    {
        return $this->hasWarning;
    }

    public function log(string $message)
    {
        $this->add($message, 'log');
    }

    public function warn(string $message)
    {
        $this->add($message, 'warn');
        $this->hasWarning = true;
    }

    public function error(string $message)
    {
        $this->add($message, 'error');
        $this->hasError = true;
    }

    public function dump(string $message, $value)
    {
        $this->add($message . PHP_EOL . print_r($value, true), 'dump');
    }

    public function send(string $email_address, string $suffix = '')
    {

        $subject = 'Jakob Manz Auto Events Report';

        if ($this->hasWarning) {
            $subject = $subject . ' [WARNING]';
        }

        if ($this->hasError) {
            $subject = $subject . ' [ERROR]';
        }

        $message = '';

        foreach ($this->logs as $log) {
            $log_message = "[{$log['date_time']}] [{$log['type']}] {$log['message']}" . PHP_EOL;

            $space = '';

            if ($log['type'] == 'warn' || $log['type'] == 'error') {
                $space = PHP_EOL;
            }

            $message = $message . $space . $log_message . $space;
        }

        $message = $message . PHP_EOL . PHP_EOL . $suffix;

        wp_mail($email_address, $subject, $message);
    }

    private function add(string $message, string $type)
    {
        $this->logs[] = array(
            'type' => $type,
            'date_time' => $this->get_date(),
            'message' => $message
        );

        if (WP_DEBUG) {
            error_log('[jm_auto] [' . $type . '] ' . $message);
        }
    }

    private function get_date(): string
    {
        return date('d-M-Y H:i:s T');
    }
}
