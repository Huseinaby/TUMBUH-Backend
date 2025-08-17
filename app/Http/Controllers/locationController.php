<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;
use App\Services\RajaOngkirService;
use Illuminate\Support\Facades\Http;

class locationController extends Controller
{
    public function getProvince()
    {
        $provinces = Province::all();

        if($provinces->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'source' => 'database',
                'data' => $provinces,
            ], 200);
        }

        $response = Http::get('http://api.binderbyte.com/wilayah/provinsi', [
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
                'source' => 'api',
                'data' => Province::all(),
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve provinces.',
        ], 500);
    }

    public function getCities($provinceId)
    {
        $cities = City::where('province_id', $provinceId)->get();

        if($cities->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'source' => 'database',
                'data' => $cities,
            ], 200);
        }

        $response = Http::get('https://api.binderbyte.com/wilayah/kabupaten', [
            'api_key' => env('BINDERBYTE_API_KEY'),
            'id_provinsi' => $provinceId,
        ]);

        if($response->successful()){
            $data = $response->json();

            foreach($data['value'] as $item) {
                City::create([
                    'province_id' => $provinceId,
                    'name' => $item['name'],
                    'code' => $item['id'],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'source' => 'api',
                'data' => City::where('province_id', $provinceId)->get(),
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve cities.',
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
