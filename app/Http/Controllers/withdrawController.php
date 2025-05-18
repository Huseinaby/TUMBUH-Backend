<?php

namespace App\Http\Controllers;

use App\Models\orderItem;
use App\Models\WithdrawRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class withdrawController extends Controller
{
    public function listWithdraw(){
        $withdraws = WithdrawRequest::with('user')->latest()->get();

        return response()->json([
            'withdraw_requests' => $withdraws,
        ]);
    }

    public function listWithdrawByUser($userId){
        $withdraws = WithdrawRequest::with('user')->where('user_id', $userId)->latest()->get();

        return response()->json([
            'withdraw_requests' => $withdraws,
        ]);
    }

    public function requestWithdraw(Request $request){
        $request->validate([
            'amount' => 'required|integer|min:10000',
        ]);

        $user = Auth::user();

        $totalIncome = orderItem::whereHas('product', fn($query) => $query->where('user_id', $user->id))
        ->whereHas('transaction', fn($query) => $query->where('status', 'paid'))
            ->sum('subtotal');

        $totalWithdrawn = WithdrawRequest::where('user_id', $user->id)
        ->where('status', 'approved')
            ->sum('amount');

        $availableBalance = $totalIncome - $totalWithdrawn;

        if($request->amount > $availableBalance) {
            return response()->json([
                'message' => 'Insufficient balance for withdrawal'
            ], 400);
        }

        $withdraw = WithdrawRequest::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Withdraw request created successfully',
            'withdraw_request' => $withdraw,
        ]);
    }

    public function handleWithdraw(Request $request, $id) {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:255',
        ]);

        $withdraw = WithdrawRequest::find($id);

        if (!$withdraw) {
            return response()->json([
                'message' => 'Withdraw request not found'
            ], 404);
        }

        $withdraw->update([
            'status' => $request->status,
            'note' => $request->note,
        ]);

        return response()->json([
            'message' => 'Withdraw request updated successfully',
            'withdraw_request' => $withdraw,
        ]);
    }
}
