<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $tool
 * @property string $automation_key
 * @property string|null $rule_name
 * @property string $trigger
 * @property string|null $contact
 * @property string|null $contact_name
 * @property string $status
 * @property string|null $detail
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['profile_id', 'tool', 'automation_key', 'rule_name', 'trigger', 'contact', 'contact_name', 'status', 'detail'])]
class AutomationActivity extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'automation_activities';

    /**
     * The page this automation run belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The human readable automation name, including keyword rules.
     */
    public function label(): string
    {
        $defaults = match ($this->tool) {
            'whatsapp' => WhatsAppAutomation::DEFAULTS,
            'facebook' => FacebookAutomation::DEFAULTS,
            'instagram' => InstagramAutomation::DEFAULTS,
            'twitter' => TwitterAutomation::DEFAULTS,
            'email' => EmailAutomation::DEFAULTS,
            default => [],
        };

        $label = data_get($defaults, $this->automation_key.'.label');

        return is_string($label) ? $label : __('Keyword reply');
    }

    /**
     * The latest runs for one tool, ready for the activity panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function panelFor(Profile $profile, string $tool, int $limit = 20): array
    {
        return $profile->automationActivities()
            ->where('tool', $tool)
            ->limit($limit)
            ->get()
            ->map(fn (self $activity): array => [
                'id' => $activity->id,
                'label' => $activity->label(),
                'status' => $activity->status,
                'trigger' => $activity->trigger,
                'contact' => $activity->contact_name ?: $activity->contact,
                'rule' => $activity->rule_name,
                'detail' => $activity->detail,
                'created_human' => $activity->created_at?->diffForHumans(),
            ])
            ->all();
    }
}
