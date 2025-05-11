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
        Schema::create('schedule_repayments', function (Blueprint $table) {
            $table->id();
            $table->date('repayment_date');
            $table->decimal('emi_amount', 10, 2);
            $table->enum('status', [0, 1,2])->default(0);
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_repayments');
    }
};
