<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Str;

class AutomationLogger
{
    /**
     * Record one automation run.
     *
     * @param  array{trigger: string, contact?: string|null, contact_name?: string|null, rule?: string|null, detail?: string|null}  $context
     */
    public function record(Profile $profile, string $tool, string $key, string $status, array $context): void
    {
        $profile->automationActivities()->create([
            'tool' => $tool,
            'automation_key' => $key,
            'rule_name' => $context['rule'] ?? null,
            'trigger' => $context['trigger'],
            'contact' => $context['contact'] ?? null,
            'contact_name' => $context['contact_name'] ?? null,
            'status' => $status,
            'detail' => blank($context['detail'] ?? null) ? null : Str::limit((string) $context['detail'], 480),
        ]);
    }
}
