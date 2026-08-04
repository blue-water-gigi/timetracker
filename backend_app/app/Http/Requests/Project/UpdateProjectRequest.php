<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
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
        $project = $this->route('project');

        return [
            'workspace_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'sometimes', 'string', 'max:500'],
            'slug' => ['prohibited'],
            'active' => ['sometimes', 'boolean'],
            'period_start' => ['nullable', 'sometimes', 'date'],
            'period_end' => ['nullable', 'sometimes', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['period_start', 'period_end'])) {
                    return;
                }

                $attributes = $this->all();
                $hasPeriodStart = array_key_exists('period_start', $attributes);
                $hasPeriodEnd = array_key_exists('period_end', $attributes);

                if (!$hasPeriodStart && !$hasPeriodEnd) {
                    return;
                }

                $project = $this->route('project');

                if (!$project instanceof Project) {
                    return;
                }

                $periodStart = $hasPeriodStart
                    ? $attributes['period_start']
                    : $project->period_start?->toDateString();
                $periodEnd = $hasPeriodEnd
                    ? $attributes['period_end']
                    : $project->period_end?->toDateString();

                if (($periodStart === null) !== ($periodEnd === null)) {
                    $validator->errors()->add(
                        $hasPeriodEnd ? 'period_end' : 'period_start',
                        'The project period start and end must both be set or both be null.',
                    );

                    return;
                }

                if ($periodStart === null) {
                    return;
                }

                if (CarbonImmutable::parse($periodStart)->isAfter(CarbonImmutable::parse($periodEnd))) {
                    $validator->errors()->add(
                        $hasPeriodEnd ? 'period_end' : 'period_start',
                        'The period start must not be after the period end.',
                    );
                }
            },
        ];
    }
}
