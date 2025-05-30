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

    public function store(Request $request)
    {
        $user = Auth::user();
        $cartIds = $request->input('cart_ids');

        if(!$cartIds || !is_array($cartIds)) {
            return response()->json([
                'message' => 'Invalid cart IDs provided',
            ], 400);
        }

        $cartData = $this->getCartGroupedBySeller($cartIds);

        if(empty($cartData)) {
            return response()->json([
                'message' => 'No valid cart items found for the provided IDs',
            ], 404);
        }

        $transactions = [];

        DB::beginTransaction();

        try {
            foreach($cartData as $sellercart) {
                $sellerId = $sellercart['seller']['id'];
                $items = $sellercart['items'];
                $total = collect($items)->sum('subTotal');

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'seller_id' => $sellerId,
                    'total_price' => $total,
                    'status' => 'pending',
                    'payment_method' => 'midtrans',
                ]);

                foreach($items as $item) {
                    orderItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['product']['price'],
                        'subtotal' => $item['subTotal'],
                    ]);
                }

                $orderId = 'TUMBUH-' . $transaction->id . '-' . now()->timestamp;

                $params = [
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $total,
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                    ],
                    'item_details' => array_map(function ($item) {
                        return [
                            'id' => $item['product_id'],
                            'price' => $item['product']['price'],
                            'quantity' => $item['quantity'],
                            'name' => $item['product']['name'],
                        ];
                    }, $items),
                ];

                $snapUrl = Snap::createTransaction($params)->redirect_url;

                $transaction->update([
                    'invoice_url' => $snapUrl,
                    'midtrans_order_id' => $orderId,
                ]);

                foreach($items as $item) {
                    cartItem::where('id', $item['cart_id'])->delete();
                }

                $transactions[] = [
                    'transaction_id' => $transaction->id,
                    'seller' => $sellercart['seller'],
                    'total_price' => $total,
                    'snap_url' => $snapUrl,
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Transactions created successfully',
                'transactions' => $transactions,
            ], 201);
            

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Transaction failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCartGroupedBySeller(array $cartIds)
    {
        $cartItems = cartItem::with(['product.user'])
            ->whereIn('id', $cartIds)
            ->get();

        $grouped = $cartItems->groupBy(fn($item) => $item->product->user_id);

        $result = [];

        foreach($grouped as $sellerId => $items) {
            $result[] =[
                'seller' => [
                    'id' => $sellerId,
                    'storeName' => $items->first()->product->user->storeName ?? $items->first()->product->user->username,
                ],
                'items' => $items->map(function ($item) {
                    return [
                        'cart_id' => $item->id,
                        'product_id' => $item->product->id,
                        'quantity' => $item->quantity,
                        'subTotal' => $item->product->price * $item->quantity,
                        'product' => [
                            'name' => $item->product->name,
                            'price' => $item->product->price,
                            'stock' => $item->product->stock,
                            'image' => $item->price->image_path ?? null,
                        ]
                        ];
                })->toArray()
            ];
        }

        return $result;
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
                'order_id' => $orderId,
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

        if ($signature !== $request->signature_key) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        $orderId = explode('-', $request->order_id)[1] ?? null;
        $transaction = transaction::find($orderId);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($request->transaction_status === 'settlement') {
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
