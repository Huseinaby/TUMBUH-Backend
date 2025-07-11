<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'seller' => [
                'id' => $this->seller->id,
                'seller_id' => $this->seller->sellerDetail->id ?? null,
                'store_name' => $this->seller->sellerDetail->store_name ?? null,
                'store_description' => $this->seller->sellerDetail->store_description ?? null,
                'store_address' => $this->seller->sellerDetail->store_address ?? null,
                'store_phone' => $this->seller->sellerDetail->store_phone ?? null,
                'store_logo' => $this->seller->sellerDetail->store_logo ? asset('storage/' . $this->seller->sellerDetail->store_logo) : null,
            ],
            'items' => CartItemResource::collection($this->items),
        ];
    }
}
