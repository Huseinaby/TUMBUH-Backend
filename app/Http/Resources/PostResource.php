<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'group_id' => $this->group_id,
            'title' => $this->title,
            'content' => $this->content,            
            'liked_count' => $this->likedBy->count(),
            'comments_count' => $this->comments->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return asset('storage/' . $image->path);
                });
            }),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->username,
                'email' => $this->user->email,
                'photo' => $this->user->photo ? asset('storage/' . $this->user->photo) : null,
            ],
        ];
    }
}
