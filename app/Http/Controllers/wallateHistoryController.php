<?php

namespace App\Http\Controllers;

use App\Models\WalletHistory;
use Illuminate\Http\Request;

class wallateHistoryController extends Controller
{
    public function getByUser($userId)
    {
        $history = WalletHistory::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        if(!$history) {
            return response()->json(['message' => 'History Not Found'], 404);
        }

        return response()->json([
            'message' => 'History Found',
            'history' => $history
        ], 200);
    }
}
