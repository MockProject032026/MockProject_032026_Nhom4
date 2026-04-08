<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'full_name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'password_hash' => \Illuminate\Support\Facades\Hash::make('12345678'),
            'id_role' => 1, // Role Admin
            'status' => 'active',
        ]);
    }
}
