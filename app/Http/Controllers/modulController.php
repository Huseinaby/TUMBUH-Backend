<?php

namespace App\Http\Controllers;

use App\Http\Resources\ModulResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\modul;


class modulController extends Controller
{
    public function index()
    {
        $moduls = Modul::with(['modulImage'])
            ->withCount('quiz', 'article', 'video')
            ->get();

        return response()->json([
            'message' => 'semua modul',
            'data' => ModulResource::collection($moduls)
        ]);
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $modul = Modul::create($request->all());

        return response()->json([
            'message' => 'Modul created successfully',
            'data' => $modul
        ], 201);
    }

    public function show($id)
    {
        $modul = Modul::with('modulImage')
            ->find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Modul found',
            'data' => ModulResource::make($modul),
        ]);
    }

    public function update(Request $request, $id)
    {
        $modul = Modul::find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        $validator = validator($request->all(), [
            'title' => 'string|max:255',
            'content' => 'string',
            'category' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $modul->update($request->all());

        return response()->json([
            'message' => 'Modul updated successfully',
            'data' => $modul
        ]);
    }

    public function destroy($id)
    {
        $modul = Modul::find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        $modul->delete();

        return response()->json([
            'message' => 'Modul deleted successfully'
        ]);
    }

    public function generateContent(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $modul = Modul::where('title', $request->title)
            ->with(['modulImage', 'article', 'video', 'quiz'])
            ->first();

        if ($modul) {
            return response()->json([
                'message' => 'Modul sudah ada',
                'data' => $modul
            ]);
        }


        $geminiKey = env('GEMINI_API_KEY');

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

        $checkPrompt = <<<EOT
        Apakah "{$request->title}" merupakan nama dari tanaman?
        
        Jika ya, jawab dalam format JSON valid seperti ini (tanpa penjelasan tambahan):
        
        {
          "isPlant": true,
          "category": "kategori tanaman antara : sayuran, buah, hias, herbal, rempah-rempah",
          "searchKeywords": {
            "video": "kata kunci untuk mencari video tentang tanaman ini dalam bahasa indonesia",
            "article": "kata kunci untuk mencari artikel dalam bahasa indonesia",
            "image": "nama tanaman dalam nama ilmiah"
          },
          "content": "Tuliskan konten edukatif singkat tentang tanaman '{$request->title}'.
        
        Konten harus mencakup:
        - Definisi atau pengenalan tanaman tersebut.
        - Kegunaan atau manfaat tanaman.
        - Cara perawatan dasar tanaman tersebut.
        - Panjang sekitar 2–3 paragraf.
        
        Catatan penting: Jangan awali dengan sapaan seperti 'Halo!', 'Oke, siap!', atau 'Mari kita mulai'. Langsung mulai dengan pembahasan inti.
        Gunakan gaya bahasa informatif dan mudah dipahami oleh pembaca umum. Jangan tambahkan judul di awal konten, cukup tulis konten saja.",
        
        
        Jika bukan tanaman, kembalikan:
        
        {
          "isPlant": false
        }
        
        Jangan beri penjelasan tambahan. Langsung balas hanya dengan JSON.
        EOT;

        $checkResponse = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $checkPrompt]
                ]
            ]
        ]);

        $text = $checkResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (!$text) {
            return response()->json([
                'message' => 'Gagal mendapatkan response dari gemini',
                'raw_response' => $checkResponse->json(),
                'status' => $checkResponse->status(),
            ], 500);
        }

        $cleaned = preg_replace('/```json|```|json|\n/', '', $text);
        $cleaned = trim($cleaned);

        $jsonResult = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'Gagal mengkonversi response ke JSON',
                'raw_response' => $checkResponse->json(),
                'error' => json_last_error_msg()
            ], 500);
        }

        if (!isset($jsonResult['isPlant']) || $jsonResult['isPlant'] !== true) {
            return response()->json([
                'message' => 'Judul bukan nama tanaman, tidak bisa digenerate.',
                'data' => $checkResponse['candidates'][0]['content']['parts'][0]['text'] ?? null
            ], 422);
        }

        $videoKeyword = $jsonResult['searchKeywords']['video'] ?? $request->title;
        $articleKeyword = $jsonResult['searchKeywords']['article'] ?? $request->title;
        $imageKeyword = $jsonResult['searchKeywords']['image'] ?? $request->title;
        $category = $jsonResult['category'] ?? null;
        $generateContent = $jsonResult['content'] ?? null;



        if (!$generateContent) {
            return response()->json([
                'message' => 'Gagal mendapatkan konten'
            ], 500);
        }

        $modul = Modul::create([
            'title' => $request->title,
            'content' => $generateContent,
            'category' => $category,
        ]);

        $imageUrl = $this->fetchImage($imageKeyword);

        foreach ($imageUrl as $url) {
            $modul->modulImage()->create([
                'url' => $url,
            ]);
        }

        $quizController = new quizController();
        $videoController = new videoController();
        $articleController = new articleController();

        $quizzes = $quizController->generateQuiz($modul->id, $generateContent);

        $articleResult = $articleController->generateArticles($articleKeyword, $modul->id);
        $articles = $articleResult['articles'] ?? [];
        $start = $articleResult['start'] ?? null;

        $videoResult = $videoController->generateVideos($videoKeyword, $modul->id);
        $videos = $videoResult['videos'] ?? [];
        $nextPageToken = $videoResult['nextPageToken'] ?? null;

        return response()->json([
            'title' => $request->title,
            'id' => $modul->id,
            'content' => $generateContent,
            'category' => $category,
            'imageKeyword' => $imageKeyword,
            'images' => $imageUrl,
            'article' => [
                'articleKeyword' => $articleKeyword,
                'start' => $start,
                'articles' => $articles,
            ],
            'video' => [
                'videoKeyword' => $videoKeyword,
                'videoNextPageToken' => $nextPageToken,
                'videos' => $videos,
            ],
            'quiz' => $quizzes,
        ]);
    }

    public function fetchImage($imageKeyword)
    {
        $accessKey = env('UNSPLASH_ACCESS_KEY');

        $response = Http::get('https://api.unsplash.com/search/photos', [
            'query' => $imageKeyword,
            'client_id' => $accessKey,
            'per_page' => 5,
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'Gagal mendapatkan gambar dari Unsplash',
                'error' => $response->json(),
                'status' => $response->status(),
            ], 500);
        }

        if ($response->successful() && !empty($response['results'])) {
            return collect($response['results'])->pluck('urls.small')->all();
        }

        return null;
    }

    public function fetchTrefleImage($imageKeyword)
    {
        $token = env('TREFLE_API_TOKEN');

        if (!$token) {
            Log::warning('TREFLE_API_TOKEN belum diset');
            return [];
        }

        Log::info('Mencari gambar dari Trefle untuk Keyword:', ['imageKeyword' => $imageKeyword]);

        try {
            $searchResponse = Http::withToken($token)->get('https://trefle.io/api/v1/plants/search', [
                'q' => $imageKeyword,
            ]);

            if (!$searchResponse->successful()) {
                Log::error('Gagal mengakses Trefle', [
                    'status' => $searchResponse->status(),
                    'body' => $searchResponse->body(),
                ]);
                return [];
            }

            $results = $searchResponse->json('data') ?? [];

            // Ambil hanya hasil yang punya image_url
            $filtered = collect($results)
                ->filter(fn($item) => !empty($item['image_url']))
                ->pluck('image_url')
                ->take(5)
                ->values()
                ->all();

            Log::info('Jumlah gambar ditemukan dari Trefle:', ['count' => count($filtered)]);

            return $filtered;
        } catch (\Exception $e) {
            Log::error('Exception saat fetch image dari Trefle', ['error' => $e->getMessage()]);
            return [];
        }
    }
}

