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
        $provinceCode = $request->input('code');

        $kabupaten = kabupaten::where('code', $provinceCode)->get();

        if ($kabupaten->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => $kabupaten,
            ], 200);
        }

        if (!$provinceCode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Province ID is required.',
            ], 400);
        }

        $response = Http::get('https://api.binderbyte.com/wilayah/kabupaten', [
            'api_key' => env('BINDERBYTE_API_KEY'),
            'id_provinsi' => $provinceCode,
        ]);
        

        if ($response->successful()) {
            foreach ($response['value'] as $item) {
                kabupaten::updateOrCreate([

                ]);
            }
        }

        $kabupatens = kabupaten::where('code', $provinceCode)->get();

        return response()->json([
            'status' => 'success',
            'data' => $kabupatens,
        ], 200);
    }

    public function getKecamatan(Request $request)
    {
        $kabupaten_id = $request->input('kabupaten_id');


        $kecamatan = kecamatan::where('kabupaten_id', $kabupaten_id)->get();

        if ($kecamatan->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => $kecamatan,
            ], 200);
        }

        $response = Http::get('https://api.binderbyte.com/wilayah/kecamatan', [
            'api_key' => env('BINDERBYTE_API_KEY'),
            'id_kabupaten' => $kabupaten_id,
        ]);

        dd($request->all(), $response->json());

        if ($response->successful()) {
            foreach ($response['value'] as $item) {
                $id = str_replace('.', '', $item['id']);
                kecamatan::updateOrCreate(
                    ['id' => $id],
                    [
                        'name' => $item['name'],
                        'id_kabupaten' => $kabupaten_id,
                    ]
                );
            }


            $kecamatan = kecamatan::where('kabupaten_id', $kabupaten_id)->get();

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
}
