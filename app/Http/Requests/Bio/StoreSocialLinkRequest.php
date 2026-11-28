<?php

namespace App\Http\Requests\Bio;

use App\Models\SocialLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialLinkRequest extends FormRequest
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
            'platform' => ['required', 'string', Rule::in(array_keys(SocialLink::PLATFORMS))],
            'url' => $this->input('platform') === 'email'
                ? ['required', 'string', 'email', 'max:255']
                : ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * Get the custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'url' => $this->input('platform') === 'email' ? __('email address') : __('link'),
        ];
    }
}
