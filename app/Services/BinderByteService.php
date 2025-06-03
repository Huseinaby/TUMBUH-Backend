<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BinderByteService
{
    protected $key, $baseUrl;

    public function __construct()
    {
        $this->key = config('services.binderbyte.key');
        $this->baseUrl = 'https://apo.binderbyte.com/api/v1/track';
    }

    public function track($courier, $awb) {
        $response = Http::get($this->baseUrl, [
            'api_key' => $this->key,
            'courier' => $courier,
            'awb' => $awb
        ]);

        if($response->successful()) {
            return $response->json();
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to retrieve tracking information.'
            ];
        }
    }
}