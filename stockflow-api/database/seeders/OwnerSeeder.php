<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeeder extends Seeder
{
    /**
     * Membuat akun Owner awal untuk development.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'owner@stockflow.test',
            ],
            [
                'name' => 'Owner StockFlow',
                'password' => Hash::make('Password123!'),
                'role' => 'owner',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}