<?php

declare(strict_types=1);

namespace App\Http\Requests\Statistics;

use App\Enums\StatisticsGranularity;
use App\Support\Statistics\StatisticsPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShowStatisticsRequest extends FormRequest
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
            'from'        => ['bail', 'required_with:to', 'date_format:Y-m-d'],
            'to'          => ['bail', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'granularity' => ['sometimes', Rule::enum(StatisticsGranularity::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()
                    || $this->isNotFilled('from')
                    || $this->isNotFilled('to')) {
                    return;
                }

                $from = CarbonImmutable::parse($this->input('from'))->startOfDay();
                $to   = CarbonImmutable::parse($this->input('to'))->startOfDay();
                $days = (int) $from->diffInDays($to) + 1;

                if ($days > StatisticsPeriod::MAX_DAYS) {
                    $validator->errors()->add('to', 'The period may not exceed 366 days.');
                }
            },
        ];
    }

    public function period(): StatisticsPeriod
    {
        return StatisticsPeriod::fromValidated($this->safe(['from', 'to', 'granularity']));
    }
}
