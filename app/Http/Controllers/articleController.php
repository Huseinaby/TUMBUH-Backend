<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Article;
use Illuminate\Support\Facades\Http;

class articleController extends Controller
{
    public function index(){
        $articles = Article::all();

        return response()->json([
            'message' => 'semua artikel',
            'data' => $articles
        ]);
    }
    
    public function show($id){
        $article = Article::find($id);

        if(!$article){
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Artikel found',
            'data' => $article
        ]);
    }

    public function destroy($id){
        $article = Article::find($id);

        if(!$article){
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        $article->delete();

        return response()->json([
            'message' => 'Artikel deleted successfully'
        ]);
    }

    public function getByModul($modulId){
        $articles = Article::where('modul_id', $modulId)->get();

        if($articles->isEmpty()) {
            return response()->json([
                'message' => 'No articles found for this module'
            ], 404);
        }

        return response()->json([
            'message' => 'Articles found',
            'data' => $articles
        ]);
    }

    public function generateArticles($articleKeyword, $modulId)
    {
        $googleApiKey = env('GOOGLE_API_KEY');
        $googleCx = env('GOOGLE_CSE_ID');
        $searchResponse = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $googleApiKey,
            'cx' => $googleCx,
            'q' => $articleKeyword,
            'num' => 3,
        ]);

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
            ]);
        }
        return $articles;
    }

    public function generateMoreArticle(Request $request)
    {
        $googleApiKey = env('GOOGLE_API_KEY');
        $googleCx = env('GOOGLE_CSE_ID');
        $searchResponse = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $googleApiKey,
            'cx' => $googleCx,
            'q' => $request->title,
            'num' => 10,
        ]);

        if($searchResponse->successful() && isset($searchResponse['items'])){
            $articles = collect($searchResponse['items'])
            ->filter(fn($item) => isset($item['title'], $item['link'], $item['snippet']))
            ->map(function ($item) {
                return [
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'snippet' => $item['snippet'],
                ];
            });
        }
        else {
            $articles = collect();
        }

        foreach ($articles as $article) {
            Article::create([
                'modul_id' => $request->modulId,
                'title' => $article['title'],
                'link' => $article['link'],
                'snippet' => $article['snippet'],
            ]);
        }
        return response()->json([
            'message' => 'Artikel generated successfully',
            'data' => $articles
        ]);
    }
}
