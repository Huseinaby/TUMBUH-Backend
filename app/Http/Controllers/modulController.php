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

        $quizzes = $this->generateQuiz($modul->id, $generateContent, $url);
        $articles = $this->generateArticles($request, $modul->id);
        $videos = $this->generateVideos($request, $modul->id);


        return response()->json([
            'content' => $modul,
            'articles' => $articles,
            'videos' => $videos,
            'quiz' => $quizzes,
        ]);
    }

    public function generateQuiz($modulId, $generateContent, $url)
    {

        $quizPromt = "Buatkan 3 soal pilihan ganda berdasarkan bacaan berikut:\n\n\"{$generateContent}\"\n\nFormat JSON:\n" .
            '[{"question":"...","option_a":"...","option_b":"...","option_c":"...","option_d":"...","correct_answer":"a"}]';

        $quizResponse = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $quizPromt]
                ]
            ]
        ]);
        $quizText = $quizResponse['candidates'][0]['content']['parts'][0]['text'] ?? 'Soal tidak ditemukan';

        $quizText = preg_replace('/```json|```/', '', $quizText);
        $quizText = trim($quizText);

        try {
            $quizzes = json_decode($quizText, true);
            foreach ($quizzes as $q) {
                Quiz::create([
                    'modul_id' => $modulId,
                    'question' => $q['question'],
                    'option_a' => $q['option_a'],
                    'option_b' => $q['option_b'],
                    'option_c' => $q['option_c'],
                    'option_d' => $q['option_d'],
                    'correct_answer' => $q['correct_answer'],
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menyimpan soal',
                'error' => $e->getMessage()
            ], 500);
        }

        return $quizzes;
    }

    public function generateArticles(Request $request, $modulId)
    {
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

    public function generateVideos(Request $request, $modulId)
    {
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

        foreach ($videos as $video) {
            Video::create([
                'modul_id' => $modulId,
                'title' => $video['title'],
                'link' => $video['url'],
                'thumbnail' => $video['thumbnail'],
            ]);
        }

        return $videos;
    }
}
