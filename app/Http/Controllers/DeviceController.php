<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\SensorData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Database;

class DeviceController extends Controller
{
    protected $database;

    public function __construct()
    {
        $this->database = app('firebase.database');
    }

    public function receiveData(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'api_key' => 'required|string',
            'temperature' => 'required|numeric',
            'humidity' => 'required|numeric',
            'soil_moisture' => 'required|integer',
            'pump_status' => 'required|string',
            'status' => 'required|string'            
        ]);        
        
        $device = Device::where('serial_number', $request->serial_number)
            ->where('api_key', hash('sha256', $request->api_key))
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Invalid device or API Key'
            ], 403);
        }

        $hour = now()->hour;
        if ($hour >= 5 && $hour < 11) {
            $time_slot = 'pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            $time_slot = 'siang';
        } elseif ($hour >= 15 && $hour < 18) {
            $time_slot = 'sore';
        } else {
            $time_slot = 'malam';
        }

        $today = now()->toDateString();

        $existing = SensorData::where('device_id', $device->id)
                ->where('time_slot', $time_slot)
                ->where('date', $today)
                ->first();
    
        if ($existing) {
            $existing->update([
                'temperature' => $request->temperature,
                'humidity' => $request->humidity,
                'soil_moisture' => $request->soil_moisture,
                'pump_status' => $request->pump_status,
                'status' => $request->status,
            ]);
            $data = $existing;
        } else {
            $data = SensorData::create([
                'device_id' => $device->id,
                'time_slot' => $time_slot,
                'temperature' => $request->temperature,
                'humidity' => $request->humidity,
                'soil_moisture' => $request->soil_moisture,
                'pump_status' => $request->pump_status,
                'status' => $request->status,
                'date' => $today,
            ]);
        }
            

        $data = [
            'time_slot' => $time_slot,
            'temperature' => $request->temperature,
            'humidity' => $request->humidity,
            'soil_moisture' => $request->soil_moisture,
            'pump_status' => $request->pump_status,
            'status' => $request->status,
            'updated_at' => now()->toDateTimeString(),
        ];

        // Simpan ke Firebase
        $this->database->getReference('devices/' . $device->serial_number)
            ->set($data);

        return response()->json([
            'message' => 'Data received and stored successfully',
            'serial_number' => $device->serial_number,
            'data' => $data
        ], 200);
    }

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
            'device' => [
                'id' => $device->id,
                'user_id' => $device->user_id,
                'serial_number' => $device->serial_number,
                'device_name' => $device->device_name,                
                'updated_at' => $device->updated_at,
            ],
        ], 200);
    }

    public function registerDevice(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string|unique:devices,serial_number',            
        ]);

        $rawApiKey = bin2hex(random_bytes(16));
        $device = Device::create([            
            'api_key' => hash('sha256', $rawApiKey),
            'serial_number' => $request->serial_number,
            'device_name' => 'unnamed device'        
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'device' => [
                'serial_number' => $device->serial_number,
                'api_key' => $rawApiKey,
            ]
        ], 201);
    }

    public function pair(Request $request){
        $request->validate([
            'serial_number' => 'required|string|exists:devices,serial_number',
            'device_name' => 'sometimes|string',
        ]);

        $userId = Auth::id();
        $device = Device::where("serial_number", $request->serial_number)->first();

        if(!$device){
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        if($device->user_id && $device->user_id != $userId){
            return response()->json([
                'message' => 'Device already paired with another user'
            ], 403);
        }

        $updateData = ['user_id' => $userId,];
        if($request->has('device_name')){
            $updateData['device_name'] = $request->device_name;
        }

        $device->update($updateData);

        return response()->json([
            'message' => 'Device paired successfully',
            'device' => [
                'id' => $device->id,
                'user_id' => $device->user_id,
                'serial_number' => $device->serial_number,
                'device_name' => $device->device_name,
            ]
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

    public function getSensorLogs($serialNumber){

        $device = Device::where("serial_number", $serialNumber)->firstOrFail();

        if(!$device){
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        if(!$device->user_id || $device->user_id != Auth::id()){
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $logs = SensorData::where("device_id", $device->id)
                ->whereDate('created_at', now()->toDateString())
                ->orderByRaw("FIELD(time_slot, 'pagi', 'siang', 'sore', 'malam')")
                ->get();
        
        return response()->json([
            'device' => $device->serial_number,
            'date' => now()->toDateString(),
            'logs' => $logs
        ], 200);
    }
}
