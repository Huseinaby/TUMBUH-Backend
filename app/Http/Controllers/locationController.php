<?php

namespace App\Http\Controllers;

use App\Models\kabupaten;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class locationController extends Controller
{
    public function getProvince()
    {
        $provinces = Province::all();

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
                        'name' => $item['name']
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

    public function getKabupaten(Request $request)
    {
        $provinceId = $request->input('province_id');

        $kabupaten = kabupaten::where('province_id', $provinceId)->get();

        if ($kabupaten->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => $kabupaten,
            ], 200);
        }

        if (!$provinceId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Province ID is required.',
            ], 400);
        }

        $response = Http::get('https://api.binderbyte.com/wilayah/kabupaten', [
            'api_key' => env('BINDERBYTE_API_KEY'),
            'id_provinsi' => $provinceId,
        ]);

        dd($response->json());

        if ($response->successful()) {
            foreach ($response['value'] as $item) {
                kabupaten::updateOrCreate(
                    ['id' => $item['id']],
                    [
                        'name' => $item['name'],
                        'province_id' => $provinceId,
                    ]
                );
            }
        }

        $kabupatens = kabupaten::where('province_id', $provinceId)->get();

        return response()->json([
            'status' => 'success',
            'data' => $kabupatens,
        ], 200);
    }
}
