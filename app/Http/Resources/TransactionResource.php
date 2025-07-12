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
            'order_items' => $this->orderItems ? $this->orderItems->map(function ($orderItem) {
                return [
                    'id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'subtotal' => $orderItem->subtotal,
                    'product_name' => $orderItem->product->name,
                    'product_image' => $orderItem->product->images->first() ? asset('storage/' . $orderItem->product->images->first()->image_path) : null,
                ];
            }) : [],
            'images' => $this->orderItems ? $this->orderItems->map(function ($orderItems) {
                return [
                    'id' => $orderItems->product->id,
                    'image_path' => $orderItems->product->images->first() ? asset('storage/' . $orderItems->product->images->first()->image_path) : null,
                ];
            }) : [],
            'reviews' => $this->reviews ? $this->reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at ? $review->created_at->format('Y-m-d H:i:s') : null,
                ];
            }) : [],
        ];
    }
}
