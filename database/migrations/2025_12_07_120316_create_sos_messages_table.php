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
        Schema::create('sos_messages', function (Blueprint $table) {
            // ID Internal MySQL (BigInt) untuk performa indexing server
            $table->id(); 

            // PENTING: Ini menampung UUID dari SQLite di HP user
            // Supaya server tahu pesan ini buatan HP siapa & tidak duplikat
            $table->uuid('local_message_id')->unique(); 

            $table->string('sender_device_id'); // ID unik HP pengirim
            $table->string('sender_name')->nullable();
            
            $table->text('content');
            
            // Gunakan decimal untuk akurasi GPS
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            
            // Status: 'ACTIVE', 'CANCELLED', 'RESOLVED'
            $table->string('status')->default('ACTIVE'); 

            // Waktu kejadian (dari HP user, bukan waktu server terima)
            $table->timestamp('occurred_at', 6); 

            $table->timestamps(6); // created_at & updated_at server
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sos_messages');
    }
};
