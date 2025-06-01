<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RajaOngkirService
{
    protected $key, $baseUrl;

    public function __construct()
    {
        $this->key = config('services.rajaongkir.api_key');
        $this->baseUrl = config('services.rajaongkir.base_url');
    }

    public function searchDestination($search = '', $limit = 10, $offset = 0)
    {
        $client = new \GuzzleHttp\Client();

        $response = $client->request('GET', "{$this->baseUrl}/destination/domestic-destination", [
            'query' => [
                'search' => $search,
                'limit' => $limit,
                'offset' => $offset
            ],
            'headers' => [
                'key' => $this->key,
                'accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function calculateDomesticCost($origin, $destination, $weight, $courier = 'jne', $price = 'lowest')
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost', [
            'form_params' => [
                'origin' => (string) $origin,
                'destination' => (string) $destination,
                'weight' => (int) $weight,
                'courier' => (string) $courier,
                'price' => (string) $price
            ],
            'headers' => [
                'accept' => 'application/json',
                'key' => config('services.rajaongkir.api_key'),
                // Hapus content-type karena form_params akan mengatur otomatis
            ],
        ]);
        return json_decode($response->getBody(), true);
    }




}
