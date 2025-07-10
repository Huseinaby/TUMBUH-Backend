<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $avarage = Review::where('product_id', $this->id)
            ->avg('rating');
        $count = Review::where('product_id', $this->id)
            ->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'weight' => $this->weight,
            'stock' => $this->stock,
            'rating' => $this->reviews->isEmpty() ? 0 : round($avarage, 1),
            'rating_count' => $count,
            'category' => $this->productCategories ? [
                'id' => $this->productCategories->id,
                'name' => $this->productCategories->name,
            ] : null,
            'province' => $this->province ? [
                'id' => $this->province->id,
                'name' => $this->province->name,
            ] : null,
            'seller' => [
                'id' => $this->user->id,
                'seller_id' => $this->user->sellerDetail ? $this->user->sellerDetail->id : null,
                'store_name' => $this->user->sellerDetail ? $this->user->sellerDetail->store_name : null,
                'store_description' => $this->user->sellerDetail ? $this->user->sellerDetail->store_description : null,
                'store_address' => $this->user->sellerDetail ? $this->user->sellerDetail->store_address : null,
                'store_phone' => $this->user->sellerDetail ? $this->user->sellerDetail->store_phone : null,
                'store_logo' => $this->user->sellerDetail ? asset('storage/' . $this->user->sellerDetail->store_logo) : null,                
            ],
            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_path' => asset('storage/'. $image->image_path),
                ];
            }),
            'reviews' => $this->reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'user_id' => $review->user_id,
                    'imageUser' => $review->user->photo ? asset( $review->user->photo) : null,
                    'username' => $review->user->username,
                    'rating' => $review->rating,
                    'date' => $review->created_at->format('Y-m-d'),
                    'comment' => $review->comment,
                ];
            }),
        ];
    }
}
