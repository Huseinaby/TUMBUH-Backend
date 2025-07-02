<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cart_id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'selected' => true,
            'subTotal' => $this->product->price * $this->quantity,
            'product' => [
                'name' => $this->product->name,
                'price' => $this->product->price,
                'stock' => $this->product->stock,
                'image' => $this->product->images->first()
                    ? asset($this->product->images->first()->image_path)
                    : null,
            ]

        ];
    }
}
