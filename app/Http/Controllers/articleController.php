<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArticleResource;
use Illuminate\Http\Request;
use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class articleController extends Controller
{
    public function index()
    {
        $articles = Article::all();

        return response()->json([
            'message' => 'semua artikel',
            'data' => new ArticleResource($articles),
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
            'pengertian',
            'menanam',
            'merawat',
            'ide bisnis',
        ];
    
        $result = [];
    
        foreach ($articleKeywords as $keyword) {
            $relevantArticles = collect();
            $start = 1;
            $maxArticles = 4;
            $attemptLimit = 5; // Max 5 page attempt (Google CSE paginasi)
            $attemptCount = 0;
    
            while ($relevantArticles->count() < $maxArticles && $attemptCount < $attemptLimit) {
                $query = $keyword . ' tanaman ' . $title . ' -filetype:pdf -filetype:doc -filetype:docx -site:researchgate.net -site:jstor.org';
    
                $searchResponse = Http::get('https://www.googleapis.com/customsearch/v1', [
                    'key' => $googleApiKey,
                    'cx' => $googleCx,
                    'q' => $query,
                    'num' => 4,
                    'start' => $start,
                ]);
    
                if (!$searchResponse->successful()) {
                    $result[$keyword] = ['error' => 'Failed to fetch articles: ' . $keyword];
                    break; // Stop trying for this keyword
                }
    
                $articlesRaw = collect($searchResponse['items'])->filter(function ($item) {
                    $link = strtolower($item['link']);
                    $title = strtolower($item['title']);
                    return !str_contains($link, '.pdf')
                        && !str_contains($link, '.doc')
                        && !str_contains($link, '.docx')
                        && !str_contains($link, 'researchgate.net')
                        && !str_contains($link, 'jstor.org')
                        && !str_contains($link, 'youtube.com')
                        && !str_contains($title, 'jurnal')
                        && !str_contains($title, 'journal');
                })->map(function ($item) {
                    return [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'snippet' => $item['snippet'],
                    ];
                });
    
                foreach ($articlesRaw as $article) {
                    if ($relevantArticles->count() >= $maxArticles) {
                        break;
                    }
    
                    $isRelevant = $this->isArticleRelevant(
                        $article['title'],
                        $article['snippet'],
                        $article['link'],
                        $keyword,
                        $title
                    );
    
                    if ($isRelevant) {
                        // Save to DB
                        Article::create([
                            'modul_id' => $modulId,
                            'title' => $article['title'],
                            'link' => $article['link'],
                            'snippet' => $article['snippet'],
                            'category' => $keyword,
                            'keyword' => $keyword . ' tanaman ' . $title,
                            'start' => $start,
                        ]);
    
                        $relevantArticles->push($article);
                    } else {
                        // Optional: log artikel yang ditolak
                        Log::info('Artikel tidak relevan, discarding', [
                            'title' => $article['title'],
                            'link' => $article['link']
                        ]);
                    }
                }
    
                // Move to next page if needed
                $start += 4;
                $attemptCount++;
            }
    
            $result[$keyword] = [
                'articles' => $relevantArticles->values(),
                'start' => $start,
                'keyword' => $keyword . ' tanaman ' . $title,
            ];
        }
    
        return $result;
    }

    
    public function isArticleRelevant($title, $snippet, $link, $keyword, $tanamanTitle)
{
    $geminiKey = env('GEMINI_API_KEY');
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

    $prompt = <<<EOT
Berikut adalah sebuah artikel hasil pencarian:

Judul: {$title}
Snippet: {$snippet}
Link: {$link}

Tanaman yang sedang dibahas: {$tanamanTitle}
Kategori pencarian: {$keyword}

Apakah artikel ini RELEVAN untuk aplikasi Tumbuh (aplikasi edukasi tanaman untuk masyarakat umum, bukan jurnal akademik atau artikel yang sulit diakses)? 

Jawab HANYA dalam format JSON valid berikut:

{
    "relevance": "RELEVAN" atau "TIDAK RELEVAN"
}

EOT;

    // Kirim permintaan ke Gemini API
    $response = Http::post($url, [
        'contents' => [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ]);

    try {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Bersihkan ```json
        $cleaned = trim($text);

        if (str_starts_with($cleaned, '```json')) {
            $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        }

        $parsed = json_decode($cleaned, true);

        // Debug jika gagal
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Gagal parse JSON dari Gemini (Relevance Check)', [
                'error' => json_last_error_msg(),
                'original_text' => $text,
                'cleaned_text' => $cleaned,
            ]);
            return false; // Anggap tidak relevan kalau parsing gagal
        }

        if (isset($parsed['relevance'])) {
            $relevance = strtolower($parsed['relevance']);
            return $relevance === 'relevan';
        } else {
            Log::warning('Field relevance tidak ditemukan pada response Gemini', [
                'text' => $text,
                'cleaned' => $cleaned,
                'parsed' => $parsed,
            ]);
            return false;
        }

    } catch (\Exception $e) {
        Log::error('Error checking article relevance: ' . $e->getMessage());
        return false;
    }
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

        foreach ($articles as $article) {
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
