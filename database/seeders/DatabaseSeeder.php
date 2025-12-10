<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

      $user=  User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        ApiKey::create([
            'name' => 'Default API Key',
            'key' => Str::random(40),
            'quota' => 1000,
            'used' => 0,
            'user_id' => $user->id,
            'expires_at' => now()->addYear(),
        ]);
    }
}
