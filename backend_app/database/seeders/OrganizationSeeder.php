<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->administrator()->create([
            'first_name' => 'James',
            'last_name'  => 'Blake',
            'email'      => 'admin@mail.com',
            'password'   => 'admin123',
        ]);

        Organization::factory()->for($admin, 'owner')->create([
            'name' => 'Google LLC',
            'slug' => 'google-llc',
        ]);

        Organization::factory()->create([
            'name' => 'Yandex LLC',
            'slug' => 'yandex-llc',
        ]);

        Organization::factory(40)->create();
    }
}
