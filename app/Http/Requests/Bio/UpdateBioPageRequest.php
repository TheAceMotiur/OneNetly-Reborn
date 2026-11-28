<?php

namespace App\Http\Requests\Bio;

use App\Models\Profile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBioPageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'alpha_dash',
                Rule::unique(Profile::class, 'username')->ignore($this->user()?->profile?->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array($value, Profile::RESERVED_USERNAMES, true)) {
                        $fail(__('That username is reserved. Please choose another one.'));
                    }
                },
            ],
            'custom_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!-)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/',
                Rule::unique(Profile::class, 'custom_domain')->ignore($this->user()?->profile?->id),
            ],
            'display_name' => ['nullable', 'string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:280'],
            'page_password' => ['nullable', 'string', 'min:6', 'max:72'],
            'remove_page_password' => ['nullable', 'boolean'],
            'theme' => ['required', 'string', Rule::in(Profile::THEMES)],
            'template' => ['required', 'string', Rule::in(Profile::TEMPLATES)],
            'business_type' => ['sometimes', 'string', Rule::in(array_keys(Profile::BUSINESS_TYPES))],
            'company' => ['nullable', 'string', 'max:80'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'contact_website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_published' => ['required', 'boolean'],
            'show_contact_form' => ['sometimes', 'boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
}
