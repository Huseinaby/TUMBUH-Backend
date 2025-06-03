<?php

namespace App\Http\Controllers;

use App\Models\transaction;
use Illuminate\Http\Request;
use App\Models\cartItem;
use App\Models\orderItem;
use App\Models\Review;
use App\Models\UserAddress;
use App\Services\BinderByteService;
use App\Services\RajaOngkirService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Midtrans\Config;
use Midtrans\Snap;

class transactionController extends Controller
{

    protected $binderByteService;

    public function __construct(BinderByteService $binderByteService)
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.sanitized');
        Config::$is3ds = config('services.midtrans.enable_3ds');

        $this->binderByteService = $binderByteService;
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

    public function checkoutSummary(Request $request)
    {
        $user = Auth::user();
        $cartIds = $request->input('cart_ids');


        if (!$cartIds || !is_array($cartIds)) {
            return response()->json([
                'message' => 'Invalid cart IDs provided',
            ], 400);
        }

        $cartData = $this->getCartGroupedBySeller($cartIds);

        if (empty($cartData)) {
            return response()->json([
                'message' => 'No valid cart items found for the provided IDs',
            ], 404);
        }

        $addresses = UserAddress::with(['province', 'kabupaten', 'kecamatan'])
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->get()
            ->map(function ($address) {
                return [
                    'id' => $address->id,
                    'full_name' => $address->nama_lengkap,
                    'full_address' => $address->alamat_lengkap,
                    'phone' => $address->nomor_telepon,
                    'province' => $address->province ? $address->province->name : null,
                    'city' => $address->kabupaten ? $address->kabupaten->name : null,
                    'district' => $address->kecamatan ? $address->kecamatan->name : null,
                    'postal_code' => $address->kode_pos,
                    'origin_id' => $address->origin_id,
                    'is_default' => $address->is_default,
                ];
            });


        $shippingCosts = $this->getCost(
            app(RajaOngkirService::class),
            $cartData[0]['seller']['origin_id'],
            $addresses[0]['origin_id'],
            $cartData[0]['items'][0]['total_weight'],
            $request->input('courier')
        );

        return response()->json([
            'cart_data' => $cartData,
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
            ],
            'addresses' => $addresses,
            'shipping_costs' => $shippingCosts,
        ]);

    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $cartIds = $request->input('cart_ids');
        $shippingCostMap = collect($request->input('shipping_costs', []))->keyBy('seller_id');

        if (!$cartIds || !is_array($cartIds)) {
            return response()->json([
                'message' => 'Invalid cart IDs provided',
            ], 400);
        }

        $cartData = $this->getCartGroupedBySeller($cartIds);

        if (empty($cartData)) {
            return response()->json([
                'message' => 'No valid cart items found for the provided IDs',
            ], 404);
        }

        $transactions = [];

        DB::beginTransaction();

        try {
            foreach ($cartData as $sellercart) {
                $sellerId = $sellercart['seller']['id'];
                $items = $sellercart['items'];
                $total = collect($items)->sum('subTotal');

                $shippingCostValue = $shippingCostMap[$sellerId]['cost'] ?? 0;
                $shippingService = $shippingCostMap[$sellerId]['service'] ?? 'unknown';

                $finalPrice = $total + $shippingCostValue;

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'seller_id' => $sellerId,
                    'shipping_cost' => $shippingCostValue,
                    'shipping_service' => $shippingService,
                    'total_price' => $finalPrice,
                    'status' => 'pending',
                    'payment_method' => 'midtrans',
                ]);

                foreach ($items as $item) {
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
                        'subtotal' => $total,
                        'shipping_cost' => $shippingCostValue,
                        'shipping_service' => $shippingService,
                        'gross_amount' => $finalPrice,
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                    ],
                    'item_details' => array_merge(
                        array_map(function ($item) {
                            return [
                                'id' => $item['product_id'],
                                'price' => $item['product']['price'],
                                'quantity' => $item['quantity'],
                                'name' => $item['product']['name'],
                            ];
                        }, $items),
                        [
                            [
                                'id' => 'shipping_' . $sellerId,
                                'price' => $shippingCostValue,
                                'quantity' => 1,
                                'name' => 'Ongkir'
                            ]
                        ]
                    ),
                ];

                $snapUrl = Snap::createTransaction($params)->redirect_url;

                $transaction->update([
                    'invoice_url' => $snapUrl,
                    'midtrans_order_id' => $orderId,
                ]);

                foreach ($items as $item) {
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

    public function getCost(RajaOngkirService $rajaOngkirService, $origin, $destination, $weight, $courier)
    {
        $cost = $rajaOngkirService->calculateDomesticCost($origin, $destination, $weight, $courier);

        if (isset($cost['error'])) {
            return response()->json([
                'message' => 'Error calculating cost',
                'error' => $cost['error'],
            ], 400);
        }

        return $cost;
    }

    public function getCartGroupedBySeller(array $cartIds)
    {
        $cartItems = cartItem::with(['product.user.sellerDetail', 'product.user.userAddress'])
            ->whereIn('id', $cartIds)
            ->get();

        $grouped = $cartItems->groupBy(fn($item) => $item->product->user_id);

        $result = [];

        foreach ($grouped as $sellerId => $items) {
            $result[] = [
                'seller' => [
                    'id' => $sellerId,
                    'storeName' => $items->first()->product->user->sellerDetail->store_name ?? $items->first()->product->user->username,
                    'origin_id' => $items->first()->product->user->userAddress->firstWhere('is_default', true)?->origin_id ?? null,
                ],
                'items' => $items->map(function ($item) {
                    return [
                        'cart_id' => $item->id,
                        'product_id' => $item->product->id,
                        'quantity' => $item->quantity,
                        'subTotal' => $item->product->price * $item->quantity,
                        'total_weight' => $item->product->weight * $item->quantity,
                        'product' => [
                            'name' => $item->product->name,
                            'price' => $item->product->price,
                            'stock' => $item->product->stock,
                            'weight' => $item->product->weight,
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

        Log::info('Webhook Signature Debug', [
            'computed_signature' => $signature,
            'received_signature' => $request->signature_key,
            'order_id' => $request->order_id,
            'status_code' => $request->status_code,
            'gross_amount' => $request->gross_amount,
        ]);



        if ($signature !== $request->signature_key) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        $orderId = explode('-', $request->order_id)[1] ?? null;
        $transaction = transaction::find($orderId);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if (in_array($request->status, ['paid', 'expired', 'cancelled'])) {
            return response()->json(['message' => 'Transaction already processed'], 200);
        }

        $status = $request->transaction_status;

        if (in_array($transaction->status, ['paid', 'expired', 'cancelled'])) {
            return response()->json(['message' => 'Transaction already processed'], 200);
        }

        if ($status === 'settlement') {
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        } elseif ($status === 'expire') {
            $transaction->update(['status' => 'expired']);
        } elseif (in_array($status, ['cancel', 'deny'])) {
            $transaction->update(['status' => 'cancelled']);
        } else {
            return response()->json(['message' => 'Unhandled transaction status'], 400);
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

    public function inputResi(Request $request, $id)
    {
        $request->validate([
            'resi_number' => 'required|string|max:255',
        ]);

        $transaction = transaction::where('id', $id)
            ->where('seller_id', Auth::id())
            ->firstOrFail();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found or you are not authorized to update this transaction',
            ], 404);
        }

        if ($transaction->resi_number) {
            return response()->json([
                'message' => 'Resi number already exists for this transaction',
            ], 400);
        }

        $transaction->update([
            'resi_number' => $request->input('resi_number'),
            'shipping_status' => 'shipped',
        ]);

        return response()->json([
            'message' => 'Resi number updated successfully',
            'transaction' => $transaction,
        ]);
    }

    public function cekResi($transactionId)
    {
        $transaction = transaction::findOrFail($transactionId);

        if (!$transaction->resi_number || !$transaction->shipping_service) {
            return response()->json([
                'message' => 'Resi number or shipping service not available',
            ], 404);
        }

        $trackingInfo = $this->binderByteService->track($transaction->shipping_service, $transaction->resi_number);

        if (!$trackingInfo || $trackingInfo['status'] !== 200) {
            return response()->json([
                'message' => 'Failed to retrieve tracking information',
                'error' => $trackingInfo['message'] ?? 'Unknown error',
            ], 500);
        }

        $summary = $trackingInfo['data']['summary'] ?? null;
        $history = $trackingInfo['data']['history'] ?? [];

        $latestDesc = $history[0]['desc'] ?? '';

        $statusMapped = $this->mapBinderByteStatus($summary['status'], $latestDesc);

        $transaction->update([
            'shipping_status' => $statusMapped,
        ]);

        return response()->json([
            'resi_number' => $transaction->resi_number,
            'shipping_service' => $transaction->shipping_service,
            'tracking_info' => $trackingInfo,
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

    public function mapBinderByteStatus($summaryStatus, $latestHistoryDesc)
    {
        $status = strtolower($$summaryStatus);
        $desc = strtolower($latestHistoryDesc);

        if (str_contains($desc, 'diterima') || $status === 'delivered') {
            return 'delivered';
        }
        if (str_contains($desc, 'dikirim') || $status === 'on transit') {
            return 'shipped';
        }
        if (str_contains($desc, 'dijemput') || $status === 'picked up') {
            return 'shipped';
        }

        if (str_contains($desc, 'gagal')) {
            return 'cancelled';
        }

        return 'pending';
    }

    public function confirmRecieved($id)
    {
        $transaction = transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('shipping_status', 'delivered')
            ->firstOrFail();


        if ($transaction->confirmed_received_at) {
            return response()->json([
                'message' => 'Transaction already confirmed received',
            ], 400);
        }

        $transaction->update([
            'confirmed_received_at' => now(),
        ]);

        return response()->json([
            'message' => 'Transaction confirmed received successfully',
            'transaction' => $transaction,
        ]);
    }

    public function cancelTransaction(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:255',
        ]);

        $transaction = transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' => 'Transaction cannot be cancelled',
            ], 400);
        }

        $transaction->update([
            'status' => 'cancelled',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Transaction cancelled successfully',
            'transaction' => $transaction,
        ]);
    }

    public function confirmTransaction($id){
        $transaction = transaction::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($transaction->status !== 'pending') {
            return response()->json([
                'message' => 'Transaction cannot be confirmed',
            ], 400);
        }

        $transaction->update([
            'status' => 'onProcess',
        ]);

        return response()->json([
            'message' => 'Transaction confirmed successfully',
            'transaction' => $transaction,
        ]);
    }

    public function storeReview(Request $request){
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'product_id' => 'required|exists:order_items,product_id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $trasaction = transaction::where('id', $request->transaction_id)
            ->where('user_id', Auth::id())
            ->whereNotNull('confirmed_received_at')
            ->firstOrFail();

        if(!$trasaction){
            return response()->json([
                'message' => 'Transaction not found or you are not authorized to review this transaction or transaction not confirmed received',
            ], 404);
        }

        $alreadyReviewed = Review::where('transaction_id', $trasaction->id)
            ->where('product_id', $request->product_id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'message' => 'You have already reviewed this product',
            ], 400);
        }

        Review::create([
            'transaction_id' => $trasaction->id,
            'product_id' => $request->product_id,
            'user_id' => Auth::id(),
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);
    }
}
