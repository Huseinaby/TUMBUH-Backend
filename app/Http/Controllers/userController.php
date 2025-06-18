<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class userController extends Controller
{

    public function update(Request $request){
        $request->validate([
            'username' => 'required|string|max:50',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $user = Auth::user();
        $user->update($request->only('username'));

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile_photos', 'public');
            $user->photo = 'storage/'. $path;
            $user->save();
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
        
    }
    public function becomeSeller(Request $request){
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
}
