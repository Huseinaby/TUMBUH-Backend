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

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'title' => $notif->title,
                    'body' => $notif->body,
                    'type' => $notif->type,
                    'read' => (bool) $notif->read,
                    'created_at' => $notif->created_at->toISOString(),
                    'data' => json_decode($notif->data, true),
                ];
            });

        return response()->json($notifications);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User Not Found'], 404);
        }

        $validateData = $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
            'type' => 'required|string',
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

    public function show($id)
    {
        $notif = Notification::findOrFail($id);

        $notification = [
            'id' => $notif->id,
            'title' => $notif->title,
            'body' => $notif->body,
            'type' => $notif->type,
            'read' => (bool) $notif->read,
            'created_at' => $notif->created_at->toISOString(),
            'data' => json_decode($notif->data, true),
        ];

        if (!$notification) {
            return response()->json(['message' => 'Notification Not Found'], 404);
        }

        return response()->json([
            'message' => 'Notification Found',
            'notification' => $notification,
        ]);
    }

    public function deleteNotifications(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:notifications,id',
        ]);
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];

        $user = Auth::user();

        $deleted = Notification::whereIn('id', $ids)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'Notifications deleted successfully',
            'deleted_count' => $deleted,
        ], 200);
    }
}
