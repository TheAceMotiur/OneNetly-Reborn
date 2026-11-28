<?php

namespace App\Http\Requests\ShortLink;

use App\Models\ShortLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShortLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $shortLink = $this->route('shortLink');
        $user = $this->user();

        return $user !== null
            && $shortLink instanceof ShortLink
            && $shortLink->user_id === $user->id;
    }

    /**
     * Prepare the request for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => filled($this->input('title')) ? trim((string) $this->input('title')) : null,
            'slug' => filled($this->input('slug')) ? strtolower(trim((string) $this->input('slug'))) : null,
        ]);

        foreach (ShortLink::CAMPAIGN_PARAMETERS as $parameter) {
            if (blank($this->input($parameter))) {
                $this->merge([$parameter => null]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $shortLink = $this->route('shortLink');

        return [
            'title' => ['nullable', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:64', 'alpha_dash',
                Rule::unique('short_links', 'slug')->ignore($shortLink instanceof ShortLink ? $shortLink->id : null),
            ],
            'destination' => ['required', 'string', 'max:2048', 'url:http,https'],
            'mobile_destination' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
            'remove_password' => ['sometimes', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'max_clicks' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
        ];
    }
}
