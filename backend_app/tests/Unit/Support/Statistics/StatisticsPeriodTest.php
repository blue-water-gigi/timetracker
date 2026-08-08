<?php

declare(strict_types=1);

use App\Enums\StatisticsGranularity;
use App\Support\Statistics\StatisticsPeriod;
use Carbon\CarbonImmutable;

it('uses an inclusive default period of 30 days', function () {
    $period = StatisticsPeriod::fromValidated([], CarbonImmutable::parse('2026-06-06'));

    expect($period->toArray())->toBe([
        'from' => '2026-05-08',
        'to' => '2026-06-06',
        'granularity' => 'day',
    ])->and($period->days())->toBe(30);
});

it('calculates equaly sized previous period', function () {
    $period = StatisticsPeriod::fromValidated([
        'from' => '2026-07-01',
        'to' => '2026-07-31',
        'granularity' => StatisticsGranularity::WEEK->value,
    ]);

    expect($period->previousFrom()->toDateString())->toBe('2026-05-31')
        ->and($period->previousTo()->toDateString())->toBe('2026-06-30');
});

it('builds continuous Monday based weekly buckets', function () {
    $period = StatisticsPeriod::fromValidated([
        'from' => '2026-07-01',
        'to' => '2026-07-31',
        'granularity' => StatisticsGranularity::WEEK->value,
    ]);

    $buckets = array_map(fn (CarbonImmutable $date) => $date->toDateString(), $period->bucketStarts());

    expect($buckets)->toBe([
        // starts from Monday
        '2026-06-29',
        '2026-07-06',
        '2026-07-13',
        '2026-07-20',
        '2026-07-27',
    ]);
});

it('selects a safe default granularity for the period length', function (
    string $from,
    string $to,
    StatisticsGranularity $expected,
) {
    $period = StatisticsPeriod::fromValidated([
        'from' => $from,
        'to' => $to,
    ]);

    expect($period->granularity)->toBe($expected);
})->with([
    '31 days' => ['2026-08-01', '2026-08-31', StatisticsGranularity::DAY],
    '32 days' => ['2026-07-31', '2026-08-31', StatisticsGranularity::WEEK],
    '181 days' => ['2026-03-04', '2026-08-31', StatisticsGranularity::MONTH],
]);

it('rejects a period longer than 366 days', function (): void {
    expect(fn (): StatisticsPeriod => StatisticsPeriod::fromValidated([
        'from' => '2025-08-07',
        'to' => '2026-08-08',
    ]))->toThrow(InvalidArgumentException::class);
});
