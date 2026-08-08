<?php

use App\Http\Requests\Statistics\ShowStatisticsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/_tests/statistics-period', function (
        ShowStatisticsRequest $request,
    ): JsonResponse {
        $period = $request->period();

        return response()->json([
            'period' => $period->toArray(),
            'previous' => [
                'from' => $period->previousFrom()->toDateString(),
                'to' => $period->previousTo()->toDateString(),
            ],
        ]);
    });
});

it('accepts valid statistics filters', function (): void {
    $this->getJson('/_tests/statistics-period?from=2026-08-01&to=2026-08-31&granularity=week')
        ->assertOk()
        ->assertJsonPath('period.from', '2026-08-01')
        ->assertJsonPath('period.to', '2026-08-31')
        ->assertJsonPath('period.granularity', 'week')
        ->assertJsonPath('previous.from', '2026-07-01')
        ->assertJsonPath('previous.to', '2026-07-31');
});

it('rejects an incomplete date pair', function (): void {
    $this->getJson('/_tests/statistics-period?from=2026-08-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('rejects an unknown granularity', function (): void {
    $this->getJson('/_tests/statistics-period?granularity=year')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('granularity');
});

it('rejects a period longer than 366 days', function (): void {
    $this->getJson('/_tests/statistics-period?from=2025-08-07&to=2026-08-08')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});
