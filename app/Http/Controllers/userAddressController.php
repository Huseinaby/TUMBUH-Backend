<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class userAddressController extends Controller
{

    public function getAddress(){
        $user = Auth::user();
        $alamat = UserAddress::where('user_id', $user->id)
            ->with(['province', 'kabupaten', 'kecamatan'])
            ->get();

        return response()->json([
            'message' => 'User addresses retrieved successfully',
            'data' => $alamat,
        ], 200);
    }


    public function store(Request $request){
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nomor_telepon' => 'required|string|max:15',
            'alamat_lengkap' => 'required|string',
            'province_id' => 'required|exists:provinces,id',
            'kabupaten_id' => 'required|exists:kabupatens,id',
            'kecamatan_id' => 'required|exists:kecamatans,id',
            'origin_id' => 'required|string|max:255',
            'kode_pos' => 'nullable|string|max:10',
            'label' => 'nullable|string|max:255',
            'is_default' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            if($request->is_default) {
                UserAddress::where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }

            $alamat = UserAddress::create([
                'user_id' => Auth::id(),
                'nama_lengkap' => $request->nama_lengkap,
                'nomor_telepon' => $request->nomor_telepon,
                'alamat_lengkap' => $request->alamat_lengkap,
                'province_id' => $request->province_id,
                'kabupaten_id' => $request->kabupaten_id,
                'kecamatan_id' => $request->kecamatan_id,
                'origin_id' => $request->origin_id,
                'kode_pos' => $request->kode_pos,
                'label' => $request->label,
                'is_default' => $request->is_default ?? false,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Address added successfully',
                'data' => $alamat->load(['province', 'kabupaten', 'kecamatan']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
