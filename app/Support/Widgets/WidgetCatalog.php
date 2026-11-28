<?php

namespace App\Support\Widgets;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WidgetCatalog
{
    /**
     * The widget categories shown in the sidebar and gallery.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'sharing' => 'Social & sharing',
        'engagement' => 'Engagement',
        'conversion' => 'Conversion',
        'utility' => 'Site utility',
    ];

    /**
     * Every widget that can be added to a website.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'social-share' => [
                'name' => 'Social share buttons',
                'description' => 'Let visitors share the page they are reading to X, Facebook, LinkedIn, WhatsApp, Telegram, Reddit or email.',
                'category' => 'sharing',
                'icon' => 'share',
                'placement' => 'Paste where the buttons should appear',
                'fields' => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => 'Share this page', 'max' => 60, 'help' => 'Set an empty label to show only the buttons.'],
                    ['key' => 'networks', 'label' => 'Networks', 'type' => 'text', 'default' => 'x,facebook,linkedin,whatsapp', 'max' => 120, 'help' => 'Comma separated: x, facebook, linkedin, whatsapp, telegram, reddit, email.'],
                    ['key' => 'style', 'label' => 'Button style', 'type' => 'select', 'default' => 'labelled', 'options' => ['labelled' => 'Labelled pills', 'icons' => 'Icon circles']],
                    ['key' => 'theme', 'label' => 'Colour scheme', 'type' => 'select', 'default' => 'light', 'options' => ['light' => 'Light', 'dark' => 'Dark', 'brand' => 'Brand colours']],
                    ['key' => 'url', 'label' => 'Page to share', 'type' => 'text', 'default' => '', 'max' => 200, 'placeholder' => 'https://example.com/article', 'help' => 'Leave blank to share whichever page the visitor is on.'],
                    ['key' => 'text', 'label' => 'Share text', 'type' => 'text', 'default' => '', 'max' => 120, 'help' => 'Message used by X, WhatsApp and email. Leave blank to use the page title.'],
                    ['key' => 'align', 'label' => 'Alignment', 'type' => 'select', 'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Centred', 'right' => 'Right']],
                    ['key' => 'copy', 'label' => 'Add a copy link button', 'type' => 'checkbox', 'default' => false, 'help' => 'Visitors can copy the page address with one click.'],
                ],
            ],
            'adblock-detector' => [
                'name' => 'AdBlock detector',
                'description' => 'Detects an ad blocker and asks visitors politely to allow ads, with a banner, modal or toast notice.',
                'category' => 'engagement',
                'icon' => 'shield',
                'placement' => 'Paste before </body>',
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Please support this site', 'max' => 80],
                    ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'default' => 'We noticed an ad blocker is running. Ads keep our articles free — consider allowing ads on this site.', 'max' => 300],
                    ['key' => 'style', 'label' => 'Notice style', 'type' => 'select', 'default' => 'banner', 'options' => ['banner' => 'Banner', 'modal' => 'Modal', 'toast' => 'Toast']],
                    ['key' => 'accent', 'label' => 'Accent colour', 'type' => 'color', 'default' => '#4f46e5'],
                    ['key' => 'delay', 'label' => 'Check after (ms)', 'type' => 'number', 'default' => 1200, 'min' => 200, 'max' => 8000, 'help' => 'Wait a moment so the detector does not slow down the first paint.'],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'right', 'options' => ['right' => 'Bottom right', 'left' => 'Bottom left'], 'help' => 'Used by the banner and toast styles.'],
                    ['key' => 'cta_label', 'label' => 'Button text', 'type' => 'text', 'default' => '', 'max' => 40, 'help' => 'Optional action button, for example “How to disable”.'],
                    ['key' => 'cta_url', 'label' => 'Button link', 'type' => 'text', 'default' => '', 'max' => 200, 'placeholder' => '/support/ads'],
                    ['key' => 'remember', 'label' => 'Do not ask again once dismissed', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'cookie-banner' => [
                'name' => 'Cookie consent banner',
                'description' => 'A GDPR-friendly consent bar with accept and decline buttons that remembers the choice.',
                'category' => 'engagement',
                'icon' => 'cookie',
                'placement' => 'Paste before </body>',
                'fields' => [
                    ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'default' => 'We use cookies to improve your experience and analyse traffic.', 'max' => 400],
                    ['key' => 'accept_label', 'label' => 'Accept button', 'type' => 'text', 'default' => 'Accept all', 'max' => 30],
                    ['key' => 'decline_label', 'label' => 'Decline button', 'type' => 'text', 'default' => 'Decline', 'max' => 30],
                    ['key' => 'policy_label', 'label' => 'Policy link text', 'type' => 'text', 'default' => 'Cookie policy', 'max' => 40],
                    ['key' => 'policy_url', 'label' => 'Policy link', 'type' => 'text', 'default' => '', 'max' => 200, 'placeholder' => '/privacy'],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'bottom', 'options' => ['bottom' => 'Bottom bar', 'top' => 'Top bar', 'bottom-left' => 'Bottom left card']],
                    ['key' => 'accent', 'label' => 'Accent colour', 'type' => 'color', 'default' => '#4f46e5'],
                    ['key' => 'theme', 'label' => 'Colour scheme', 'type' => 'select', 'default' => 'light', 'options' => ['light' => 'Light', 'dark' => 'Dark']],
                    ['key' => 'show_decline', 'label' => 'Offer a decline button', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'expires_days', 'label' => 'Remember the choice (days)', 'type' => 'number', 'default' => 180, 'min' => 1, 'max' => 365, 'help' => 'After this many days visitors are asked again.'],
                ],
            ],
            'whatsapp-button' => [
                'name' => 'WhatsApp chat button',
                'description' => 'A floating chat button that opens WhatsApp with a pre-filled message.',
                'category' => 'conversion',
                'icon' => 'chat',
                'placement' => 'Paste before </body>',
                'fields' => [
                    ['key' => 'phone', 'label' => 'WhatsApp number', 'type' => 'text', 'default' => '', 'max' => 30, 'placeholder' => '14155550134', 'help' => 'Include the country code and no spaces.'],
                    ['key' => 'message', 'label' => 'Pre-filled message', 'type' => 'text', 'default' => 'Hi! I would like to know more.', 'max' => 200],
                    ['key' => 'label', 'label' => 'Button label', 'type' => 'text', 'default' => 'Chat with us', 'max' => 40, 'help' => 'Shown beside the button and read by screen readers.'],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'right', 'options' => ['right' => 'Bottom right', 'left' => 'Bottom left']],
                    ['key' => 'color', 'label' => 'Button colour', 'type' => 'color', 'default' => '#25d366'],
                    ['key' => 'greeting', 'label' => 'Greeting bubble', 'type' => 'text', 'default' => '', 'max' => 90, 'help' => 'Optional message shown above the button when the page loads.'],
                    ['key' => 'always_label', 'label' => 'Always show the label', 'type' => 'checkbox', 'default' => false, 'help' => 'Otherwise the label only appears on hover.'],
                ],
            ],
            'announcement-bar' => [
                'name' => 'Announcement bar',
                'description' => 'A slim promo bar for sales, launches and notices, with an optional link and dismiss button.',
                'category' => 'conversion',
                'icon' => 'megaphone',
                'placement' => 'Paste before </body>',
                'fields' => [
                    ['key' => 'message', 'label' => 'Message', 'type' => 'text', 'default' => 'Free shipping on orders over $50 — this week only.', 'max' => 160],
                    ['key' => 'link_label', 'label' => 'Link text', 'type' => 'text', 'default' => 'Shop now', 'max' => 40],
                    ['key' => 'link_url', 'label' => 'Link URL', 'type' => 'text', 'default' => '/', 'max' => 200],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'top', 'options' => ['top' => 'Top of the page', 'bottom' => 'Bottom of the page']],
                    ['key' => 'background', 'label' => 'Background', 'type' => 'color', 'default' => '#4f46e5'],
                    ['key' => 'text_color', 'label' => 'Text colour', 'type' => 'color', 'default' => '#ffffff'],
                    ['key' => 'dismissible', 'label' => 'Let visitors close the bar', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'link_new_tab', 'label' => 'Open the link in a new tab', 'type' => 'checkbox', 'default' => false],
                    ['key' => 'dismiss_days', 'label' => 'Stay closed for (days)', 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 365, 'help' => '0 keeps it closed until the visitor closes the tab.'],
                ],
            ],
            'countdown' => [
                'name' => 'Countdown timer',
                'description' => 'A launch or sale countdown that updates every second and shows a message when it ends.',
                'category' => 'conversion',
                'icon' => 'timer',
                'placement' => 'Paste where the timer should appear',
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Launching soon', 'max' => 80],
                    ['key' => 'ends_at', 'label' => 'Count down to', 'type' => 'text', 'default' => now()->addDays(14)->startOfHour()->format('Y-m-d\TH:i'), 'max' => 40, 'placeholder' => '2026-12-31T23:59', 'help' => 'Local date and time in 24-hour form, for example 2026-12-31T23:59.'],
                    ['key' => 'background', 'label' => 'Background', 'type' => 'color', 'default' => '#0f172a'],
                    ['key' => 'text_color', 'label' => 'Text colour', 'type' => 'color', 'default' => '#ffffff'],
                    ['key' => 'accent', 'label' => 'Digit colour', 'type' => 'color', 'default' => '#818cf8'],
                    ['key' => 'expired_text', 'label' => 'Finished message', 'type' => 'text', 'default' => 'The offer has ended.', 'max' => 80],
                    ['key' => 'show_labels', 'label' => 'Show the Days / Hours labels', 'type' => 'checkbox', 'default' => true],
                    ['key' => 'hide_when_done', 'label' => 'Hide the timer when it ends', 'type' => 'checkbox', 'default' => false, 'help' => 'Otherwise the finished message stays visible.'],
                ],
            ],
            'back-to-top' => [
                'name' => 'Back to top button',
                'description' => 'A floating button that appears after scrolling and glides visitors back to the top.',
                'category' => 'utility',
                'icon' => 'arrow-up',
                'placement' => 'Paste before </body>',
                'fields' => [
                    ['key' => 'label', 'label' => 'Button label', 'type' => 'text', 'default' => '', 'max' => 40, 'help' => 'Optional text beside the arrow. Leave blank for an icon-only button.'],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'right', 'options' => ['right' => 'Bottom right', 'left' => 'Bottom left']],
                    ['key' => 'offset', 'label' => 'Appear after scrolling (px)', 'type' => 'number', 'default' => 400, 'min' => 100, 'max' => 5000],
                    ['key' => 'background', 'label' => 'Background', 'type' => 'color', 'default' => '#0f172a'],
                    ['key' => 'shape', 'label' => 'Button shape', 'type' => 'select', 'default' => 'pill', 'options' => ['pill' => 'Pill', 'circle' => 'Circle']],
                    ['key' => 'smooth', 'label' => 'Scroll smoothly', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'scroll-progress' => [
                'name' => 'Reading progress bar',
                'description' => 'A thin bar that fills as visitors scroll, so they can see how much of the page is left.',
                'category' => 'utility',
                'icon' => 'progress',
                'placement' => 'Paste before </body>',
                'fields' => [
                    ['key' => 'color', 'label' => 'Bar colour', 'type' => 'color', 'default' => '#4f46e5'],
                    ['key' => 'height', 'label' => 'Bar height (px)', 'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 12],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'select', 'default' => 'top', 'options' => ['top' => 'Top of the page', 'bottom' => 'Bottom of the page']],
                    ['key' => 'track_color', 'label' => 'Track colour', 'type' => 'color', 'default' => '#e5e7eb'],
                    ['key' => 'align', 'label' => 'Width', 'type' => 'select', 'default' => 'full', 'options' => ['full' => 'Full width', 'center' => 'Centred bar']],
                    ['key' => 'rounded', 'label' => 'Rounded ends', 'type' => 'checkbox', 'default' => false],
                ],
            ],
        ];
    }

    /**
     * Determine whether a widget type exists.
     */
    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * The definition of one widget, falling back to the first widget.
     *
     * @return array<string, mixed>
     */
    public static function definition(string $type): array
    {
        return self::all()[$type] ?? self::all()['announcement-bar'];
    }

    /**
     * The editable fields of a widget.
     *
     * @return list<array<string, mixed>>
     */
    public static function fields(string $type): array
    {
        $fields = self::definition($type)['fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        return [
            ...array_values(array_filter($fields, is_array(...))),
            ...self::behaviourFields(),
        ];
    }

    /**
     * Delivery controls shared by every widget: who sees it, when it runs and
     * how it enters the page. They are optional, so existing saved widgets
     * keep their defaults until the owner changes them.
     *
     * @return list<array<string, mixed>>
     */
    public static function behaviourFields(): array
    {
        $group = 'Behaviour & targeting';

        return [
            ['key' => 'behaviour_paused', 'label' => 'Pause this widget', 'type' => 'checkbox', 'default' => false, 'optional' => true, 'group' => $group, 'help' => 'Visitors stop seeing the widget without deleting it or touching the embed code.'],
            ['key' => 'behaviour_device', 'label' => 'Show on', 'type' => 'select', 'default' => 'all', 'optional' => true, 'group' => $group, 'options' => ['all' => 'All devices', 'desktop' => 'Desktop only', 'mobile' => 'Mobile only']],
            ['key' => 'behaviour_pages', 'label' => 'Pages', 'type' => 'select', 'default' => 'everywhere', 'optional' => true, 'group' => $group, 'options' => ['everywhere' => 'Every page', 'include' => 'Only the pages listed below', 'exclude' => 'Every page except those listed below']],
            ['key' => 'behaviour_paths', 'label' => 'Page paths', 'type' => 'text', 'default' => '', 'max' => 300, 'optional' => true, 'group' => $group, 'placeholder' => '/pricing, /blog/*', 'help' => 'Comma separated paths. * matches anything, for example /blog/* covers every article.'],
            ['key' => 'behaviour_delay', 'label' => 'Wait before showing (ms)', 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 15000, 'optional' => true, 'group' => $group, 'help' => 'Let the page settle first. 0 shows the widget immediately.'],
            ['key' => 'behaviour_frequency', 'label' => 'Repeat visits', 'type' => 'select', 'default' => 'always', 'optional' => true, 'group' => $group, 'options' => ['always' => 'Every visit', 'session' => 'Once per visit', 'day' => 'Once per day', 'visitor' => 'Once per visitor']],
            ['key' => 'behaviour_from', 'label' => 'Start showing', 'type' => 'text', 'default' => '', 'max' => 20, 'optional' => true, 'group' => $group, 'placeholder' => '2026-12-01T09:00', 'help' => 'Optional local date and time. Leave blank to show it straight away.'],
            ['key' => 'behaviour_until', 'label' => 'Stop showing', 'type' => 'text', 'default' => '', 'max' => 20, 'optional' => true, 'group' => $group, 'placeholder' => '2026-12-31T23:59', 'help' => 'Optional. The widget hides itself after this moment.'],
            ['key' => 'behaviour_animation', 'label' => 'Entrance animation', 'type' => 'select', 'default' => 'none', 'optional' => true, 'group' => $group, 'options' => ['none' => 'None', 'fade' => 'Fade in', 'slide' => 'Slide up', 'scale' => 'Scale in'], 'help' => 'Skipped automatically for visitors who ask for reduced motion.'],
        ];
    }

    /**
     * The catalogue grouped into the shape the frontend consumes.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(?string $category = null): array
    {
        $catalog = [];

        foreach (self::all() as $key => $definition) {
            $definitionCategory = (string) ($definition['category'] ?? '');

            if ($category !== null && $definitionCategory !== $category) {
                continue;
            }

            $catalog[] = [
                'key' => $key,
                ...$definition,
                'category_label' => self::CATEGORIES[$definitionCategory] ?? ucfirst($definitionCategory),
            ];
        }

        return $catalog;
    }

    /**
     * The default configuration of a widget.
     *
     * @return array<string, mixed>
     */
    public static function defaults(string $type): array
    {
        return self::normalise($type, []);
    }

    /**
     * Cast a stored configuration to the expected fields and types.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalise(string $type, array $config): array
    {
        $normalised = [];

        foreach (self::fields($type) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $value = $config[$key] ?? ($field['default'] ?? '');
            $fieldType = (string) ($field['type'] ?? 'text');

            if ($fieldType === 'checkbox') {
                $normalised[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

                continue;
            }

            if ($fieldType === 'number') {
                $number = is_numeric($value) ? (int) $value : (int) ($field['default'] ?? 0);
                $min = (int) ($field['min'] ?? 0);
                $max = (int) ($field['max'] ?? 100000);
                $normalised[$key] = max($min, min($max, $max >= $min ? $number : $min));

                continue;
            }

            if ($fieldType === 'color') {
                $candidate = is_string($value) ? trim($value) : '';
                $normalised[$key] = preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) === 1
                    ? $candidate
                    : (string) ($field['default'] ?? '#4f46e5');

                continue;
            }

            if ($fieldType === 'select') {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                $candidate = is_scalar($value) ? (string) $value : '';
                $normalised[$key] = array_key_exists($candidate, $options)
                    ? $candidate
                    : (string) ($field['default'] ?? (array_key_first($options) ?? ''));

                continue;
            }

            $max = (int) ($field['max'] ?? 500);
            $normalised[$key] = Str::limit(is_scalar($value) ? trim((string) $value) : '', $max, '');
        }

        return $normalised;
    }

    /**
     * The validation rules for the configurable fields of a widget.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $type): array
    {
        $rules = [];

        foreach (self::fields($type) as $field) {
            $key = (string) ($field['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $rules['config.'.$key] = self::fieldRules($field);
        }

        return $rules;
    }

    /**
     * Build the validation rules for one field.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private static function fieldRules(array $field): array
    {
        $fieldType = (string) ($field['type'] ?? 'text');

        $rules = match ($fieldType) {
            'checkbox' => ['boolean'],
            'number' => ['required', 'integer', 'min:'.(int) ($field['min'] ?? 0), 'max:'.(int) ($field['max'] ?? 100000)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'select' => ['required', 'string', Rule::in(array_keys(is_array($field['options'] ?? null) ? $field['options'] : []))],
            default => ['nullable', 'string', 'max:'.(int) ($field['max'] ?? 500)],
        };

        if (($field['optional'] ?? false) === true) {
            $rules = array_values(array_filter(
                $rules,
                static fn (mixed $rule): bool => $rule !== 'required',
            ));
            array_unshift($rules, 'sometimes', 'nullable');
        }

        return $rules;
    }
}
