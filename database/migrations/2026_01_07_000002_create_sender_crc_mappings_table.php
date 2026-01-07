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
        Schema::create('sender_crc_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('crc')->unique();
            $table->string('sender_device_id');
            $table->timestamp('first_seen_at', 6)->useCurrent();
            $table->timestamps(6);

            $table->index(['crc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sender_crc_mappings');
    }
};