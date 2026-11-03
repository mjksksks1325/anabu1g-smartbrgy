<?php

namespace App\Http\Requests\Admin;

use App\Models\Purok;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePurokRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Purok::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                if (Purok::query()->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])->exists()) {
                    $fail(__('A purok with this name already exists.'));
                }
            }],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name')))]);
    }
}
