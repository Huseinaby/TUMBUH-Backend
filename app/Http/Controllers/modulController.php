<?php

namespace App\Http\Controllers;

use Google\Service\CloudTrace\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\modul;
use App\Models\Quiz;
use App\Models\Article;
use App\Models\Video;

class modulController extends Controller
{
    public function index()
    {
        $moduls = Modul::all();

        return response()->json([
            'message' => 'semua modul',
            'data' => $moduls
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
        $modul = Modul::find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Modul found',
            'data' => $modul
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
            'category' => 'required|string|max:255',
        ]);

        $geminiKey = env('GEMINI_API_KEY');

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

        // Prompt untuk validasi tanaman dan generate keyword
        $checkPrompt = "
Apakah  \"{$request->title}\"

merupakan nama dari tanaman. Jika ya, jawab dengan format JSON seperti ini:

{
  \"isPlant\": true,
  \"searchKeywords\": {
    \"video\": \"isi kata kunci video\",
    \"article\": \"isi kata kunci artikel\"
    \"image\" : \"nama tanaman dalam bahasa inggris atau nama ilmiah\"
  }
}

Jika bukan tanaman, kembalikan:

{
  \"isPlant\": false
}

Jangan tambahkan penjelasan, langsung beri JSON saja.
";

        $checkResponse = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $checkPrompt]
                ]
            ]
        ]);

        $text = $checkResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $cleaned = preg_replace('/```json|```|json|\n/', '', $text);
        $cleaned = trim($cleaned);

        $jsonResult = json_decode($cleaned, true);

        if (!$jsonResult || !isset($jsonResult['isPlant']) || $jsonResult['isPlant'] !== true) {
            return response()->json([
                'message' => 'Judul bukan nama tanaman, tidak bisa digenerate.',
                'data' => $checkResponse['candidates'][0]['content']['parts'][0]['text'] ?? null
            ], 422);
        }

        $videoKeyword = $jsonResult['searchKeywords']['video'] ?? $request->title;
        $articleKeyword = $jsonResult['searchKeywords']['article'] ?? $request->title;
        $imageKeyword = $jsonResult['searchKeywords']['image'] ?? $request->title;


        $contentPrompt = "
Tuliskan konten edukatif singkat tentang tanaman dengan judul: \"{$request->title}\".

Konten harus mencakup:
- Definisi atau pengenalan tanaman tersebut.
- Kegunaan atau manfaat tanaman.
- Cara perawatan dasar tanaman tersebut.
- Panjang sekitar 2–3 paragraf.

Catatan penting: Jangan awali dengan sapaan seperti 'Halo!', 'Oke, siap!', atau 'Mari kita mulai'. Langsung mulai dengan pembahasan inti.
Gunakan gaya bahasa informatif dan mudah dipahami oleh pembaca umum. Jangan tambahkan judul diawal konten, cukup tulis konten saja.
";

        $response = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $contentPrompt]
                ]
            ]
        ]);

        $generateContent = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$generateContent) {
            return response()->json([
                'message' => 'Gagal mendapatkan konten'
            ], 500);
        }

        $imageUtl = $this->fetchImage($imageKeyword);

        $modul = Modul::create([
            'title' => $request->title,
            'content' => $generateContent,
            'category' => $request->category,
        ]);

        $quizController = new quizController();
        $videoController = new videoController();
        $articleController = new articleController();

        $quizzes = $quizController->generateQuiz($modul->id, $generateContent, $url);
        $articles = $articleController->generateArticles($articleKeyword, $modul->id);
        $videos = $videoController->generateVideos($videoKeyword, $modul->id);

        return response()->json([
            'content' => $modul,
            'image' => $imageUtl,
            'articles' => $articles,
            'videos' => $videos,
            'quiz' => $quizzes,
            'videoKeyword' => $videoKeyword,
            'articleKeyword' => $articleKeyword,
        ]);
    }

    public function fetchImage($imageKeyword)
    {
        $accessKey = env('UNSPLASH_ACCESS_KEY');

        $response = Http::get('https://api.unsplash.com/search/photos', [
            'query' => $imageKeyword,
            'client_id' => $accessKey,
            'per_page' => 1
        ]);

        if ($response->successful() && !empty($response['results'])) {
            return $response['results'][0]['urls']['small'];
        }

        return null;
    }
}

