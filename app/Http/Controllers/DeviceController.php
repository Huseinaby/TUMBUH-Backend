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

    public function store(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|unique:devices,serial_number',
            'device_name' => 'required|string',
        ]);

        $userId = Auth::id();

        $device = Device::create([
            'user_id' => $userId,
            'serial_number' => $request->input('serial_number'),
            'device_name' => $request->input('device_name'),
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'device' => $device
        ], 201);
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
