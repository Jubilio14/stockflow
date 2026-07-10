<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seluruh data awal aplikasi.
     */
    public function run(): void
    {
        $this->call([
            OwnerSeeder::class,
        ]);
    }
}