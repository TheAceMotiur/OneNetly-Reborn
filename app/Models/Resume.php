<?php

namespace App\Models;

use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string $template
 * @property string $accent
 * @property string $font
 * @property array<string, mixed>|null $content
 * @property bool $is_public
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['title', 'slug', 'template', 'accent', 'font', 'content', 'is_public'])]
class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    /**
     * The professional layouts a resume can use.
     *
     * @var array<string, array{name: string, description: string, style: string, ats: bool, tags: list<string>}>
     */
    public const TEMPLATES = [
        'classic' => [
            'name' => 'Classic',
            'description' => 'A timeless single-column layout with centred headings that recruiters instantly recognise.',
            'style' => 'Single column',
            'ats' => true,
            'tags' => ['Traditional', 'All industries'],
        ],
        'modern' => [
            'name' => 'Modern',
            'description' => 'A confident accent banner with clean type that still parses cleanly in tracking systems.',
            'style' => 'Single column',
            'ats' => true,
            'tags' => ['Colour banner', 'All industries'],
        ],
        'executive' => [
            'name' => 'Executive',
            'description' => 'Serif headings and a stately centred header for leadership and senior specialist roles.',
            'style' => 'Single column',
            'ats' => true,
            'tags' => ['Leadership', 'Serif'],
        ],
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'Quiet typography and generous white space so the achievements carry the page.',
            'style' => 'Single column',
            'ats' => true,
            'tags' => ['Clean', 'Creative industries'],
        ],
        'sidebar' => [
            'name' => 'Sidebar',
            'description' => 'A colour sidebar for contact details, skills and languages beside your experience.',
            'style' => 'Two column',
            'ats' => false,
            'tags' => ['Colour block', 'Design & marketing'],
        ],
        'technical' => [
            'name' => 'Technical',
            'description' => 'Compact sections with skills and projects up front, built for engineers and analysts.',
            'style' => 'Single column',
            'ats' => true,
            'tags' => ['Compact', 'Engineering'],
        ],
    ];

    /**
     * The accent colours a resume can use.
     *
     * @var array<string, string>
     */
    public const ACCENTS = [
        'indigo' => '#4f46e5',
        'emerald' => '#059669',
        'rose' => '#e11d48',
        'amber' => '#d97706',
        'sky' => '#0284c7',
        'violet' => '#7c3aed',
        'teal' => '#0d9488',
        'slate' => '#475569',
    ];

    /**
     * The typefaces a resume can use.
     *
     * @var array<string, string>
     */
    public const FONTS = [
        'sans' => 'Modern sans',
        'serif' => 'Classic serif',
    ];

    /**
     * The shape of each repeatable resume entry.
     *
     * @var array<string, array<string, string|bool>>
     */
    private const ENTRY_SHAPES = [
        'experience' => ['title' => '', 'company' => '', 'location' => '', 'start' => '', 'end' => '', 'current' => false, 'bullets' => ''],
        'education' => ['degree' => '', 'school' => '', 'location' => '', 'start' => '', 'end' => '', 'details' => ''],
        'skills' => ['group' => '', 'items' => ''],
        'projects' => ['name' => '', 'link' => '', 'description' => ''],
        'certifications' => ['name' => '', 'issuer' => '', 'year' => ''],
        'languages' => ['name' => '', 'level' => ''],
    ];

    /**
     * The user that owns the resume.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The empty sections of a new resume.
     *
     * @return array<string, string>
     */
    private static function blankBasics(): array
    {
        return [
            'name' => '',
            'headline' => '',
            'email' => '',
            'phone' => '',
            'location' => '',
            'website' => '',
            'linkedin' => '',
            'summary' => '',
        ];
    }

    /**
     * An empty resume with every section present.
     *
     * @return array<string, mixed>
     */
    public static function blankContent(): array
    {
        return [
            'basics' => self::blankBasics(),
            'experience' => [],
            'education' => [],
            'skills' => [],
            'projects' => [],
            'certifications' => [],
            'languages' => [],
        ];
    }

    /**
     * A new resume pre-filled with the details we already know about the user.
     *
     * @return array<string, mixed>
     */
    public static function starterContent(string $name, string $email): array
    {
        $content = self::blankContent();
        $content['basics'] = [
            ...self::blankBasics(),
            'name' => $name,
            'email' => $email,
        ];

        return $content;
    }

    /**
     * Demo content used to preview every template before the user picks one.
     *
     * @return array<string, mixed>
     */
    public static function sampleContent(): array
    {
        return [
            'basics' => [
                'name' => 'Alex Morgan',
                'headline' => 'Senior Product Designer',
                'email' => 'alex.morgan@example.com',
                'phone' => '+1 (415) 555-0134',
                'location' => 'Lisbon, Portugal',
                'website' => 'alexmorgan.design',
                'linkedin' => 'linkedin.com/in/alexmorgan',
                'summary' => 'Product designer with eight years of experience shipping thoughtful, accessible interfaces for fintech and marketplace teams. Comfortable leading discovery, research and design systems work end to end.',
            ],
            'experience' => [
                [
                    'title' => 'Senior Product Designer',
                    'company' => 'Northwind Labs',
                    'location' => 'Remote',
                    'start' => '2022',
                    'end' => '',
                    'current' => true,
                    'bullets' => "Led the redesign of the onboarding flow, lifting activation by 28%.\nBuilt the design system now used by 40 engineers across six product teams.\nMentored three mid-level designers through promotion.",
                ],
                [
                    'title' => 'Product Designer',
                    'company' => 'Brightline',
                    'location' => 'Berlin',
                    'start' => '2019',
                    'end' => '2022',
                    'current' => false,
                    'bullets' => "Shipped the mobile checkout used by 1.2 million customers.\nRan the first usability research programme, cutting support tickets by a third.",
                ],
            ],
            'education' => [
                [
                    'degree' => 'BSc Interaction Design',
                    'school' => 'University of the Arts London',
                    'location' => 'London, UK',
                    'start' => '2012',
                    'end' => '2015',
                    'details' => 'First class honours · Design society lead',
                ],
            ],
            'skills' => [
                ['group' => 'Design', 'items' => 'Product strategy, Interaction design, Design systems, Prototyping'],
                ['group' => 'Tools', 'items' => 'Figma, Framer, Maze, Notion'],
            ],
            'projects' => [
                [
                    'name' => 'Design Systems Playbook',
                    'link' => 'alexmorgan.design/playbook',
                    'description' => 'An open guide to scaling component libraries across product teams.',
                ],
            ],
            'certifications' => [
                [
                    'name' => 'Certified Usability Analyst',
                    'issuer' => 'Human Factors International',
                    'year' => '2021',
                ],
            ],
            'languages' => [
                ['name' => 'English', 'level' => 'Native'],
                ['name' => 'Portuguese', 'level' => 'Fluent'],
            ],
        ];
    }

    /**
     * Fill every missing section and cast stored values back to their expected types.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function normaliseContent(array $content): array
    {
        $basics = is_array($content['basics'] ?? null) ? $content['basics'] : [];

        $normalised = self::blankContent();
        $normalised['basics'] = [
            'name' => self::text($basics['name'] ?? null),
            'headline' => self::text($basics['headline'] ?? null),
            'email' => self::text($basics['email'] ?? null),
            'phone' => self::text($basics['phone'] ?? null),
            'location' => self::text($basics['location'] ?? null),
            'website' => self::text($basics['website'] ?? null),
            'linkedin' => self::text($basics['linkedin'] ?? null),
            'summary' => self::text($basics['summary'] ?? null),
        ];

        $normalised['experience'] = self::normaliseEntries($content['experience'] ?? null, self::ENTRY_SHAPES['experience']);
        $normalised['education'] = self::normaliseEntries($content['education'] ?? null, self::ENTRY_SHAPES['education']);
        $normalised['skills'] = self::normaliseEntries($content['skills'] ?? null, self::ENTRY_SHAPES['skills']);
        $normalised['projects'] = self::normaliseEntries($content['projects'] ?? null, self::ENTRY_SHAPES['projects']);
        $normalised['certifications'] = self::normaliseEntries($content['certifications'] ?? null, self::ENTRY_SHAPES['certifications']);
        $normalised['languages'] = self::normaliseEntries($content['languages'] ?? null, self::ENTRY_SHAPES['languages']);

        return $normalised;
    }

    /**
     * The stored content with every section guaranteed to exist.
     *
     * @return array<string, mixed>
     */
    public function structuredContent(): array
    {
        return self::normaliseContent(is_array($this->content) ? $this->content : []);
    }

    /**
     * The template definition for this resume.
     *
     * @return array{name: string, description: string, style: string, ats: bool, tags: list<string>}
     */
    public function templateDefinition(): array
    {
        return self::TEMPLATES[$this->template] ?? self::TEMPLATES['classic'];
    }

    /**
     * Determine whether this resume keeps a single-column layout tracking systems can read.
     */
    public function isAtsFriendly(): bool
    {
        return $this->templateDefinition()['ats'];
    }

    /**
     * The hex value of the chosen accent colour.
     */
    public function accentHex(): string
    {
        return self::ACCENTS[$this->accent] ?? self::ACCENTS['indigo'];
    }

    /**
     * How complete the resume is as a percentage of the sections recruiters expect.
     */
    public function completeness(): int
    {
        $content = $this->structuredContent();
        $basics = is_array($content['basics'] ?? null) ? $content['basics'] : [];

        $checks = [
            filled($basics['name'] ?? null),
            filled($basics['headline'] ?? null),
            filled($basics['email'] ?? null) || filled($basics['phone'] ?? null),
            filled($basics['summary'] ?? null),
            self::hasEntries($content['experience'] ?? null),
            self::hasEntries($content['education'] ?? null),
            self::hasEntries($content['skills'] ?? null),
            self::hasEntries($content['projects'] ?? null) || self::hasEntries($content['certifications'] ?? null),
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    /**
     * The public share URL, or null while the resume is still private.
     */
    public function publicUrl(): ?string
    {
        return $this->is_public ? route('resume.public', $this->slug) : null;
    }

    /**
     * The resume as the frontend expects it.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'template' => $this->template,
            'accent' => $this->accent,
            'accent_hex' => $this->accentHex(),
            'font' => $this->font,
            'is_public' => $this->is_public,
            'public_url' => $this->publicUrl(),
            'print_url' => route('resumes.print', $this),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'completeness' => $this->completeness(),
            'watermark' => ! $this->user->canRemoveBranding(),
            'content' => $this->structuredContent(),
        ];
    }

    /**
     * Build a short, unique share slug from the resume title.
     */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 45, '') ?: 'resume';
        $slug = $base;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    /**
     * Cast a stored value to trimmed text.
     */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Cast the stored entries of one section to the expected keys.
     *
     * @param  array<string, string|bool>  $shape
     * @return list<array<string, mixed>>
     */
    private static function normaliseEntries(mixed $entries, array $shape): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $rows = [];

        foreach (array_slice($entries, 0, 30) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $row = [];

            foreach ($shape as $key => $default) {
                $value = $entry[$key] ?? $default;
                $row[$key] = is_bool($default) ? (bool) $value : self::text($value);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Determine whether any entry of a section has content.
     */
    private static function hasEntries(mixed $entries): bool
    {
        if (! is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry as $value) {
                if (is_string($value) && trim($value) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
