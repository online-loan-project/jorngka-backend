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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('device_id')->unique(); // Unique device identifier
            $table->string('device_name');
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('os')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('fcm_token')->nullable(); // For push notifications
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
