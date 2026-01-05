<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SOSMessage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\DB;

class SOSMessageController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'local_message_id' => 'required|string|uuid',
            'device_id' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'battery_level' => 'sometimes|numeric',
            'timestamp' => 'required|date_format:Y-m-d\TH:i:s.u\Z',
            'message' => 'nullable|string',
            'status' => 'required|string|in:ACTIVE,CANCELLED,RESOLVED',
        ]);

        $deviceId = $validatedData['device_id'];
        $incomingTime = Carbon::parse($validatedData['timestamp']);

        // Find the existing record for this device, if any.
        $existingMessage = SOSMessage::where('sender_device_id', $deviceId)->first();

        // Only proceed if there is no existing message, or if the incoming one is newer.
        if (!$existingMessage || $incomingTime->isAfter($existingMessage->occurred_at)) {
            $message = SOSMessage::updateOrCreate(
                ['sender_device_id' => $deviceId],
                [
                    'local_message_id' => $validatedData['local_message_id'],
                    'content' => $validatedData['message'] ?? '',
                    'latitude' => $validatedData['latitude'],
                    'longitude' => $validatedData['longitude'],
                    'status' => $validatedData['status'],
                    'occurred_at' => $incomingTime,
                    'updated_at' => $incomingTime, // Explicitly set timestamp
                    // 'sender_name' can be added here if sent from client
                ]
            );
            return response()->json(['message' => 'SOS message processed successfully', 'data' => $message], 200);
        }

        return response()->json(['message' => 'SOS message skipped (not newer).'], 200);
    }
}
