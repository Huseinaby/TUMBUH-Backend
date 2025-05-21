<?php

namespace App\Http\Controllers;

use App\Http\Resources\ModulResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\modul;
use Illuminate\Support\Facades\Auth;


class ModulController extends Controller
{
    public function index()
    {
        $moduls = Modul::with(['modulImage', 'user'])
            ->withCount('quiz','article', 'video')
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

        $validator['user_id'] = Auth::user()->id;

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
        $modul = Modul::with(['modulImage', 'user'])
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
            'data' => ModulResource::make($modul),
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

    public function getModulByUser($userId)
    {
        $moduls = Modul::where('user_id', $userId)
            ->with(['modulImage'])
            ->withCount('quiz', 'article', 'video')
            ->get();

        return response()->json([
            'message' => 'Modul by user',
            'data' => ModulResource::collection($moduls)
        ]);
    }

    public function generateContent(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $modul = Modul::where('title', $request->title)
            ->with(['modulImage','user', 'article', 'video', 'quiz'])
            ->first();

        if ($modul) {
            return response()->json([
                'message' => 'Modul sudah ada',
                'data' => $modul
            ]);
        }

        $userId = Auth::user()->id;


        $geminiKey = env('GEMINI_API_KEY');

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

        $checkPrompt = <<<EOT
        Apakah "{$request->title}" merupakan nama dari tanaman?
        
        Jika ya, jawab dalam format JSON valid seperti ini (tanpa penjelasan tambahan):
        
        {
          "isPlant": true,
          "category": "kategori tanaman antara : sayuran, buah, hias, herbal, rempah-rempah",
          "image": "nama tanaman dalam bahasa inggris",
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

        $imageKeyword = 'Image of ' . $jsonResult['image'];
        $category = $jsonResult['category'] ?? null;
        $generateContent = $jsonResult['content'] ?? null;

        if (!$generateContent) {
            return response()->json([
                'message' => 'Gagal mendapatkan konten'
            ], 500);
        }

        $modul = Modul::create([
            'title' => $request->title,
            'user_id' => $userId,
            'content' => $generateContent,
            'category' => $category,
        ]);

        $imageUrl = $this->fetchGoogleImages($imageKeyword);

        foreach ($imageUrl as $url) {
            $modul->modulImage()->create([
                'url' => $url,
            ]);
        }

        $quizController = new quizController();
        $videoController = new videoController();
        $articleController = new articleController();

        // $quizzes = $quizController->generateQuiz($modul->id, $generateContent);

        $articleResult = $articleController->generateArticles( $request->title, $modul->id);

        $videoResult = $videoController->generateVideos( $request->title,$modul->id);

        return response()->json([
            'title' => $request->title,
            'id' => $modul->id,
            'user_id' => $userId,
            'content' => $generateContent,
            'category' => $category,
            'imageKeyword' => $imageKeyword,
            'images' => $imageUrl,
            'article' => $articleResult,
            'videos' => $videoResult,
            // 'quiz' => $quizzes,
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

    public function fetchGoogleImages($imageKeyword){
        $apikey = env('GOOGLE_API_KEY');
        $cseId = env('GOOGLE_CSE_ID');

        if(!$apikey || !$cseId){
            Log::warning('Google API key or CSE ID not set in .env file');
            return [];
        }

        try {
            $response = Http::get('https://www.googleapis.com/customsearch/v1', [
                'key' => $apikey,
                'cx' => $cseId,
                'q' => $imageKeyword,
                'searchType' => 'image',
                'num' => 5,
            ]);

            if(!$response->successful()){
                Log::error('Google API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $items = $response->json('items') ?? [];

            $images = collect($items)->pluck('link')->filter()->values()->all();

            return $images;
        } catch (\Exception $e) {
            Log::error('Error fetching images from Google API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    public function favoriteUser(Request $request, $id){
        $user = $request->user();
        $modul = Modul::findOrFail($id);

        if($user->favoriteModul()->where('modul_id', $id)->exists()) {
            $user->favoriteModul()->detach($id);
            return response()->json([
                'status' => 'unfavorited',
                'message' => 'Modul dihapus dari favorit.'
            ]);
        } else {
            $user->favoriteModul()->attach($id);
            return response()->json([
                'status' => 'favorited',
                'message' => 'Modul ditambahkan ke favorit.'
            ]);
        }
    }

    public function getFavoriteModul(Request $request)
    {
        $user = $request->user();
        $favoriteModuls = $user->favoriteModul()->get();

        return response()->json([
            'data' => $favoriteModuls
        ]);
    }
}

