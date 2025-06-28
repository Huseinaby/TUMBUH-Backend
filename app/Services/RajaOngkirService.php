<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RajaOngkirService
{
    protected $key, $baseUrl;

    public function __construct()
    {
        $this->key = config('services.rajaongkir.api_key');
        $this->baseUrl = rtrim(config('services.rajaongkir.base_url'), '/');
    }

    /**
     * Search destinasi tujuan dari Komship.
     */
    public function searchDestination($search = '', $limit = 10, $offset = 0)
    {
        $client = new \GuzzleHttp\Client();
    
        $response = $client->request('GET', "{$this->baseUrl}/destination/search", [
            'query' => [
                'keyword' => $search, // ✅ Ganti 'search' jadi 'keyword'
                // 'limit' dan 'offset' tidak diperlukan jika tidak didukung oleh endpoint ini
            ],
            'headers' => [
                'x-api-key' => $this->key,
                'accept' => 'application/json',
            ],
        ]);
    
        return json_decode($response->getBody(), true);
    }
    

    /**
     * Hitung ongkir berdasarkan Komship (GET request dengan query).
     */
    public function calculateDomesticCost($shipperId, $receiverId, $weight, $itemValue = 0, $cod = 'no')
    {
        $client = new \GuzzleHttp\Client();

        $response = $client->request('GET', "{$this->baseUrl}/calculate", [
            'query' => [
                'shipper_destination_id' => $shipperId,
                'receiver_destination_id' => $receiverId,
                'weight' => $weight,
                'item_value' => $itemValue,
                'cod' => $cod,
            ],
            'headers' => [
                'x-api-key' => $this->key,
                'accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}
