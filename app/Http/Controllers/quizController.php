<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\support\Facades\Http;
use App\Models\Quiz;

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

    public function getByModul($modulId){
        $quizzes = Quiz::where('modul_id', $modulId)->get();

        if($quizzes->isEmpty()) {
            return response()->json([
                'message' => 'No quizzes found for this module'
            ], 404);
        }

        return response()->json([
            'message' => 'Quizzes found',
            'data' => $quizzes
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
}
