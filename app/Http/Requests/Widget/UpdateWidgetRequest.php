<?php

namespace App\Http\Requests\Widget;

use App\Models\Widget;
use App\Support\Widgets\WidgetCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWidgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $widget = $this->route('widget');

        return $widget instanceof Widget && $this->user()?->id === $widget->user_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $widget = $this->route('widget');

        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'config' => ['required', 'array'],
        ];

        if ($widget instanceof Widget) {
            $rules = array_merge($rules, WidgetCatalog::rules($widget->type));
        }

        return $rules;
    }
}
