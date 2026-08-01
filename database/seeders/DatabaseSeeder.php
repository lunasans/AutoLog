<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Deliberately without a factory: Faker lives in require-dev and is missing
     * on a server installed with `composer install --no-dev`.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@autolog.pro'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );
    }
}
