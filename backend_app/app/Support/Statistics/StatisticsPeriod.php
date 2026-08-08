<?php

declare(strict_types=1);

namespace App\Support\Statistics;

use App\Enums\StatisticsGranularity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/**
 * DTO
 */
final readonly class StatisticsPeriod implements Arrayable
{
    public const int MAX_DAYS = 366;

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public StatisticsGranularity $granularity,
    ) {}

    /**
     * @param array{
     *     from?: string, to?: string, granularity?:string
     * } $attributes
     */
    public static function fromValidated(array $attributes, ?CarbonImmutable $today = null): self
    {
        // we need from and to or nothing
        $hasTo = isset($attributes['to']);
        $hasFrom = isset($attributes['from']);

        if ($hasTo !== $hasFrom) {
            throw new InvalidArgumentException("'From' and 'to' dates are required.");
        }

        if ($hasFrom) {
            $to = CarbonImmutable::parse($attributes['to'])->startOfDay();
            $from = CarbonImmutable::parse($attributes['from'])->startOfDay();
        } else {
            $baseDate = $today instanceof CarbonImmutable ? $today->startOfDay() : CarbonImmutable::today();
            $to = $baseDate->startOfDay();
            // subtract month by default
            $from = $to->subDays(29);
        }

        if ($from->isAfter($to)) {
            throw new InvalidArgumentException("'From' date must not be after 'to'.");
        }

        $days = (int) $from->diffInDays($to) + 1;

        if ($days > self::MAX_DAYS) {
            throw new InvalidArgumentException('Statistics period must not exceed 366 days.');
        }

        $granularity = isset($attributes['granularity'])
            ? StatisticsGranularity::from($attributes['granularity'])
            : StatisticsGranularity::defaultForDays($days);

        return new self($from, $to, $granularity);
    }

    /**
     * Calculate buckets depending on granularity
     *
     * @return array<CarbonImmutable>
     */
    public function bucketStarts(): array
    {
        $buckets = [];

        $bucket = $this->granularity->bucketStart($this->from);

        while ($bucket->lessThanOrEqualTo($this->to)) {
            $buckets[] = $bucket;
            $bucket = $this->granularity->nextBucket($bucket);
        }

        return $buckets;
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * 'From' date indicating previous period
     */
    public function previousFrom(): CarbonImmutable
    {
        return $this->from->subDays($this->days());
    }

    /**
     * 'To' date indicating previous period
     */
    public function previousTo(): CarbonImmutable
    {
        return $this->from->subDay();
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'granularity' => $this->granularity->value,
        ];
    }
}
