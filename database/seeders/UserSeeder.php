<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            User::create([
        'name' => 'Admin User',
        'email' => 'admin@gmail.com',
        'password' => Hash::make('admin123'),
        'role' => 'admin',
    ]);

    User::create([
        'name' => 'Staff User',
        'email' => 'staff@gmail.com',
        'password' => Hash::make('staff123'),
        'role' => 'staff',
    ]);
    }
}
