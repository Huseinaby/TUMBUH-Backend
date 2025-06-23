<?php

namespace App\Http\Controllers;

use App\Models\orderItem;
use App\Models\SellerDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class sellerController extends Controller
{

    public function getSeller(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        $sellerDetail = SellerDetail::where('user_id', $user->id)->first();

        if (!$sellerDetail) {
            return response()->json([
                'message' => 'You are not registered as a seller.',
            ], 404);
        }

        return response()->json([
            'message' => 'Seller details retrieved successfully.',
            'seller_detail' => $sellerDetail->load([
                'user' => function ($query) {
                    $query->select('id', 'username', 'email', 'role');
                }
            ]),
        ]);
    }

    public function register(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        if ($user->role == 'seller') {
            return response()->json([
                'message' => 'You are already a seller.',
                'user' => $user,
            ], 400);
        }

        if (SellerDetail::where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You have already registered as a seller.',
                'seller_detail' => SellerDetail::where('user_id', $user->id)->first(),
            ], 400);
        }

        $request->validate([
            'store_name' => 'required|string|max:100',
            'store_description' => 'required|string',
            'store_address' => 'required|string|max:150',
            'store_phone' => 'required|string|max:15',
            'store_logo' => 'nullable|image|max:2048',
            'store_banner' => 'nullable|image|max:2048',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:20',
            'bank_account_holder_name' => 'nullable|string|max:100',
            'nomor_induk_kependudukan' => 'nullable|string|max:16',
            'foto_ktp' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'store_name',
            'store_description',
            'store_address',
            'store_phone',
            'bank_name',
            'bank_account_number',
            'bank_account_holder_name',
            'nomor_induk_kependudukan',
        ]);

        if ($request->hasFile('store_logo')) {
            $data['store_logo'] = $request->file('store_logo')->store('seller_logos', 'public');
        }

        if ($request->hasFile('store_banner')) {
            $data['store_banner'] = $request->file('store_banner')->store('seller_banners', 'public');
        }

        if ($request->hasFile('foto_ktp')) {
            $data['foto_ktp'] = $request->file('foto_ktp')->store('seller_ktps', 'public');
        }

        $sellerDetail = SellerDetail::create($data + [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Seller details retrieved successfully.',
            'seller_detail' => $sellerDetail->load([
                'user' => function ($query) {
                    $query->select('id', 'username', 'email', 'role');
                }
            ]),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        $sellerDetail = SellerDetail::where('user_id', $user->id)->first();

        if (!$sellerDetail) {
            return response()->json([
                'message' => 'You are not registered as a seller.',
            ], 404);
        }

        $request->validate([
            'store_name' => 'sometimes|required|string|max:100',
            'store_description' => 'sometimes|required|string',
            'store_address' => 'sometimes|required|string|max:150',
            'store_phone' => 'sometimes|required|string|max:15',
            'store_logo' => 'nullable|image|max:2048',
            'store_banner' => 'nullable|image|max:2048',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:20',
            'bank_account_holder_name' => 'nullable|string|max:100',
            'nomor_induk_kependudukan' => 'nullable|string|max:16',
            'foto_ktp' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'store_name',
            'store_description',
            'store_address',
            'store_phone',
            'bank_name',
            'bank_account_number',
            'bank_account_holder_name',
            'nomor_induk_kependudukan',
        ]);

        if ($request->hasFile('store_logo')) {
            // Delete old logo if exists
            if ($sellerDetail->store_logo) {
                Storage::disk('public')->delete($sellerDetail->store_logo);
            }

            $data['store_logo'] = $request->file('store_logo')->store('seller_logos', 'public');
        }

        if ($request->hasFile('store_banner')) {
            // Delete old banner if exists
            if ($sellerDetail->store_banner) {
                Storage::disk('public')->delete($sellerDetail->store_banner);
            }

            $data['store_banner'] = $request->file('store_banner')->store('seller_banners', 'public');
        }

        if ($request->hasFile('foto_ktp')) {
            // Delete old KTP photo if exists
            if ($sellerDetail->foto_ktp) {
                Storage::disk('public')->delete($sellerDetail->foto_ktp);
            }

            $data['foto_ktp'] = $request->file('foto_ktp')->store('seller_ktps', 'public');
        }
        // Update the seller detail
        $sellerDetail->update($data);
        return response()->json([
            'message' => 'Seller details updated successfully.',
            'seller_detail' => $sellerDetail->load([
                'user' => function ($query) {
                    $query->select('id', 'username', 'email', 'role');
                }
            ]),
        ]);
    }

    public function destroy()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        $sellerDetail = SellerDetail::where('user_id', $user->id)->first();

        if (!$sellerDetail) {
            return response()->json([
                'message' => 'You are not registered as a seller.',
            ], 404);
        }

        if ($sellerDetail->store_logo) {
            Storage::disk('public')->delete($sellerDetail->store_logo);
        }
        if ($sellerDetail->store_banner) {
            Storage::disk('public')->delete($sellerDetail->store_banner);
        }
        if ($sellerDetail->foto_ktp) {
            Storage::disk('public')->delete($sellerDetail->foto_ktp);
        }

        // Delete the seller detail
        $sellerDetail->delete();

        return response()->json([
            'message' => 'Seller account deleted successfully.',
        ]);
    }

    public function getOriginSeller(Request $request)
    {
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

    public function verifySeller(Request $request)
    {
        $user = Auth::user();

        if (!$user || $user->role != 'admin') {
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

    public function getWeeklySales(Request $request)
    {
        $seller = Auth::user();

        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        $sales = orderItem::with(['product', 'transaction'])
            ->whereHas('product', function ($query) use ($seller) {
                $query->where('user_id', $seller->id);
            })
            ->whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'paid');
            })
            ->selectRaw('DATE(created_at) as date, SUM(quantity) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $weeklySales = [];

        for($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateString = $date->toDateString();
            $dayName = $date->format('l');

            $weeklySales[] = [
                'date' => $dateString,
                'day' => $dayName,
                'total_sold' => isset($sales[$dateString]) ? (int) $sales[$dateString]->total : 0,
            ];
        }

        return response()->json([
            'saller_id' => $seller->id,
            'sales' => $weeklySales,
        ]);
    }
}
