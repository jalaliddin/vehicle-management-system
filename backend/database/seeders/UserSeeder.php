<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@utg.uz',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'is_active' => true,
            'organization_id' => null,
        ]);

        User::create([
            'name' => 'HQ Dispetcher',
            'email' => 'hq@utg.uz',
            'password' => Hash::make('password'),
            'role' => 'hq_dispatcher',
            'is_active' => true,
            'organization_id' => null,
        ]);

        User::create([
            'name' => 'Urganch Dispetcher',
            'email' => 'dispatcher@utg.uz',
            'password' => Hash::make('password'),
            'role' => 'dispatcher',
            'is_active' => true,
            'organization_id' => 5,
        ]);

        User::create([
            'name' => 'Mexanik Karimov',
            'email' => 'mechanic@utg.uz',
            'password' => Hash::make('password'),
            'role' => 'mechanic',
            'is_active' => true,
            'organization_id' => 5,
        ]);

        User::create([
            'name' => 'Dr. Yusupova',
            'email' => 'doctor@utg.uz',
            'password' => Hash::make('password'),
            'role' => 'doctor',
            'is_active' => true,
            'organization_id' => 5,
        ]);
    }
}
