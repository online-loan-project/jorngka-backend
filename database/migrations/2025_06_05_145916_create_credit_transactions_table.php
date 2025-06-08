<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();

            // Transaction identification
            $table->string('transaction_code')->unique()->comment('Public-facing transaction ID');

            // Relationships
            $table->unsignedBigInteger('credit_id');
            $table->unsignedBigInteger('user_id')->comment('Direct user reference for faster queries');

            // Transaction details
            $table->decimal('amount', 15, 2);
            $table->enum('type', [
                'admin_deposit',      // Admin adds funds to user's balance
                'admin_withdrawal',    // Admin removes funds from user's balance
                'loan_disbursement',   // User receives loan (increases liability)
                'loan_repayment',      // User repays loan (decreases liability)
            ]);

            // Tracking and references
            $table->string('reference')->nullable()->comment('External reference number');
            $table->text('description')->nullable();
            $table->decimal('balance_after', 15, 2)->comment('Balance after this transaction');
            $table->decimal('outstanding_after', 15, 2)->default(0)->comment('Outstanding loans after');

            // Transaction chain reference
            $table->unsignedBigInteger('previous_transaction_id')->nullable()->comment('Previous transaction in sequence');
            $table->unsignedBigInteger('related_transaction_id')->nullable()->comment('For linked transactions');

            // Additional data
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
