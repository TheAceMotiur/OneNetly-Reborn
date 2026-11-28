<?php

declare(strict_types=1);

namespace App\Support\Installer;

use RuntimeException;

/**
 * Minimal, dependency free .env reader and writer.
 *
 * Only the keys the installer hands to it are touched; comments, ordering and
 * every other line in the file are preserved exactly as they were.
 */
final class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $values = [];

        foreach ($this->lines() as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) === 1) {
                $values[$matches[1]] = $this->unquote(trim($matches[2]));
            }
        }

        return $values;
    }

    /**
     * Create the file from a template when it does not exist yet.
     */
    public function createFrom(string $template): void
    {
        if ($this->exists() || ! is_file($template)) {
            return;
        }

        if (! @copy($template, $this->path)) {
            throw new RuntimeException('Could not create .env. Check the permissions of your project root.');
        }

        @chmod($this->path, 0600);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function set(array $values): void
    {
        $values = array_filter(
            $values,
            static fn (?string $value): bool => $value !== null,
        );

        $lines = $this->lines();
        $pending = [];

        foreach ($values as $key => $value) {
            $pending[$key] = (string) $value;
        }

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];

            if (! array_key_exists($key, $pending)) {
                continue;
            }

            $lines[$index] = $key.'='.$this->format($pending[$key]);
            unset($pending[$key]);
        }

        foreach ($pending as $key => $value) {
            $lines[] = $key.'='.$this->format($value);
        }

        $this->write(implode("\n", $lines)."\n");
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException('Could not read the .env file. Check its permissions.');
        }

        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        return explode("\n", rtrim($contents, "\n"));
    }

    private function write(string $contents): void
    {
        if (@file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the .env file. Check the permissions of your project root.');
        }

        @chmod($this->path, 0600);
    }

    private function format(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.:@\/+,%\-]*$/', $value) === 1) {
            return $value;
        }

        $escaped = str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\\"', '\\$'],
            $value,
        );

        return '"'.$escaped.'"';
    }

    private function unquote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if ($first === '"' && $last === '"' && strlen($value) >= 2) {
            return str_replace(
                ['\\\\', '\\"', '\\$'],
                ['\\', '"', '$'],
                substr($value, 1, -1),
            );
        }

        if ($first === "'" && $last === "'" && strlen($value) >= 2) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
