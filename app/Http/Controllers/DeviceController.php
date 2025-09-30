<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceController extends Controller
{
    public function myDevices()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $device = Device::where("user_id", $userId)->first();

        if (!$device) {
            return response()->json([
                'message' => 'No device found for this user'
            ], 404);
        }

        return response()->json([
            'device' => $device
        ], 200);
    }

    public function pair(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'device_name' => 'sometimes|string',
        ]);

        $userId = Auth::id();
        $device = Device::where("serial_number", $request->serial_number)->first();

        // If the device does not exist, create it
        if (!$device) {
            $device = Device::create([
                'user_id' => $userId,
                'serial_number' => $request->serial_number,
                'device_name' => $request->device_name ?? 'Unnamed Device',
            ]);

            return response()->json([
                'message' => 'Device created successfully',
                'device' => $device
            ], 201);
        }

        // Check if the device is already registered to another user
        if ($device->user_id && $device->user_id != $userId) {
            return response()->json([
                'message' => 'This device is already registered to another user'
            ], 403);
        }
        
        // Update the device with the new user_id and device_name if provided
        $updateData = ['user_id' => $userId];
        if ($request->has('device_name')) {
            $updateData['device_name'] = $request->device_name;
        }
        $device->update($updateData);

        return response()->json([
            'message' => 'Device paired successfully',
            'device' => $device
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'device_name' => 'sometimes|required|string',
        ]);

        $userId = Auth::id();

        $device = Device::where("user_id", $userId)->where("id", $id)->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        $device->device_name = $request->input('device_name', $device->device_name);

        $device->save();

        return response()->json([
            'message' => 'Device updated successfully',
            'device' => $device
        ], 200);
    }

    public function destroy($id)
    {
        $userId = Auth::id();

        $device = Device::where("user_id", $userId)->where("id", $id)->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        $device->delete();

        return response()->json([
            'message' => 'Device deleted successfully'
        ], 200);
    }
}
