<?php

namespace App\Http\Requests\Timesheet\TimeEntry;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
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
            'timesheet_id' => ['prohibited'],
            'work_date' => ['required', 'date'],
            'description' => ['nullable', 'sometimes', 'string', 'max:500'],
            'hours' => ['required', 'decimal'],
            'is_overtime' => ['nullable', 'sometimes', 'boolean'],
        ];
    }
}
