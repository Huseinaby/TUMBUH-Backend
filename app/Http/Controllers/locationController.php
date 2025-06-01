<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class locationController extends Controller
{
    public function getProvince(){
        $provinces = Province::all();

        return response()->json([
            'status' => 'success',
            'data' => $provinces,
        ], 200);
    }

    public function syncProvince() {
        $response = Http::get('https://api.binderbyte.com/wilayah/provinsi', [
            'api_key' => env('BINDERBYTE_API_KEY'),
        ]);
        

        if($response->successful()) {
            foreach($response['value'] as $item) {
                Province::updateOrCreate([
                        'name' => $item['name'],
                    ]
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Provinces synchronized successfully.',
            ], 200);
        }
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to synchronize provinces.',
        ], 500);
    }
}
