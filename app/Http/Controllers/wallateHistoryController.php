<?php

namespace App\Http\Controllers;

use App\Models\SellerDetail;
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

        $saldo = SellerDetail::where('user_id', $userId)->value('saldo');

        return response()->json([
            'message' => 'History Found',
            'saldo' => $saldo,
            'history' => $history
        ], 200);
    }
}
