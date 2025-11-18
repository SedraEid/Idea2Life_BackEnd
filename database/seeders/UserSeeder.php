<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
  public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'user_type' => 'admin',
            ]
        );
        Wallet::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'user_type' => 'admin',
                'balance' => 0.00,
                'status' => 'active',
            ]
        );
    }
}
