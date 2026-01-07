<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\SOSMessage;
use App\Models\SenderCrcMapping;

class SyncControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creates_mapping_and_marks_from_server()
    {
        $deviceId = 'device-abc123';
        $crc = sprintf('%u', crc32($deviceId));

        $payload = [
            'messages' => [
                [
                    'local_message_id' => '\\' . uniqid('', true),
                    'sender_device_id' => $deviceId,
                    'content' => 'Test SOS',
                    'latitude' => 1.23,
                    'longitude' => 4.56,
                    'status' => 'ACTIVE',
                    'occurred_at' => now()->toIso8601String(),
                ]
            ]
        ];

        $response = $this->postJson('/api/sync/upload', $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('sender_crc_mappings', [
            'crc' => intval($crc),
            'sender_device_id' => $deviceId,
        ]);

        $this->assertDatabaseHas('sos_messages', [
            'sender_device_id' => $deviceId,
            'from_server' => true,
        ]);
    }

    public function test_upload_with_ble_placeholder_uses_mapping()
    {
        // First create a mapping
        $deviceId = 'device-xyz789';
        $crc = intval(sprintf('%u', crc32($deviceId)));
        SenderCrcMapping::create(['crc' => $crc, 'sender_device_id' => $deviceId]);

        // Now upload a message claiming to be from BLE with that CRC
        $placeholder = "ble-device-{$crc}";
        $payload = [
            'messages' => [
                [
                    'local_message_id' => '\\' . uniqid('', true),
                    'sender_device_id' => $placeholder,
                    'content' => 'BLE SOS',
                    'latitude' => 0.1,
                    'longitude' => 0.2,
                    'status' => 'ACTIVE',
                    'occurred_at' => now()->toIso8601String(),
                ]
            ]
        ];

        $response = $this->postJson('/api/sync/upload', $payload);
        $response->assertStatus(200);

        // Server should store the message with mapped sender_device_id
        $this->assertDatabaseHas('sos_messages', [
            'sender_device_id' => $deviceId,
            'sender_crc' => $crc,
        ]);
    }
}
