<?php

namespace App\Http\Controllers;

use App\Models\SellerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class sellerController extends Controller
{
    public function register(Request $request) {
        $user = Auth::user();
        
        if($user->role == 'seller') {
            return response()->json([
                'message' => 'You are already a seller.',
                'user' => $user,
            ], 400);
        }

        $request->validate([
            'store_name' => 'required|string|max:100',
            'store_description' => 'required|string',
            'store_address' => 'required|string|max:150',
            'origin_id' => 'required|string',
            'store_phone' => 'required|string|max:15',
            'store_logo' => 'nullable|image|max:2048',
            'store_banner' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'store_name',
            'store_description',
            'store_address',
            'origin_id',
            'store_phone',
        ]);

        SellerDetail::create($data + [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        if($request->hasFile('store_logo')) {
            $data['store_logo'] = $request->file('store_logo')->store('seller_logos', 'public');
        }

        if($request->hasFile('store_banner')) {
            $data['store_banner'] = $request->file('store_banner')->store('seller_banners', 'public');
        }

        return response()->json([
            'message' => 'Seller registration successful. Your account is pending approval.',
            'user' => $user,
        ]);
    }
}
