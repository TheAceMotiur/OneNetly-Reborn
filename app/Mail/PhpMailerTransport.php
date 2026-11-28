<?php

namespace App\Mail;

use PHPMailer\PHPMailer\SMTP;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class PhpMailerTransport implements TransportInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly string $encryption = 'tls',
        private readonly int $timeout = 15,
        private readonly string $localDomain = 'localhost',
    ) {}

    /**
     * Send the given message through PHPMailer's SMTP client.
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);

        $smtp = new SMTP;
        $smtp->setTimeout($this->timeout);

        $host = $this->encryption === 'ssl' ? 'ssl://'.$this->host : $this->host;

        if (! $smtp->connect($host, $this->port, $this->timeout, [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ])) {
            $this->fail($smtp, 'Could not connect to the SMTP server');
        }

        try {
            if ($this->encryption === 'tls' && ! $smtp->startTLS()) {
                $this->fail($smtp, 'Could not start TLS on the SMTP connection');
            }

            if (! $smtp->hello($this->localDomain)) {
                $this->fail($smtp, 'The SMTP server rejected the greeting');
            }

            if (filled($this->username) && ! $smtp->authenticate($this->username, (string) $this->password)) {
                $this->fail($smtp, 'SMTP authentication failed');
            }

            if (! $smtp->mail($envelope->getSender()->getAddress())) {
                $this->fail($smtp, 'The SMTP server rejected the sender');
            }

            foreach ($envelope->getRecipients() as $recipient) {
                if (! $smtp->recipient($recipient->getAddress())) {
                    $this->fail($smtp, 'The SMTP server rejected a recipient');
                }
            }

            if (! $smtp->data($message->toString())) {
                $this->fail($smtp, 'The SMTP server rejected the message');
            }
        } finally {
            if ($smtp->connected()) {
                $smtp->quit();
            }
        }

        return new SentMessage($message, $envelope);
    }

    /**
     * Throw a transport exception with the last SMTP error.
     */
    private function fail(SMTP $smtp, string $message): never
    {
        $detail = (string) ($smtp->getError()['error'] ?? '');

        throw new TransportException(trim($message.($detail !== '' ? ': '.$detail : '')));
    }

    public function __toString(): string
    {
        return sprintf('phpmailer://%s:%d', $this->host, $this->port);
    }
}
