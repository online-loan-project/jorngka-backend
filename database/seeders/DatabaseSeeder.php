<?php

namespace Database\Seeders;

use App\Constants\ConstCreditTransaction;
use App\Models\Credit;
use App\Models\CreditTransaction;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
//
//        User::factory()->create([
//            'name' => 'Test User',
//            'email' => 'test@example.com',
//        ]);

        $credit = Credit::create([
            'balance' => 1000,
            'currency' => 'USD',
            'is_active' => true,
            'last_transaction_at' => now(),
        ]);

        //create transactions for the credit
        CreditTransaction::create([
            'transaction_code' => 'DEP-1',
            'credit_id' => $credit->id,
            'user_id' => 1, // Assuming user with ID 1 exists
            'amount' => 1000,
            'type' => ConstCreditTransaction::TYPE_ADMIN_DEPOSIT,
            'reference' => 'Initial deposit',
            'description' => 'Initial deposit for testing',
            'balance_after' => $credit->balance,
            'outstanding_after' => 0,
            'previous_transaction_id' => null, // Assuming this is the first transaction
            'related_transaction_id' => null, // No related transaction for this deposit
            'metadata' => ['source' => 'bank transfer'],
        ]);
    }
}
