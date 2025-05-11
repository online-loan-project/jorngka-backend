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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->integer('loan_duration');
            $table->decimal('loan_repayment', 10, 2);
            $table->decimal('revenue', 10, 2);
            $table->enum('status', [0, 1])->default(0);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('request_loan_id')->nullable();
            $table->unsignedBigInteger('credit_score_id')->nullable();
            $table->unsignedBigInteger('interest_rate_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
