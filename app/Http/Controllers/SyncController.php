<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SOSMessage; // Import the SOSMessage model
use App\Models\SenderCrcMapping;
use Illuminate\Support\Facades\DB; // For database transactions
use Carbon\Carbon; // For timestamp parsing

class SyncController extends Controller
{
    /**
     * Handle incoming SOS messages from clients (upload from offline to online).
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.local_message_id' => 'required|string|uuid',
            'messages.*.sender_device_id' => 'required|string',
            'messages.*.content' => 'nullable|string',
            'messages.*.latitude' => 'required|numeric',
            'messages.*.longitude' => 'required|numeric',
            'messages.*.status' => 'required|string|in:ACTIVE,CANCELLED,RESOLVED',
            'messages.*.occurred_at' => 'required|date_format:Y-m-d\TH:i:s.u\Z',
        ]);

        $processedIds = [];
        $errorMessages = [];

        $messagesByDevice = collect($validated['messages'])->groupBy('sender_device_id');

        foreach ($messagesByDevice as $deviceId => $messages) {
            // For each device, find the very latest message from the incoming batch.
            $latestMessageData = $messages->sortByDesc('occurred_at')->first();

            try {
                $senderCrc = null;
                $deviceIdToUse = $deviceId;

                // If the deviceId looks like a BLE placeholder, try to map crc -> real device
                if (preg_match('/^ble-device-(\d+)$/', $deviceId, $m)) {
                    $senderCrc = intval($m[1]);
                    $mapping = \App\Models\SenderCrcMapping::where('crc', $senderCrc)->first();
                    if ($mapping) {
                        $deviceIdToUse = $mapping->sender_device_id;
                    }
                } else {
                    // If deviceId is a normal full device id, ensure we have a mapping
                    $crcVal = sprintf('%u', crc32($deviceId));
                    $senderCrc = intval($crcVal);
                    // Upsert mapping (first_seen_at only when created)
                    \App\Models\SenderCrcMapping::updateOrCreate(
                        ['crc' => $senderCrc],
                        ['sender_device_id' => $deviceId, 'first_seen_at' => now()]
                    );
                }

                // Try to find existing by either real device id or sender_crc (covers BLE-received messages)
                $existingQuery = SOSMessage::query();
                $existingQuery->where(function ($q) use ($deviceIdToUse, $senderCrc) {
                    $q->where('sender_device_id', $deviceIdToUse);
                    if (!is_null($senderCrc)) {
                        $q->orWhere('sender_crc', $senderCrc);
                    }
                });

                $existingMessage = $existingQuery->first();

                $incomingTime = Carbon::parse($latestMessageData['occurred_at']);

                // Only proceed if there is no existing message, or if the incoming one is newer.
                if (!$existingMessage || $incomingTime->isAfter($existingMessage->occurred_at)) {
                    \Log::info('Incoming message is newer. Updating/Creating for device: ' . $deviceIdToUse, $latestMessageData);

                    SOSMessage::updateOrCreate(
                        ['sender_device_id' => $deviceIdToUse, 'sender_crc' => $senderCrc],
                        [
                            'local_message_id' => $latestMessageData['local_message_id'],
                            'content' => $latestMessageData['content'] ?? '',
                            'latitude' => $latestMessageData['latitude'],
                            'longitude' => $latestMessageData['longitude'],
                            'status' => $latestMessageData['status'],
                            'occurred_at' => $incomingTime,
                            'updated_at' => $incomingTime, // Explicitly set timestamp
                            'sender_name' => $latestMessageData['sender_name'] ?? null,
                            'sender_crc' => $senderCrc,
                            // Mark as server stored so clients can treat it as server-originated when they download
                            'from_server' => true,
                        ]
                    );

                    $processedIds[] = $latestMessageData['local_message_id'];
                } else {
                    \Log::info('Incoming message is not newer. Skipping for device: ' . $deviceIdToUse);
                    // Still mark as "processed" from the client's perspective
                    $processedIds[] = $latestMessageData['local_message_id'];
                }

            } catch (\Exception $e) {
                $errorMessage = "Failed to process message for device " . $deviceId . ": " . $e->getMessage();
                $errorMessages[] = $errorMessage;
                \Log::error("Sync Upload Error: " . $errorMessage, $latestMessageData);
            }
        }

        if (!empty($errorMessages)) {
            return response()->json([
                'message' => 'Synchronization completed with some errors.',
                'processed_ids' => $processedIds,
                'errors' => $errorMessages,
                'acknowledged' => true, // ACK even with errors
                'ack_timestamp' => now()->toIso8601String(),
            ], 500);
        }

        // Include timestamp for each processed message for ACK validation
        $ackData = [];
        foreach ($processedIds as $localMessageId) {
            // Find the message to get its timestamp
            $message = SOSMessage::where('local_message_id', $localMessageId)->first();
            if ($message) {
                $ackData[] = [
                    'local_message_id' => $localMessageId,
                    'sender_device_id' => $message->sender_device_id,
                    'sender_crc' => $message->sender_crc,
                    'from_server' => $message->from_server,
                    'occurred_at' => $message->occurred_at->toIso8601String(), // Timestamp of the message
                ];
            }
        }

        return response()->json([
            'message' => 'Synchronization successful.',
            'synced_messages_count' => count($processedIds),
            'processed_ids' => $processedIds,
            'acknowledged' => true, // ACK mechanism
            'ack_timestamp' => now()->toIso8601String(), // Timestamp when server received
            'ack_data' => $ackData, // Include device_id, crc and timestamp for validation
        ], 200);
    }

    /**
     * Send SOS messages from the server to the client (download from online to offline).
     * Also includes ACK data for messages that have been acknowledged by server.
     */
    public function download(Request $request)
    {
        // Expecting a timestamp; accept 0 as valid. Check presence instead of emptiness.
        if (!$request->query->has('since')) {
            return response()->json(['message' => 'Missing "since" timestamp parameter.'], 400);
        }

        $since = (int) $request->query('since');

        try {
            $messages = SOSMessage::where('updated_at', '>', $since)
                                ->orWhere('created_at', '>', $since)
                                ->orderBy('updated_at', 'asc')
                                ->get();

            // Include ACK data for all synced messages (so offline nodes can receive ACK)
            $ackData = [];
            $allSyncedMessages = SOSMessage::all();
            foreach ($allSyncedMessages as $message) {
                $ackData[] = [
                    'local_message_id' => $message->local_message_id,
                    'sender_device_id' => $message->sender_device_id,
                    'sender_crc' => $message->sender_crc,
                    'from_server' => $message->from_server,
                    'occurred_at' => $message->occurred_at->toIso8601String(),
                ];
            }

            // Transform messages: ensure 'from_server' and 'sender_crc' are included explicitly
            $messagesArray = $messages->map(function ($m) {
                return [
                    'local_message_id' => $m->local_message_id,
                    'sender_device_id' => $m->sender_device_id,
                    'sender_crc' => $m->sender_crc,
                    'from_server' => $m->from_server,
                    'content' => $m->content,
                    'latitude' => (float) $m->latitude,
                    'longitude' => (float) $m->longitude,
                    'status' => $m->status,
                    'occurred_at' => $m->occurred_at->toIso8601String(),
                    'created_at' => $m->created_at->toIso8601String(),
                    'updated_at' => $m->updated_at->toIso8601String(),
                ];
            });

            return response()->json([
                'messages' => $messagesArray,
                'ack_data' => $ackData, // Include ACK data for offline nodes
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve messages.', 'error' => $e->getMessage()], 500);
        }
    }
}
