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
                    'category' => $keyword,
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
            'modulId' => 'required|integer|exists:moduls,id',
            'start' => 'required|integer',
            'keyword' => 'required|string',
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
        });

        $categories = explode(' ', $request->keyword);
        $category = implode(' ', array_slice($categories, 0, -2));

        foreach($articles as $article) {
            Article::create([
                'modul_id' => $modulId,
                'title' => $article['title'],
                'link' => $article['link'],
                'snippet' => $article['snippet'],
                'category' => $category,
                'keyword' => $request->keyword,
                'start' => $request->start + 3,
            ]);
        }


        return response()->json([
            'message' => 'Artikel generated successfully',
            'articles' => $articles,
            'start' => $request->start + 3,
        ]);
    }
}
