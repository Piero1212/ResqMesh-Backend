<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SenderCrcMapping extends Model
{
    use HasFactory;

    protected $table = 'sender_crc_mappings';

    protected $fillable = [
        'crc',
        'sender_device_id',
        'first_seen_at',
    ];

    protected $casts = [
        'crc' => 'integer',
        'first_seen_at' => 'datetime',
    ];
}
