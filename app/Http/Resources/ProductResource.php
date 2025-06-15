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
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role,
                'photo' => $this->user->photo,
            ],
            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_path' => asset('storage/' . $image->image_path),
                ];
            }),
        ];
    }
}
