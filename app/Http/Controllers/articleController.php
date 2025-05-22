<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Article;
use Illuminate\Support\Facades\Http;

class articleController extends Controller
{
    public function index()
    {
        $articles = Article::all();

        return response()->json([
            'message' => 'semua artikel',
            'data' => $articles
        ]);
    }

    public function show($id)
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Artikel found',
            'data' => $article
        ]);
    }

    public function destroy($id)
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        $article->delete();

        return response()->json([
            'message' => 'Artikel deleted successfully'
        ]);
    }

    public function getByModul($modulId)
    {
        $articles = Article::where('modul_id', $modulId)->get();

        if ($articles->isEmpty()) {
            return response()->json([
                'message' => 'No articles found for this module'
            ], 404);
        }

        return response()->json([
            'message' => 'Articles found',
            'data' => $articles
        ]);
    }

    public function generateArticles($title, $modulId)
    {
        $googleApiKey = env('GOOGLE_API_KEY');
        $googleCx = env('GOOGLE_CSE_ID');

        $articleKeywords = [
            'perngertian',
            'menanaman',
            'merawat',
            'ide bisnis',
        ];

        $result = [];

        foreach ($articleKeywords as $keyword) {
            $searchResponse = Http::get('https://www.googleapis.com/customsearch/v1', [
                'key' => $googleApiKey,
                'cx' => $googleCx,
                'q' => $keyword . ' tanaman ' . $title,
                'num' => 3,
            ]);

            if (!$searchResponse->successful()) {
                $result[$keyword] = ['error' => 'Failed to fetch articles' . $keyword];
            }

            $articles = $searchResponse->successful()
                ? collect($searchResponse['items'])->map(function ($item) {
                    return [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'snippet' => $item['snippet'],
                    ];
                })
                : [];

            foreach ($articles as $article) {
                Article::create([
                    'modul_id' => $modulId,
                    'title' => $article['title'],
                    'link' => $article['link'],
                    'snippet' => $article['snippet'],
                    'keyword' => $keyword . ' tanaman ' . $title,
                    'start' => 4,
                ]);
            }

            $result[$keyword] = [
                'articles' => $articles,
                'start' => 4,
                'Keyword' => $keyword . ' tanaman ' . $title,
            ];
        }
        return $result;
    }

    public function generateMoreArticle(Request $request)
    {
        $request->validate([
            'start' => 'required|integer|min:11|max:41',
        ]);

        $modulId = $request->modulId;

        $googleApiKey = env('GOOGLE_API_KEY');
        $googleCx = env('GOOGLE_CSE_ID');

        $searchResponse = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $googleApiKey,
            'cx' => $googleCx,
            'q' => $request->keyword,
            'num' => 3,
            'start' => $request->start,
        ]);

        if (!$searchResponse->successful()) {
            return response()->json([
                'message' => 'Failed to fetch articles',
            'error' => $searchResponse->body()
            ], 500);
        }

        $articles = collect($searchResponse['items'] ?? [])->map(function ($item) use ($modulId) {
            return [
                'modul_id' => $modulId,
                'title' => $item['title'] ?? null,
                'link' => $item['link'] ?? null,
                'snippet' => $item['snippet'] ?? null,
            ];

            if (!Article::where('title', $data['title'])->exists()) {
                Article::create([
                    'modul_id' => $modulId,
                    'title' => $data['title'],
                    'link' => $data['link'],
                    'snippet' => $data['snippet'],
                    'keyword' => $request->keyword,
                    'start' => $request->start + 3,
                ]);
            }

            return $data;
        });


        return response()->json([
            'message' => 'Artikel generated successfully',
            'articles' => $articles,
            'start' => $request->start + 3,
        ]);
    }
}
