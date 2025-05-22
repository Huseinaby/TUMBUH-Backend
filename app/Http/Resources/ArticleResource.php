<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ArticleResource extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->collection->groupBy('category')->map(function ($articles, $category) {
            return [
                'articles' => $articles->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'modul_id' => $article->modul_id,
                        'title' => $article->title,
                        'link' => $article->link,
                        'snippet' => $article->snippet,
                        'category' => $article->category,
                        'keyword' => $article->keyword,
                        'start' => $article->start,
                        'created_at' => $article->created_at,
                        'updated_at' => $article->updated_at,
                    ];
                })->values()
            ];
        })->toArray();
    }
}

