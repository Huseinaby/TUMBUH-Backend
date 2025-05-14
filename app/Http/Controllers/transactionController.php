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
use Illuminate\Support\Facades\Log;

class transactionController extends Controller
{
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

    public function payWithXendit($transactionId)
    {
        $transaction = Transaction::with('user')->findOrFail($transactionId);

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' => 'Transaction already paid or cancelled',
            ], 400);
        }

        Configuration::setXenditKey(config('services.xendit.secrt'));

        $externalId = 'TUMBUH-' . $transaction->id . '-' . now()->timestamp;

        $invoiceRequest = new CreateInvoiceRequest([
            'external_id' => $externalId,
            'amount' => $transaction->total_price,
            'description' => 'Payment for transaction #' . $transaction->id,
            'customer' => [
                'email' => $transaction->user->email,
            ],
            'success_redirect_url' => url('/api/transaction/success'),
            'failure_redirect_url' => url('/api/transaction/failed'),
        ]);

        $invoiceApi = new InvoiceApi();
        $invoice = $invoiceApi->createInvoice($invoiceRequest);

        $transaction->update([
            'payment_method' => 'xendit',
            'xendit_invoice_id' => $invoice['id'],
            'invoice_url' => $invoice['invoice_url'],
        ]);

        return response()->json([
            'message' => 'Invoice created successfully',
            'invoice_url' => $invoice['invoice_url'],
        ]);
    }

    public function handleWebHook(Request $request)
    {
        $payload = $request->all();

        if (!isset($payload['id']) || !isset($payload['status'])) {
            return response()->json([
                'message' => 'Invalid payload',
            ], 400);
        }

        $transaction = transaction::where('xendit_invoice_id', $payload['id'])->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        $status = strtolower($payload['status']);

        if ($status === 'paid') {
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            Log::info('Webhook dari Xendit:', $request->all());
        } elseif ($status === 'expired') {
            $transaction->update([
                'status' => 'expired',
            ]);
        }
        return response()->json([
            'message' => 'Transaction status updated successfully',
        ], 200);
    }

    public function paymentSuccess(Request $request)
    {
        $invoiceId = $request->query('id');

        $transaction = transaction::with('orderItems.product')
        ->where('xendit_invoice_id', $invoiceId)
        ->first();

        if(!$transaction) {
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

        $transaction = transaction::where('xendit_invoice_id', $invoiceId)->first();

        if(!$transaction) {
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
}
