<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SOSMessage extends Model
{
    use HasFactory;

    // Define the table name if it's not the plural form of the model name
    protected $table = 'sos_messages';

    protected $fillable = [
        'local_message_id',
        'sender_device_id',
        'sender_name',
        'content',
        'latitude',
        'longitude',
        'status',
        'occurred_at',
        'updated_at', // Allow updated_at to be mass-assigned
        'sender_crc',
        'from_server',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'occurred_at' => 'datetime',
        'from_server' => 'boolean',
        'sender_crc' => 'integer',
    ];
}