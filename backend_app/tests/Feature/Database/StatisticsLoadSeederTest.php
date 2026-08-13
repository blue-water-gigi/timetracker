<?php

declare(strict_types=1);

use Database\Seeders\StatisticsLoadSeeder;
use Illuminate\Support\Facades\DB;

it('refuses to run outside the dedicated load database without writing data', function () {
    $database = (string) data_get(
        DB::selectOne('SELECT current_database() AS name'),
        'name',
    );
    $usersBefore = DB::table('users')->count();

    expect($database)->toBe('time_track_test')
        ->and(fn () => (new StatisticsLoadSeeder)->run())
        ->toThrow(RuntimeException::class, 'StatisticsLoadSeeder can run only on time_track_load.')
        ->and(DB::table('users')->count())
        ->toBe($usersBefore);
});
