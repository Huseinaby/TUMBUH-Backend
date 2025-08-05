<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\FirebaseNotificationService;

class userController extends Controller
{

    public function update(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:50',
            'photo' => 'nullable', // bisa file atau URL
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->update($request->only('username'));

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile_photos', 'public');
            $user->photo = 'storage/' . $path;
            $user->save();
        } elseif ($request->filled('photo') && filter_var($request->photo, FILTER_VALIDATE_URL)) {
            $user->photo = $request->photo;
            $user->save();
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }


    public function becomeSeller(Request $request)
    {
        $request->validate([
            'storeName' => 'required|string|max:100'
        ]);

        $user = Auth::user();
        $user->update([
            'role' => 'seller',
            'storeName' => $request->storeName
        ]);

        return response()->json([
            'message' => 'now you are seller',
            'user' => $user,
        ]);
    }

    public function storeFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'message' => 'FCM token stored successfully',
            'user' => $user,
        ]);
    }

    public function sendNotifToUser($userId)
    {
        $user = User::findOrFail($userId);

        if (!$user) {
            return response()->json([
                'message' => "User Not Found"
            ], 400);
        }

        if (!$user->fcm_token) {
            return response()->json(['message' => 'FCM Token Not Found'], 400);
        }

        $firebase = new FirebaseNotificationService();
        $firebase->sendToToken($user->fcm_token, 'Hai!', 'Kamu punya notifikasi baru!');

        return response()->json(['mesage' => 'Notifikasi dikirim']);
    }
}
