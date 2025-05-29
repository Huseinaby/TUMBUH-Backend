<?php

namespace App\Http\Controllers;

use App\Models\transaction;
use Illuminate\Http\Request;
use App\Models\cartItem;
use App\Models\orderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Midtrans\Config;
use Midtrans\Snap;

class transactionController extends Controller
{

    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.sanitized');
        Config::$is3ds = config('services.midtrans.enable_3ds');
    }

    public function index()
    {
        $transactions = transaction::with('user', 'orderItems.product')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function store()
    {
        $user = Auth::user();

        $cartItems = cartItem::with('product')
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty'
            ], 404);
        }

        $total = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'message' => 'Insufficient stock for product: ' . $item->product->name,
                ], 400);
            }
        }

        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_price' => $total,
                'status' => 'pending',
                'payment_method' => 'xendit',
            ]);

            foreach ($cartItems as $item) {
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
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Transaction failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $transaction = Transaction::with('user', 'orderItems.product')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'transaction' => $transaction,
        ]);
    }

    public function payWithMidtrans($transactionId)
    {
        $transaction = Transaction::with('user')->findOrFail($transactionId);

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' => 'Transaction already paid or cancelled',
            ], 400);
        }

        $orderId = 'TUMBUH-' . $transaction->id . '-' . now()->timestamp;

        $params = [
            'transaction_details' => [
                'order_id'  => $orderId,
                'gross_amount' => $transaction->total_price,
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'callbacks' => [
                'finish' => url('/api/transaction/success'),
                'unfinish' => url('/api/transaction/failed'),
                'error' => url('/api/transaction/failed'),
            ],
        ];

        $snapUrl = Snap::createTransaction($params)->redirect_url;

        $transaction->update([
            'payment_method' => 'midtrans',
            'invoice_url' => $snapUrl,
            'midtrans_order_id' => $orderId,
        ]);

        return response()->json([
            'message' => 'Snap URL generated successfully',
            'invoice_url' => $snapUrl,
        ]);
    }

    public function handleWebHook(Request $request)
    {
        $serverKey = config('services.midtrans.server_key');

        $signature = hash(
            'sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            $serverKey
        );

        if($signature !== $request->signature_key) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        $orderId = explode('-', $request->order_id)[1] ?? null;
        $transaction = transaction::find($orderId);

        if(!$transaction){
            return response()->json(['message' => 'Transaction not foudn'], 404);
        }

        if($request->transaction_status === 'settlement') {
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        } elseif ($request->transaction_status === 'expire') {
            $transaction->update(['status' => 'expired']);
        }

        return response()->json(['message' => 'Transaction status updated'], 200);
    }

    public function paymentSuccess(Request $request)
    {
        $invoiceId = $request->query('id');

        $transaction = transaction::with('orderItems.product')
            ->where('midtrans_order_id', $invoiceId)
            ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Payment successful',
            'status' => $transaction->status,
            'transaction' => $transaction,
        ]);
    }

    public function paymentFailed(Request $request)
    {
        $invoiceId = $request->query('id');

        $transaction = transaction::where('midtrans_order_id', $invoiceId)->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Payment failed',
            'status' => $transaction->status,
            'transaction' => $transaction,
        ]);
    }

    public function sellerIncome()
    {
        $user = Auth::user();

        $total = orderItem::whereHas('product', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereHas('transaction', function ($query) {
                    $query->where('status', 'paid');
                });
        })->sum('subtotal');

        $count = orderItem::where('product', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereHas('transaction', function ($query) {
                    $query->where('status', 'paid');
                });
        })->count();

        return response()->json([
            'total_income' => $total,
            'total_transaction' => $count,
        ]);
    }
}
