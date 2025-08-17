<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;
use App\Services\RajaOngkirService;
use Illuminate\Support\Facades\Http;

class locationController extends Controller
{
    public function getProvince()
    {
        $provinces = Province::all();

        if ($provinces->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No provinces found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $provinces,
        ], 200);
    }

    public function syncProvince()
    {
        $response = Http::get('https://api.binderbyte.com/wilayah/provinsi', [
            'api_key' => env('BINDERBYTE_API_KEY'),
        ]);


        if ($response->successful()) {
            foreach ($response['value'] as $item) {
                Province::Create([
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'code' => $item['id'],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Provinces synchronized successfully.',
                'data' => Province::all(),
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to synchronize provinces.',
        ], 500);
    }

    public function getOrigin()
    {
        $keyword = request()->input('search', '');

        if (!$keyword) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search keyword is required.',
            ], 400);
        }

        $rajaOngkirService = app(RajaOngkirService::class);

        try {
            $results = $rajaOngkirService->searchDestination($keyword, 10, 0);

            return response()->json([
                'status' => 'success',
                'data' => $results['data'] ?? [],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve origin data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
