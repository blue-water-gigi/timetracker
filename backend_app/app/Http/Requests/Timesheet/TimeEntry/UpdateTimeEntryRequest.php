<?php

declare(strict_types=1);

namespace App\Http\Requests\Timesheet\TimeEntry;

use App\Services\Timesheet\Data\UpdateTimeEntryData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
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
            'work_date' => ['bail', 'sometimes', 'date'],
            'description' => ['nullable', 'sometimes', 'string', 'max:500'],
            'hours' => ['sometimes', 'decimal:0,2', 'gte:0', 'lte:24'],
            'is_overtime' => ['sometimes', 'boolean'],
        ];
    }

    //    public function after(): array
    //    {
    //        return [
    //            function (Validator $validator): void {
    //                if ($validator->errors()->has('work_date')) {
    //                    return;
    //                }
    //
    //                $timeEntry = $this->route('entry');
    //                $timesheet = $this->route('timesheet');
    //                $workDate = CarbonImmutable::parse($this->input('work_date', $timeEntry->work_date->toDateString()));
    //
    //                if ($workDate->lt($timesheet->period_start) || $workDate->gt($timesheet->period_end)) {
    //                    $validator->errors()->add(
    //                        'work_date',
    //                        sprintf('The work date must be between %s and %s.',
    //                            $timesheet->period_start,
    //                            $timesheet->period_end));
    //                }
    //            },
    //        ];
    //    }

    public function entryData(): UpdateTimeEntryData
    {
        return UpdateTimeEntryData::fromValidated(
            $this->safe()->except('timesheet_id'),
        );
    }
}
