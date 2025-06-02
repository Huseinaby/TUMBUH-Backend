<?php

namespace App\Http\Controllers;

use App\Models\kabupaten;
use App\Models\kecamatan;
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

    public function getKabupaten(Request $request)
    {
        $province_code = $request->input('province_code');

        $kabupaten = kabupaten::where('province_id', $province_code)->get();

        if ($kabupaten->isNotEmpty()) {
            return response()->json([
                'status' => 'Kabupaten already exists',
                'data' => $kabupaten,
            ], 200);
        }

        if (!$province_code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Province Code is required.',
            ], 400);
        }

        $response = Http::get('https://api.binderbyte.com/wilayah/kabupaten', [
            'api_key' => env('BINDERBYTE_API_KEY'),
            'id_provinsi' => $province_code,
        ]);

        if ($response->successful()) {
            foreach ($response['value'] as $item) {
                kabupaten::updateOrCreate([
                    'id' => str_replace('.', '', $item['id']),
                ], [
                    'name' => $item['name'],
                    'province_id' => $province_code,
                    'code' => $item['id'],
                ]);
            }
        }

        $kabupatens = kabupaten::where('province_id', $province_code)->get();

        return response()->json([
            'status' => 'success',
            'data' => $kabupatens,
        ], 200);
    }

    public function getKecamatan(Request $request)
    {
        $kabupatenId = $request->input('kabupaten_code');

        $kabupaten_code = str_replace('.', '', $kabupatenId);

        $kecamatan = kecamatan::where('kabupaten_id', $kabupaten_code)->get();

        if ($kecamatan->isNotEmpty()) {
            return response()->json([
                'status' => 'kecamatan already exists',
                'data' => $kecamatan,
            ], 200);
        }

        $response = Http::get('https://api.binderbyte.com/wilayah/kecamatan', [
            'api_key' => env('BINDERBYTE_API_KEY'),
            'id_kabupaten' => $kabupatenId,
        ]);

        if ($response->successful()) {
            foreach ($response['value'] as $item) {
                $id = str_replace('.', '', $item['id']);
                kecamatan::updateOrCreate(
                    ['id' => $id],
                    [
                        'name' => $item['name'],
                        'kabupaten_id' => $kabupaten_code,
                        'code' => $item['id']
                    ]
                );
            }


            $kecamatan = kecamatan::where('kabupaten_id', $kabupaten_code)->get();

            return response()->json([
                'status' => 'success',
                'data' => $kecamatan,
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve kecamatan data.',
        ], 500);
    }

    public function getOriginByKecamatan()
    {
        $keyword = request()->input('search', '');
        $baseUrl = config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');
 
        if (!$keyword) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search keyword is required.',
            ], 400);
        }

        $response = Http::withHeaders([
            'key' => config('services.rajaongkir.api_key'),
        ])->get("{$baseUrl}/destination/domestic-destination", [
            'search' => $keyword,
            'limit' => 10,
            'offset' => 0,
        ]);

        if($response->successful()){
            return response()->json([
                'status' => 'success',
                'data' => $response->json()['data'],
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to retrieve origin data.',
        ], 500);
    }
}
