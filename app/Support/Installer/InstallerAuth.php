<?php

declare(strict_types=1);

namespace App\Support\Installer;

/**
 * Gates update mode behind an existing administrator account.
 *
 * A successful login returns a random token that is kept hashed on disk and is
 * sent back with every update request, so the password is never re-submitted.
 */
final class InstallerAuth
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 600;

    private const SESSION_SECONDS = 7200;

    public function __construct(
        private readonly Installer $installer,
        private readonly string $basePath,
    ) {}

    /**
     * @return array{ok: bool, message: string, token?: string}
     */
    public function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $state = $this->readJson($this->attemptsPath());

        if ((int) ($state['locked_until'] ?? 0) > time()) {
            $minutes = (int) ceil(((int) $state['locked_until'] - time()) / 60);

            return ['ok' => false, 'message' => "Too many failed attempts. Try again in {$minutes} minute(s)."];
        }

        if ($email === '' || $password === '' || ! $this->installer->verifyAdmin($email, $password)) {
            return ['ok' => false, 'message' => $this->recordFailure($state)];
        }

        $this->writeJson($this->attemptsPath(), ['failures' => 0, 'locked_until' => 0]);

        $token = bin2hex(random_bytes(32));
        $sessions = $this->activeSessions();
        $sessions[$this->hash($token)] = time() + self::SESSION_SECONDS;
        $this->writeJson($this->sessionsPath(), $sessions);

        $this->installer->log('Administrator signed in to the updater: '.$email);

        return [
            'ok' => true,
            'message' => 'Signed in as '.$email.'.',
            'token' => $token,
        ];
    }

    public function check(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $sessions = $this->activeSessions();

        return isset($sessions[$this->hash($token)]);
    }

    public function revoke(?string $token): void
    {
        if (! is_string($token) || $token === '') {
            return;
        }

        $sessions = $this->activeSessions();
        unset($sessions[$this->hash($token)]);
        $this->writeJson($this->sessionsPath(), $sessions);
    }

    /**
     * Remaining lockout time in seconds, for the UI.
     */
    public function lockedFor(): int
    {
        $state = $this->readJson($this->attemptsPath());
        $until = (int) ($state['locked_until'] ?? 0);

        return max(0, $until - time());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function recordFailure(array $state): string
    {
        $failures = (int) ($state['failures'] ?? 0) + 1;

        if ($failures >= self::MAX_ATTEMPTS) {
            $this->writeJson($this->attemptsPath(), [
                'failures' => 0,
                'locked_until' => time() + self::LOCKOUT_SECONDS,
            ]);

            $this->installer->log('Updater login locked out after '.self::MAX_ATTEMPTS.' failed attempts.');

            return 'Too many failed attempts. The updater is locked for 10 minutes.';
        }

        $this->writeJson($this->attemptsPath(), [
            'failures' => $failures,
            'locked_until' => 0,
        ]);

        $remaining = self::MAX_ATTEMPTS - $failures;

        return "Invalid email or password. {$remaining} attempt(s) left before a 10 minute lockout.";
    }

    /**
     * @return array<string, int>
     */
    private function activeSessions(): array
    {
        $now = time();
        $sessions = [];

        foreach ($this->readJson($this->sessionsPath()) as $hash => $expiresAt) {
            if ((int) $expiresAt > $now) {
                $sessions[$hash] = (int) $expiresAt;
            }
        }

        return $sessions;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $contents = @file_get_contents($path);
        $decoded = $contents === false ? null : json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($path, 0600);
    }

    private function attemptsPath(): string
    {
        return $this->basePath.'/storage/app/install.attempts.json';
    }

    private function sessionsPath(): string
    {
        return $this->basePath.'/storage/app/install.sessions.json';
    }
}
