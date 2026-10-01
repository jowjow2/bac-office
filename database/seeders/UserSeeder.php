<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development accounts, one per portal role. Safe to re-run: an
 * account that already exists (by email) is left unchanged.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Admin User', 'email' => 'admin@gmail.com', 'password' => 'admin123', 'role' => 'admin'],
            ['name' => 'Staff User', 'email' => 'staff@gmail.com', 'password' => 'staff123', 'role' => 'staff'],
            // End-user office: files purchase requests (My purchase requests).
            ['name' => 'Municipal Engineering Office', 'email' => 'meo@sanjose.test', 'password' => 'meo12345', 'role' => 'end_user', 'office' => 'Municipal Engineering Office'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(['email' => $account['email']], [
                'name' => $account['name'],
                'password' => Hash::make($account['password']),
                'role' => $account['role'],
                'status' => 'active',
                'office' => $account['office'] ?? null,
            ]);
        }
    }
}
