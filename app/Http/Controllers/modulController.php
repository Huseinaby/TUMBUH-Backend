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

    public function update(Request $request, $id){
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

        $prompt = "Tuliskan konten edukatif singkat tentang tanaman dengan judul: \"{$request->title}\".

        Konten harus mencakup:
        - Definisi atau pengenalan tanaman tersebut.
        - Kegunaan atau manfaat tanaman.
        - Cara perawatan dasar tanaman tersebut.
        - Panjang sekitar 2–3 paragraf.
        
        Catatan penting: Jangan awali dengan sapaan seperti 'Halo!', 'Oke, siap!', atau 'Mari kita mulai'. Langsung mulai dengan pembahasan inti.
        Gunakan gaya bahasa informatif dan mudah dipahami oleh pembaca umum. Jangan tambahkan judul diawal konten, cukup tulis konten saja.";


        $response = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]);

        $generateContent = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$generateContent)
            return response()->json([
                'message' => 'Gagal mendapatkan konten'
            ], 500);

        $modul = Modul::create([
            'title' => $request->title,
            'content' => $generateContent,
            'category' => $request->category,
        ]);

        $quizController = new quizController();
        $videoController = new videoController();
        $articleController = new articleController();
        

        $quizzes = $quizController->generateQuiz($modul->id, $generateContent, $url);
        $articles = $articleController->generateArticles($request, $modul->id);
        $videos = $videoController->generateVideos($request, $modul->id);


        return response()->json([
            'content' => $modul,
            'articles' => $articles,
            'videos' => $videos,
            'quiz' => $quizzes,
        ]);
    }
}
