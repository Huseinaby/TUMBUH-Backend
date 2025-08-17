<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return  [
            'id' => $this->id,
            'name' => $this->username,
            'photo' => $this->photo ? asset('storage/' . $this->photo) : null,
            'groups' => $this->groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'cover_image' => $group->cover_image ? asset('storage/' . $group->cover_image) : null,
                    'created_at' => $group->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'posts' => $this->posts->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                    'image' => $post->images->isNotEmpty() ? asset('storage/' . $post->images->first()->image_path) : null,
                    'created_at' => $post->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    }
}
