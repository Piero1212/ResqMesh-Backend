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
        Schema::table('sos_messages', function (Blueprint $table) {
            // 32-bit unsigned CRC32 of sender_device_id (nullable for legacy/unknown)
            $table->unsignedInteger('sender_crc')->nullable()->after('sender_device_id');

            // Mark if this message is being distributed by the server (server-mark)
            $table->boolean('from_server')->default(false)->after('sender_crc');

            // Add index for quick lookup
            $table->index(['sender_crc']);
            $table->index(['from_server']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sos_messages', function (Blueprint $table) {
            $table->dropIndex(['sender_crc']);
            $table->dropIndex(['from_server']);
            $table->dropColumn(['sender_crc', 'from_server']);
        });
    }
};