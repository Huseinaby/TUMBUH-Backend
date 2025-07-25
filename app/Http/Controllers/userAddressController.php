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
                'origin_id' => $request->origin_id,
                'kode_pos' => $request->kode_pos,
                'label' => $request->label,
                'is_default' => $request->is_default ?? false,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Address added successfully',
                'data' => $alamat,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id){
        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'nomor_telepon' => 'sometimes|string|max:15',
            'alamat_lengkap' => 'sometimes|string',
            'origin_id' => 'sometimes|string|max:255',
            'kode_pos' => 'sometimes|string|max:10',
            'label' => 'sometimes|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $alamat = UserAddress::where('user_id', $user->id)->findOrFail($id);
        if(!$alamat) {
            return response()->json([
                'message' => 'Address not found',
            ], 404);
        }

        DB::beginTransaction();

        try {
            if($request->is_default) {
                UserAddress::where('user_id', $user->id)
                    ->update(['is_default' => false]);
            }

            $alamat->update([
                'nama_lengkap' => $request->nama_lengkap ?? $alamat->nama_lengkap,
                'nomor_telepon' => $request->nomor_telepon ?? $alamat->nomor_telepon,
                'alamat_lengkap' => $request->alamat_lengkap ?? $alamat->alamat_lengkap,
                'origin_id' => $request->origin_id ?? $alamat->origin_id,
                'kode_pos' => $request->kode_pos ?? $alamat->kode_pos,
                'label' => $request->label ?? $alamat->label,
                'is_default' => $request->is_default ?? false,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Address updated successfully',
                'data' => $alamat,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update address',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function destroy($id){
        $user = Auth::user();
        $alamat = UserAddress::where('user_id', $user->id)->findOrFail($id);

        if(!$alamat) {
            return response()->json([
                'message' => 'Address not found',
            ], 404);
        }

        $alamat->delete();
        return response()->json([
            'message' => 'Address deleted successfully',
        ], 200);
    }
}
