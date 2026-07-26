<?php

declare(strict_types=1);

namespace App\Http\Requests\Timesheet;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTimesheetRequest extends FormRequest
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
            'period_start' => ['bail', 'sometimes', 'date'],
            'period_end' => ['bail', 'sometimes', 'date'],
            'status' => ['prohibited'],
            'reviewed_by_user_id' => ['prohibited'],
            'review_comment' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'reviewed_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['period_start', 'period_end'])) {
                    return;
                }

                $timesheet = $this->route('timesheet');

                $periodStart = CarbonImmutable::parse(
                    $this->input('period_start', $timesheet->period_start->toDateString())
                );

                $periodEnd = CarbonImmutable::parse(
                    $this->input('period_end', $timesheet->period_end->toDateString())
                );

                if ($periodStart->isAfter($periodEnd)) {
                    $validator->errors()->add(
                        $this->has('period_end') ? 'period_end' : 'period_start',
                        'The period start must not be after the period end.'
                    );

                    return;
                }

                if (! $this->hasAny(['period_start', 'period_end'])) {
                    return;
                }

                $hasOutOfRangeEntries = $timesheet->entries()
                    ->where(function ($query) use ($periodStart, $periodEnd) {
                        $query->where('work_date', '<', $periodStart->toDateString())
                            ->orWhere('work_date', '>', $periodEnd->toDateString());
                    })
                    ->exists();

                if ($hasOutOfRangeEntries) {
                    $validator->errors()->add(
                        $this->has('period_end') ? 'period_end' : 'period_start',
                        'Cannot change the period: one or more time entries fall outside the new range.'
                    );
                }
            },
        ];
    }
}
