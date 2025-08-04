<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@localhost.com',
            'phone' => '675066919',
            'user_type'=>0
        ]);

        $this->call([
            ImageSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            DepartementSeeder::class,
            CitySeeder::class
        ]);
    }
}
