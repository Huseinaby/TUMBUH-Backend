<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class notificationController extends Controller
{
    public function getUserNotifications()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'notifications' => $notification,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if(!$user) {
            return response()->json(['message' => 'User Not Found'], 404);
        }

        $validateData = $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
            'data' => 'nullable|array',
            'badge' => 'nullable|integer',  
        ]);

        $validateData['user_id'] = $user->id;

        Notification::create($validateData);

        return response()->json([
            'message' => 'Notification created successfully',
            'notification' => $validateData,
        ], 201);
    }

    public function markAsRead($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->read = true;
        $notification->save();

        return response()->json([
            'notification' => $notification,
        ], 200);
    }
}
