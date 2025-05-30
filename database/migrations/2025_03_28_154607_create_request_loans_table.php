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
        Schema::create('request_loans', function (Blueprint $table) {
            $table->id();
            $table->decimal('loan_amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->default(0);
            $table->Integer('loan_duration');
            $table->string('loan_type');
            $table->string('rejection_reason')->nullable();
            $table->enum('status', ['pending', 'not_eligible', 'approved', 'rejected', 'eligible'])->default('pending');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_loans');
    }
};
