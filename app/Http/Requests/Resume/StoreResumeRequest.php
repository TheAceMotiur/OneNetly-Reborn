<?php

namespace App\Http\Requests\Resume;

use App\Models\Resume;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResumeRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'template' => ['required', 'string', Rule::in(array_keys(Resume::TEMPLATES))],
            'accent' => ['sometimes', 'string', Rule::in(array_keys(Resume::ACCENTS))],
            'font' => ['sometimes', 'string', Rule::in(array_keys(Resume::FONTS))],
        ];
    }
}
