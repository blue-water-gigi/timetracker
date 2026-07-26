<?php

declare(strict_types=1);

namespace App\Http\Requests\Timesheet;

use App\Enums\TimesheetStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimesheetRequest extends FormRequest
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
            'workspace_id' => ['prohibited'],
            'project_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'period_start' => ['bail', 'required', 'date'],
            'period_end' => ['bail', 'required', 'date', 'after_or_equal:period_start'],
            'status' => ['prohibited', Rule::enum(TimesheetStatus::class)],
            'reviewed_by_user_id' => ['prohibited'],
            'review_comment' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
        ];
    }
}
