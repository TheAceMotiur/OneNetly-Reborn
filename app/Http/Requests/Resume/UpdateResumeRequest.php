<?php

namespace App\Http\Requests\Resume;

use App\Models\Resume;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResumeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $resume = $this->route('resume');

        return $resume instanceof Resume && $this->user()?->id === $resume->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'template' => ['required', 'string', Rule::in(array_keys(Resume::TEMPLATES))],
            'accent' => ['required', 'string', Rule::in(array_keys(Resume::ACCENTS))],
            'font' => ['required', 'string', Rule::in(array_keys(Resume::FONTS))],

            'content' => ['required', 'array'],
            'content.basics' => ['required', 'array'],
            'content.basics.name' => ['nullable', 'string', 'max:120'],
            'content.basics.headline' => ['nullable', 'string', 'max:160'],
            'content.basics.email' => ['nullable', 'string', 'max:160'],
            'content.basics.phone' => ['nullable', 'string', 'max:40'],
            'content.basics.location' => ['nullable', 'string', 'max:120'],
            'content.basics.website' => ['nullable', 'string', 'max:200'],
            'content.basics.linkedin' => ['nullable', 'string', 'max:200'],
            'content.basics.summary' => ['nullable', 'string', 'max:2000'],

            'content.experience' => ['sometimes', 'array', 'max:20'],
            'content.experience.*' => ['array'],
            'content.experience.*.title' => ['nullable', 'string', 'max:120'],
            'content.experience.*.company' => ['nullable', 'string', 'max:120'],
            'content.experience.*.location' => ['nullable', 'string', 'max:120'],
            'content.experience.*.start' => ['nullable', 'string', 'max:40'],
            'content.experience.*.end' => ['nullable', 'string', 'max:40'],
            'content.experience.*.current' => ['sometimes', 'boolean'],
            'content.experience.*.bullets' => ['nullable', 'string', 'max:4000'],

            'content.education' => ['sometimes', 'array', 'max:10'],
            'content.education.*' => ['array'],
            'content.education.*.degree' => ['nullable', 'string', 'max:160'],
            'content.education.*.school' => ['nullable', 'string', 'max:160'],
            'content.education.*.location' => ['nullable', 'string', 'max:120'],
            'content.education.*.start' => ['nullable', 'string', 'max:40'],
            'content.education.*.end' => ['nullable', 'string', 'max:40'],
            'content.education.*.details' => ['nullable', 'string', 'max:1000'],

            'content.skills' => ['sometimes', 'array', 'max:12'],
            'content.skills.*' => ['array'],
            'content.skills.*.group' => ['nullable', 'string', 'max:60'],
            'content.skills.*.items' => ['nullable', 'string', 'max:500'],

            'content.projects' => ['sometimes', 'array', 'max:12'],
            'content.projects.*' => ['array'],
            'content.projects.*.name' => ['nullable', 'string', 'max:160'],
            'content.projects.*.link' => ['nullable', 'string', 'max:200'],
            'content.projects.*.description' => ['nullable', 'string', 'max:1000'],

            'content.certifications' => ['sometimes', 'array', 'max:12'],
            'content.certifications.*' => ['array'],
            'content.certifications.*.name' => ['nullable', 'string', 'max:160'],
            'content.certifications.*.issuer' => ['nullable', 'string', 'max:160'],
            'content.certifications.*.year' => ['nullable', 'string', 'max:20'],

            'content.languages' => ['sometimes', 'array', 'max:10'],
            'content.languages.*' => ['array'],
            'content.languages.*.name' => ['nullable', 'string', 'max:80'],
            'content.languages.*.level' => ['nullable', 'string', 'max:60'],
        ];
    }
}
