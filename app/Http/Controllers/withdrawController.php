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
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
        ]);

        $user = Auth::user();

        if(!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $withdraw = WithdrawRequest::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
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
            'proof_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048', // Optional proof of transfer
            'note' => 'nullable|string|max:255',
        ]);

        $withdraw = WithdrawRequest::find($id);

        if (!$withdraw) {
            return response()->json([
                'message' => 'Withdraw request not found'
            ], 404);
        }

        if ($withdraw->status !== 'pending') {
            return response()->json([
                'message' => 'Withdraw request has already been processed'
            ], 400);
        }

        $withdraw->status = $request->status;
        $withdraw->note = $request->note;
        if ($request->hasFile('proof_transfer')) {
            $withdraw->proof_transfer = $request->file('proof_transfer')->store('proof_transfers', 'public');
        }
        if ($request->status === 'approved') {
            $withdraw->approved_at = now();
        } elseif ($request->status === 'rejected') {
            $withdraw->rejected_at = now();
        }

        $withdraw->save();

        return response()->json([
            'message' => 'Withdraw request updated successfully',
            'withdraw_request' => $withdraw,
        ]);

    }
}
