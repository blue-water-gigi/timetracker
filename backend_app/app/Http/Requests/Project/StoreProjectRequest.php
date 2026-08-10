<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id'       => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:500'],
            'slug'               => ['prohibited'],
            'active'             => ['sometimes', 'boolean'],
            'period_start'       => ['nullable', 'required_with:period_end', 'date'],
            'period_end'         => ['nullable', 'required_with:period_start', 'date', 'after_or_equal:period_start'],
        ];
    }
}
