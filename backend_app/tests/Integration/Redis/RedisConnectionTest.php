<?php

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

uses(TestCase::class);

it('connects to the testing redis DB', function () {
    $result = Redis::connection('testing')->ping();

    expect($result)->toBeTrue();
})->group('redis');
