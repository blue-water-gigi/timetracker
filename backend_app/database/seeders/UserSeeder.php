<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
//            'workspace_id' => 1,
            'nickname' => 'admin',
            'first_name' => 'James',
            'last_name' => 'Blake',
            'role' => Role::ADMINISTRATOR,
            'email' => 'admin@mail.com',
            'password' => 'admin123',
        ]);

        User::factory(300)->create();
    }
}
