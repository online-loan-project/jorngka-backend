<?php

namespace Database\Seeders;

use App\Models\Credit;
use App\Models\CreditTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate the existing data to start fresh
        Credit::truncate();
        CreditTransaction::truncate();

        // Seed the credits table with initial data

    }
}
