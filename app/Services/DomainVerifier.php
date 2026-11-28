<?php

namespace App\Services;

use App\Models\Profile;

class DomainVerifier
{
    /**
     * The TXT record prefix a domain owner adds to verify control.
     */
    public const RECORD_PREFIX = 'onenetly-verification=';

    /**
     * Check the domain's TXT records and store the verification result.
     */
    public function verify(Profile $profile): bool
    {
        $verified = filled($profile->custom_domain)
            && filled($profile->custom_domain_token)
            && $this->hasToken((string) $profile->custom_domain, (string) $profile->custom_domain_token);

        $profile->forceFill([
            'custom_domain_verified_at' => $verified ? now() : null,
        ])->save();

        return $verified;
    }

    /**
     * The TXT record value the owner should publish.
     */
    public function recordValue(Profile $profile): ?string
    {
        return filled($profile->custom_domain_token)
            ? self::RECORD_PREFIX.$profile->custom_domain_token
            : null;
    }

    /**
     * Determine whether the domain publishes the verification token.
     */
    private function hasToken(string $domain, string $token): bool
    {
        $records = @dns_get_record($domain, DNS_TXT);

        if (! is_array($records)) {
            return false;
        }

        $expected = strtolower(self::RECORD_PREFIX.$token);

        foreach ($records as $record) {
            $entries = $record['entries'] ?? [];

            $values = array_filter([
                $record['txt'] ?? null,
                ...(is_array($entries) ? $entries : []),
            ]);

            foreach ($values as $value) {
                if (is_string($value) && str_contains(strtolower($value), $expected)) {
                    return true;
                }
            }
        }

        return false;
    }
}
