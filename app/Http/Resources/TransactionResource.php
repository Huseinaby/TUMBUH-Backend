<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'seller_id' => $this->seller_id,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'shipping_service' => $this->shipping_service,
            'shipping_name' => $this->shipping_name,
            'shipping_cost' => $this->shipping_cost,
            'invoice_url' => $this->invoice_url,
            'midtrans_order_id' => $this->midtrans_order_id,
            'expired_at' => $this->expired_at,
            'platform_fee' => $this->platform_fee,
            'paid_at' => $this->paid_at,
            'resi_number' => $this->resi_number,
            'shipping_status' => $this->shipping_status,
            'seller' => [
                'id' => $this->seller->id,
                'seller_id' => $this->seller->sellerDetail->id ?? null,
                'store_name' => $this->seller->sellerDetail->store_name ?? null,
                'store_description' => $this->seller->sellerDetail->store_description ?? null,
                'store_address' => $this->seller->sellerDetail->store_address ?? null,
                'store_phone' => $this->seller->sellerDetail->store_phone ?? null,
                'store_logo' => $this->seller->sellerDetail->store_logo ? asset('storage/' . $this->seller->sellerDetail->store_logo) : null,                
            ],
            'images' => [
                'id' => $this->images->id ?? null,
                'image_path' => $this->images->image_path ? asset('storage/' . $this->images->image_path) : null,
            ],
            'reviews' => [
                'id' => $this->reviews->id ?? null,
                'rating' => $this->reviews->rating ?? null,
                'comment' => $this->reviews->comment ?? null,
                'created_at' => $this->reviews->created_at ? $this->reviews->created_at->format('Y-m-d H:i:s') : null,
            ],
        ];
    }
}
