<?php

namespace App\Http\Controllers;

use Illuminate\support\Facades\Http;
use App\Models\Quiz;
use Illuminate\Support\Facades\Log;

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
            'data' => $quizzes
        ]);
    }

    public function generateQuiz($modulId, $generateContent)
    {
        $geminiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}";
        
        // Prompt yang lebih ketat
        $quizPrompt = <<<EOT
        Buatkan 10 soal pilihan ganda berdasarkan bacaan berikut:
       
        {$generateContent}
        
        dengan distribusi soal sebagai berikut:
        - 2 soal dengan tingkat kesulitan mudah
        - 3 soal dengan tingkat kesulitan sedang
        - 5 soal dengan tingkat kesulitan sulit

        tingkat kesulitan:
        - mudah: soal yang jawabannya bisa langsung ditemukan di teks
        - sedang: soal yang membutuhkan penalaman dari bacaan
        - sulit: soal analisis yang menerapkan konsep bacaan


        Jawab hanya dalam format JSON valid seperti ini:
       
        [
          {
            "difficulty": "easy",
            "question": "Apa manfaat utama dari tanaman ini?",
            "option_a": "Untuk dekorasi",
            "option_b": "Sebagai sumber makanan",
            "option_c": "Untuk obat-obatan",x
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
            // Ekstrak teks dari respons
            $text = $quizResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            // Bersihkan teks dari markdown code blocks dan whitespace
            $cleaned = preg_replace('/```json|```|json|\n/', '', $text);
            $cleaned = trim($cleaned);
            
            // Decode JSON
            $quizzes = json_decode($cleaned, true);
            
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
            }
            
            return $savedQuizzes;
            
        } catch (\Exception $e) {
            Log::error('Error generating quiz: ' . $e->getMessage());
            return [];
        }
    }

}
