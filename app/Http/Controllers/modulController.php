<?php

namespace App\Http\Controllers;

use Google\Service\CloudTrace\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\modul;

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
        $geminiKey = env('GEMINI_API_KEY');

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

        $prompt = "Tuliskan konten edukatif singkat tentang tanaman dengan judul: \"{$request->title}\".

        Konten harus mencakup:
        - Definisi atau pengenalan tanaman tersebut.
        - Kegunaan atau manfaat tanaman.
        - Cara perawatan dasar tanaman tersebut.
        - Panjang sekitar 2–3 paragraf.
        
        Catatan penting: Jangan awali dengan sapaan seperti 'Halo!', 'Oke, siap!', atau 'Mari kita mulai'. Langsung mulai dengan pembahasan inti.
        Gunakan gaya bahasa informatif dan mudah dipahami oleh pembaca umum.";


        $response = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]);

           $genetareContent = $response['candidates'][0]['content']['parts'][0]['text'] ?? 'Konten tidak ditemukan';

        $googleApiKey = env('GOOGLE_API_KEY');
        $googleCx = env('GOOGLE_CSE_ID');
        $searchResponse = Http::get('https://www.googleapis.com/customsearch/v1', [
            'key' => $googleApiKey,
            'cx' => $googleCx,
            'q' => $request->title,
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

        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => $request->title,
            'type' => 'video',
            'maxResults' => 3,
            'key' => $youtubeApiKey,
        ]);

        $videos = collect($videoResponse['items'])->map(function ($item) {
            return [
                'title' => $item['snippet']['title'],
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                'videoId' => $item['id']['videoId'],
                'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId'],
            ];
        });

        return response()->json([
            'content' => $genetareContent,
            'articles' => $articles,
            'videos' => $videos,
        ]);
    }
}
