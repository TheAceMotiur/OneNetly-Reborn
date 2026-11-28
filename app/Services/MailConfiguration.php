<?php

namespace App\Services;

class MailConfiguration
{
    /**
     * Switch the application to the PHPMailer driver when an SMTP host is configured.
     */
    public function apply(SettingsService $settings): void
    {
        $host = trim((string) $settings->get('mail_host'));

        if ($host === '') {
            return;
        }

        $encryption = (string) $settings->get('mail_encryption');

        if (! in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        config([
            'mail.default' => 'phpmailer',
            'mail.mailers.phpmailer' => [
                'transport' => 'phpmailer',
                'host' => $host,
                'port' => (int) ($settings->get('mail_port') ?: 587),
                'username' => $settings->get('mail_username') ?: null,
                'password' => $settings->get('mail_password') ?: null,
                'encryption' => $encryption,
                'timeout' => 15,
            ],
        ]);
    }
}
