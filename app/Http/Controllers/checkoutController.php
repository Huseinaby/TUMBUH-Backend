<?php

namespace App\Http\Controllers;

use App\Models\cartItem;
use App\Models\orderItem;
use App\Models\transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class checkoutController extends Controller
{
    public function store(Request $request){
        $user = Auth::user();

        $cartItems = cartItem::with('product')
            ->where('user_id', $user->id)
            ->get();

        if($cartItems->isEmpty()){
            return response()->json([
                'message' => 'Cart is empty'
            ], 404);
        }

        $total = $cartItems->sum(function($item) {
            return $item->product->price * $item->quantity;
        });

        DB::beginTransaction();

        try{
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_price' => $total,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
            ]);

            foreach($cartItems as $item){
                orderItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                    'subtotal' => $item->product->price * $item->quantity,
                ]);
            }

            cartItem::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Transaction created successfully',
                'transaction' => $transaction,
            ]);
        } catch(\Exception $e){
            DB::rollBack();

            return response()->json([
                'message' => 'Transaction failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
