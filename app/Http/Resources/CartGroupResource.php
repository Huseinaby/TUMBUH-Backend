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
                'name' => $this->seller->name,
            ],
            'items' => CartItemResource::collection($this->items),
        ];
    }
}
