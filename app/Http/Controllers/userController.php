<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class userController extends Controller
{
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
