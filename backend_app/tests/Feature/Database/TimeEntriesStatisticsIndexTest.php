<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('uses a covering PostgreSQL index for statistics time entries', function () {
    $definition = data_get(
        DB::selectOne(
            "SELECT indexdef
             FROM pg_indexes
             WHERE tablename = 'time_entries'
               AND indexname = 'time_entries_timesheet_id_work_date_index'"
        ),
        'indexdef',
    );

    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and($definition)->toBeString()
        ->toContain('INCLUDE (hours, is_overtime)');
});
