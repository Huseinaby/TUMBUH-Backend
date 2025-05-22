<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class VideoResource extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection->groupBy('category')->map(function ($videos, $category) {
            return [
                'articles' => $videos->map(function ($video) {
                    return [
                        'id' => $video->id,
                        'modul_id' => $video->modul_id,
                        'title' => $video->title,
                        'description' => $video->description,
                        'creator' => $video->creator,
                        'duration' => $video->duration,
                        'link' => $video->link,
                        'thumbnail' => $video->thumbnail,
                        'category' => $video->category,
                        'keyword' => $video->keyword,
                        'nextPageToken' => $video->nextPageToken,
                    ];
                })->values()
            ];
        })->toArray();
    }
}

