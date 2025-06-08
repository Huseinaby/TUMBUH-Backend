<?php

namespace App\Http\Controllers;

use App\Models\SellerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class sellerController extends Controller
{
    public function register(Request $request) {
        $user = Auth::user();

        if(!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }
        
        if($user->role == 'seller') {
            return response()->json([
                'message' => 'You are already a seller.',
                'user' => $user,
            ], 400);
        }

        if(SellerDetail::where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You have already registered as a seller.',
                'seller_detail' => SellerDetail::where('user_id', $user->id)->first(),
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
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:20',
            'bank_account_holder_name' => 'nullable|string|max:100',
            'foto_ktp' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'store_name',
            'store_description',
            'store_address',
            'origin_id',
            'store_phone',
            'bank_name',
            'bank_account_number',
            'bank_account_holder_name',
        ]);

        if($request->hasFile('store_logo')) {
            $data['store_logo'] = $request->file('store_logo')->store('seller_logos', 'public');
        }

        if($request->hasFile('store_banner')) {
            $data['store_banner'] = $request->file('store_banner')->store('seller_banners', 'public');
        }

        SellerDetail::create($data + [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Seller registration successful. Your account is pending approval.',
            'user' => $user,
            'seller_detail' => SellerDetail::where('user_id', $user->id)->first(),
        ]);
    }

    public function getOriginSeller(Request $request) {
        $search = $request->input('search', '');
        $baseUrl = config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');

        $response = Http::withHeaders([
            'key' => config('services.rajaongkir.api_key'),
        ])->get("{$baseUrl}/destination/domestic-destination", [
            'search' => $search,
            'limit' => 10,
            'offset' => 0,
        ]);
        
        return response()->json($response->json());
    }

    public function verifySeller(Request $request) {
        $user = Auth::user();

        if(!$user || $user->role != 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Only admin can verify sellers.',
            ], 401);
        }

        $request->validate([
            'seller_id' => 'required|exists:seller_details,id',
            'status' => 'required|in:approved,rejected',
        ]);

        $sellerDetail = SellerDetail::find($request->input('seller_id'));
        $sellerDetail->status = $request->input('status');
        $sellerDetail->save();

        return response()->json([
            'message' => 'Seller verification status updated successfully.',
            'seller_detail' => $sellerDetail,
        ]);
    }
}
