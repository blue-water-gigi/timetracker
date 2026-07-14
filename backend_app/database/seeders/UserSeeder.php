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
        User::factory()->administrator()->create([
            'first_name' => 'James',
            'last_name' => 'Blake',
            'email' => 'admin@mail.com',
            'password' => 'admin123',
        ]);

        $workspace = Workspace::query()->firstOrFail();

        User::factory(30)->forWorkspace($workspace)->create();
    }
}
