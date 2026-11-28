<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SettingsService
{
    private const CACHE_KEY = 'app.settings';

    /**
     * The settings resolved for the current process.
     *
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * Every setting merged with its configured default.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $values = $this->defaults();

        try {
            $stored = Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            // The settings table does not exist yet; retry on the next call.
            return $values;
        }

        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $values[$key] = $this->cast($key, $value);
        }

        return $this->values = $values;
    }

    /**
     * Read a single setting.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Read a setting as a boolean.
     */
    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    /**
     * Persist a single setting and clear the cache.
     */
    public function set(string $key, mixed $value): void
    {
        if (! array_key_exists($key, (array) config('settings.settings', []))) {
            return;
        }

        $definition = (array) config("settings.settings.{$key}");
        $type = (string) ($definition['type'] ?? 'string');

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serialize($type, $value),
                'type' => $type,
                'group' => (string) ($definition['group'] ?? 'general'),
            ],
        );

        $this->forget();
    }

    /**
     * Persist multiple settings at once.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = (array) config("settings.settings.{$key}");

            if ($definition === []) {
                continue;
            }

            $type = (string) ($definition['type'] ?? 'string');

            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->serialize($type, $value),
                    'type' => $type,
                    'group' => (string) ($definition['group'] ?? 'general'),
                ],
            );
        }

        $this->forget();
    }

    /**
     * Clear the cached settings.
     */
    public function forget(): void
    {
        $this->values = null;

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The configured default value for every setting.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ((array) config('settings.settings', []) as $key => $definition) {
            $defaults[$key] = $definition['default'] ?? null;
        }

        return $defaults;
    }

    /**
     * Cast a stored value using its configured type.
     */
    private function cast(string $key, mixed $value): mixed
    {
        return match (config("settings.settings.{$key}.type", 'string')) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => $value === null ? null : (int) $value,
            'secret' => $this->decrypt($value),
            default => $value,
        };
    }

    /**
     * Serialize a value for storage.
     */
    private function serialize(string $type, mixed $value): ?string
    {
        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }

        if ($type === 'secret') {
            return filled($value) ? Crypt::encryptString((string) $value) : null;
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * Decrypt a stored secret, tolerating values encrypted with an old key.
     */
    private function decrypt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
