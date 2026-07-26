<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::query()->each(
            fn (Workspace $workspace) => User::factory(4)->forWorkspace($workspace)->create()
        );
    }
}
