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
                    'local_message_id' => (string) \Illuminate\Support\Str::uuid(),
                    'sender_device_id' => $deviceId,
                    'content' => 'Test SOS',
                    'latitude' => 1.23,
                    'longitude' => 4.56,
                    'status' => 'ACTIVE',
                    'occurred_at' => now()->format('Y-m-d\\TH:i:s.u\\Z'),
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
                    'local_message_id' => (string) \Illuminate\Support\Str::uuid(),
                    'sender_device_id' => $placeholder,
                    'content' => 'BLE SOS',
                    'latitude' => 0.1,
                    'longitude' => 0.2,
                    'status' => 'ACTIVE',
                    'occurred_at' => now()->format('Y-m-d\\TH:i:s.u\\Z'),
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

    public function test_download_with_since_milliseconds_returns_ok()
    {
        // Seed a message
        $msg = SOSMessage::create([
            'local_message_id' => (string) \Illuminate\Support\Str::uuid(),
            'sender_device_id' => 'device-download',
            'content' => 'Hello',
            'latitude' => 1.0,
            'longitude' => 2.0,
            'status' => 'ACTIVE',
            'occurred_at' => now(),
        ]);

        $sinceMs = (int) (microtime(true) * 1000) - 10000; // 10s ago

        $response = $this->getJson('/api/sync/download?since=' . $sinceMs);
        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('messages', $json);
        $this->assertArrayHasKey('ack_data', $json);
    }
}
