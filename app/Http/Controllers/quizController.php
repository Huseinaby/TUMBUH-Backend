<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\modul;
use Illuminate\support\Facades\Http;
use App\Models\Quiz;
use App\Models\QuizProgress;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class quizController extends Controller
{
    //
    public function index()
    {
        $quizzes = Quiz::all();

        return response()->json([
            'message' => 'semua quiz',
            'data' => $quizzes
        ]);
    }

    public function show($id)
    {
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json([
                'message' => 'Quiz not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Quiz found',
            'data' => $quiz
        ]);
    }

    public function destroy($id)
    {
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json([
                'message' => 'Quiz not found'
            ], 404);
        }

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully'
        ]);
    }

    public function getByModul($modulId)
    {
        $quizzes = Quiz::where('modul_id', $modulId)->get();

        if ($quizzes->isEmpty()) {
            return response()->json([
                'message' => 'No quizzes found for this module'
            ], 404);
        }

        return response()->json([
            'message' => 'Quizzes found',
            'quiz' => $quizzes
        ]);
    }

    public function generateQuiz($modulId, $articles)
    {

        $geminiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

        $links = $this->decodeArticles($articles);

        $linkTexts = "";
        foreach ($links as $kategori => $daftarLink) {
            $linkTexts .= strtoupper($kategori) . ":\n";
            foreach ($daftarLink as $link) {
                $linkTexts .= "- {$link} \n";
            }
            $linkTexts .= "\n";
        }

        $quizPrompt = <<<EOT
        Buatkan 10 soal pilihan ganda berdasarkan bacaan berikut:
        
        {$linkTexts}
        
        Gunakan isi dari artikel-artikel di atas untuk membuat 10 soal pilihan ganda sebagai berikut:

        - 10 soal dari kategori Pengertian (basic)
        - 10 soal dari kategori Menanam (easy)
        - 10 soal dari kategori Merawat (medium)
        - 10 soal dari kategori Menghasilkan Keuntungan (hard)
        
        Tingkat kesulitan soal:
        - basic: berdasarkan pengertian langsung dari bacaan
        - easy: berdasarkan instruksi praktis menanam
        - medium: membutuhkan pemahaman dalam perawatan
        - hard: berdasarkan analisis ide bisnis atau keuntungan        

        Jawab hanya dalam format JSON valid seperti ini:
       
        [
          {
            "difficulty": "easy",
            "question": "Apa manfaat utama dari tanaman ini?",
            "option_a": "Untuk dekorasi",
            "option_b": "Sebagai sumber makanan",
            "option_c": "Untuk obat-obatan",
            "option_d": "Untuk penelitian",
            "correct_answer": "c"
          }
        ]
        EOT;

        // Kirim permintaan ke Gemini API
        $quizResponse = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $quizPrompt]
                ]
            ]
        ]);

        try {

            $text = $quizResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Bersihkan triple backtick dan label ```json dari awal dan akhir teks
            $cleaned = trim($text);

            // Hapus pembuka ```json di awal dan penutup ``` di akhir
            if (str_starts_with($cleaned, '```json')) {
                $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
                $cleaned = preg_replace('/\s*```$/', '', $cleaned);
            }

            // Decode JSON
            $quizzes = json_decode($cleaned, true);

            // Debug jika gagal
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Gagal parse JSON dari Gemini', [
                    'error' => json_last_error_msg(),
                    'original_text' => $text,
                    'cleaned_text' => $cleaned,
                ]);
                return [];
            }

            if (!$quizzes || !is_array($quizzes)) {
                // Log untuk debugging
                Log::error('Failed to parse quiz JSON', [
                    'text' => $text,
                    'cleaned' => $cleaned
                ]);

                return [];
            }

            // Simpan quiz ke database
            $savedQuizzes = [];
            foreach ($quizzes as $quizData) {
                try {
                    $quiz = Quiz::create([
                        'modul_id' => $modulId,
                        'difficulty' => $quizData['difficulty'],
                        'question' => $quizData['question'],
                        'option_a' => $quizData['option_a'],
                        'option_b' => $quizData['option_b'],
                        'option_c' => $quizData['option_c'],
                        'option_d' => $quizData['option_d'],
                        'correct_answer' => $quizData['correct_answer'],
                    ]);
                    $savedQuizzes[] = $quiz;
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Error saving quiz',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            return $savedQuizzes;
        } catch (\Exception $e) {
            Log::error('Error generating quiz: ' . $e->getMessage() . $articles . $links);
            return response()->json([
                'message' => 'Error generating quiz',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getArticlesForQuiz($modulId)
    {
        $categories = ['pengertian', 'menanam', 'merawat', 'ide bisnis'];

        $articles = Article::where('modul_id', $modulId)
            ->whereIn('category', $categories)
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('category')
            ->map(fn($items) => ['articles' => $items]);

        return $articles;
    }

    public function createQuiz($modulId)
    {
        $quizzes = Quiz::where('modul_id', $modulId);

        if ($quizzes->exists()) {
            return response()->json([
                'message' => 'Quiz for this module already exists',
                'quiz' => $quizzes->get()
            ], 400);
        }

        $geminiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";

        $articles = $this->getArticlesForQuiz($modulId);

        $links = $this->decodeArticles($articles);

        $linkTexts = "";
        foreach ($links as $kategori => $daftarLink) {
            $linkTexts .= strtoupper($kategori) . ":\n";
            foreach ($daftarLink as $link) {
                $linkTexts .= "- {$link} \n";
            }
            $linkTexts .= "\n";
        }

        $quizPrompt = <<<EOT
        Buatkan 10 soal pilihan ganda berdasarkan bacaan berikut:
        
        {$linkTexts}
        
        Gunakan isi dari artikel-artikel di atas untuk membuat 10 soal pilihan ganda sebagai berikut:

        - 10 soal dari kategori Pengertian (basic)
        - 10 soal dari kategori Menanam (easy)
        - 10 soal dari kategori Merawat (medium)
        - 10 soal dari kategori Menghasilkan Keuntungan (hard)
        
        Tingkat kesulitan soal:
        - basic: berdasarkan pengertian langsung dari bacaan
        - easy: berdasarkan instruksi praktis menanam
        - medium: membutuhkan pemahaman dalam perawatan
        - hard: berdasarkan analisis ide bisnis atau keuntungan        

        Jawab hanya dalam format JSON valid seperti ini:
       
        [
          {
            "difficulty": "easy",
            "question": "Apa manfaat utama dari tanaman ini?",
            "option_a": "Untuk dekorasi",
            "option_b": "Sebagai sumber makanan",
            "option_c": "Untuk obat-obatan",
            "option_d": "Untuk penelitian",
            "correct_answer": "c"
          }
        ]
        EOT;

        // Kirim permintaan ke Gemini API
        $quizResponse = Http::post($url, [
            'contents' => [
                'parts' => [
                    ['text' => $quizPrompt]
                ]
            ]
        ]);

        try {

            $text = $quizResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Bersihkan triple backtick dan label ```json dari awal dan akhir teks
            $cleaned = trim($text);

            // Hapus pembuka ```json di awal dan penutup ``` di akhir
            if (str_starts_with($cleaned, '```json')) {
                $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
                $cleaned = preg_replace('/\s*```$/', '', $cleaned);
            }

            // Decode JSON
            $quizzes = json_decode($cleaned, true);

            // Debug jika gagal
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Gagal parse JSON dari Gemini', [
                    'error' => json_last_error_msg(),
                    'original_text' => $text,
                    'cleaned_text' => $cleaned,
                ]);
                return [];
            }

            if (!$quizzes || !is_array($quizzes)) {
                // Log untuk debugging
                Log::error('Failed to parse quiz JSON', [
                    'text' => $text,
                    'cleaned' => $cleaned
                ]);

                return [];
            }

            // Simpan quiz ke database
            $savedQuizzes = [];
            foreach ($quizzes as $quizData) {
                try {
                    $quiz = Quiz::create([
                        'modul_id' => $modulId,
                        'difficulty' => $quizData['difficulty'],
                        'question' => $quizData['question'],
                        'option_a' => $quizData['option_a'],
                        'option_b' => $quizData['option_b'],
                        'option_c' => $quizData['option_c'],
                        'option_d' => $quizData['option_d'],
                        'correct_answer' => $quizData['correct_answer'],
                    ]);
                    $savedQuizzes[] = $quiz;
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Error saving quiz',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            return $response = response()->json([
                'message' => 'Quiz generated and saved successfully',
                'quiz' => $savedQuizzes
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error generating quiz: ' . $e->getMessage() . $articles . $links);
            return response()->json([
                'message' => 'Error generating quiz',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function decodeArticles($articles)
    {
        $result = [];

        foreach ($articles as $kategoriNama => $kategoriData) {
            if (!isset($kategoriData['articles'])) {
                continue;
            }

            foreach ($kategoriData['articles'] as $article) {
                if (isset($article['link'])) {
                    $result[$kategoriNama][] = $article['link'];
                }
            }
        }

        return $result;
    }

    public function getProgress($userId)
    {
        $progressList = QuizProgress::where('user_id', $userId)
            ->get()
            ->groupBy('modul_id');

        $result = [
            'userId' => (string) $userId,
            'modul' => [],
        ];

        foreach ($progressList as $modulId => $progressPerModul) {
            $levels = [];

            foreach (['basic', 'easy', 'medium', 'hard'] as $level) {
                $record = $progressPerModul->firstWhere('level', $level);
                $levels[$level] = $record
                    ? [
                        'isLocked' => (bool) $record->isLocked,
                        'isCompleted' => (bool) $record->isCompleted
                    ]
                    : [
                        'isLocked' => true,
                        'isCompleted' => false
                    ];
            }

            $result['modul'][] = [
                'modulId' => (string) $modulId,
                'levels' => $levels
            ];
        }

        return response()->json([
            'userId' => (string) $userId,
            'modules' => $result['modul']
        ]);
    }

    public function updateProgress(Request $request)
    {
        $validator = validator($request->all(), [
            'user_id' => 'required|exists:users,id',
            'modul_id' => 'required|exists:moduls,id',
            'level' => 'required|in:basic,easy,medium,hard',
            'isLocked' => 'required|boolean',
            'isCompleted' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $progress = QuizProgress::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'modul_id' => $request->modul_id,
                'level' => $request->level,
            ],
            [
                'isLocked' => $request->isLocked,
                'isCompleted' => $request->isCompleted,
            ]
        );

        // Beri coins jika hard level selesai
        if ($request->level === 'hard' && $request->isCompleted) {
            $user = User::find($request->user_id);
            $user->increment('coins', 10);
        }

        // Unlock next level jika current level sudah Completed
        if ($request->isCompleted) {
            $levelOrder = ['basic', 'easy', 'medium', 'hard'];
            $currentIndex = array_search($request->level, $levelOrder);

            if ($currentIndex !== false && $currentIndex < count($levelOrder) - 1) {
                $nextLevel = $levelOrder[$currentIndex + 1];

                // Update next level → unlock
                QuizProgress::updateOrCreate(
                    [
                        'user_id' => $request->user_id,
                        'modul_id' => $request->modul_id,
                        'level' => $nextLevel,
                    ],
                    [
                        'isLocked' => false, // Buka level berikutnya
                        // isCompleted tetap false untuk next level
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Quiz progress updated successfully',
            'data' => $progress
        ]);
    }
}
