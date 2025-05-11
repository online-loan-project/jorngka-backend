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
        Schema::create('nid_information', function (Blueprint $table) {
            $table->id();
            $table->string('nid_number');
            //image
            $table->string('nid_image');
            //status
            $table->enum('status', [1,0])->default(0);
            $table->unsignedBigInteger('request_loan_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nid_information');
    }
};
