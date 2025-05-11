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
        Schema::create('income_information', function (Blueprint $table) {
            $table->id();
            $table->string('employee_type');
            $table->string('position');
            $table->decimal('income', 10, 2);
            $table->string('bank_statement')->nullable();
            $table->unsignedBigInteger('request_loan_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_information');
    }
};
