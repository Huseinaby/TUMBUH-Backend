<?php

namespace App\Http\Controllers;

use App\Models\transaction;
use Illuminate\Http\Request;
use App\Models\cartItem;
use App\Models\orderItem;
use App\Models\Product;
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
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function getByUser()
    {
        $user = Auth::user();

        $transactions = transaction::with('user', 'orderItems.product')
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function getBySeller()
    {
        $user = Auth::user();

        $transactions = transaction::with('user', 'orderItems.product')
            ->where('seller_id', $user->id)
            ->get();

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function checkoutSummary(Request $request)
    {
        $request->validate([
            'cart_ids' => 'required|array',
            'courier' => 'nullable|string',
        ]);

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


        $shippingCosts = [];

        foreach ($cartData as $group) {
            $sellerOriginId = $group['seller']['origin_id'];
            $destinationId = $addresses[0]['origin_id'];
            $weight = collect($group['items'])->sum('total_weight');

            $cost = $this->getCost(
                app(RajaOngkirService::class),
                $sellerOriginId,
                $destinationId,
                $weight,
                $request->input('courier', 'jne')
            );

            $shippingCosts[] = [
                'seller_id' => $group['seller']['id'],
                'cost' => $cost,
            ];
        }

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
        $paymentMethod = $request->input('payment_method');

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
                $platformFee = round($total * 0.05); // 5% platform fee

                $shippingCostValue = $shippingCostMap[$sellerId]['cost'] ?? 0;
                $shippingService = $shippingCostMap[$sellerId]['service'] ?? 'unknown';

                $finalPrice = $total + $shippingCostValue + $platformFee;

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'seller_id' => $sellerId,
                    'shipping_cost' => $shippingCostValue,
                    'shipping_service' => $shippingService,
                    'platform_fee' => $platformFee,
                    'total_price' => $finalPrice,
                    'status' => 'pending',
                    'payment_method' => $request->payment_method,
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

                $itemDetails = array_map(function ($item) {
                    return [
                        'id' => $item['product_id'],
                        'price' => $item['product']['price'],
                        'quantity' => $item['quantity'],
                        'name' => $item['product']['name'],
                    ];
                }, $items);

                $itemDetails[] = [
                    'id' => 'shipping_' . $sellerId,
                    'price' => $shippingCostValue,
                    'quantity' => 1,
                    'name' => $shippingService,
                ];

                $itemDetails[] = [
                    'id' => 'platform_fee',
                    'price' => $platformFee,
                    'quantity' => 1,
                    'name' => 'Biaya Layanan Platform TUMBUH',
                ];

                $params = [
                    'enabled_payments' => [$paymentMethod],
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $finalPrice,
                    ],
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                    ],
                    'item_details' => $itemDetails,
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
                    'subTotal' => $total,
                    'shipping_cost' => $shippingCostValue,
                    'platform_fee' => $platformFee,
                    'total_price' => $finalPrice,
                    'payment_method' => $paymentMethod,
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

    public function buyNowSummary(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'courier' => 'nullable|string',
        ]);

        $product = Product::with(['user.sellerDetail', 'user.userAddress'])
            ->findOrFail($request->product_id);

        $quantity = $request->quantity;
        $seller = $product->user;

        $user = Auth::user();
        $address = UserAddress::with(['province', 'kabupaten', 'kecamatan'])
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        $shippingCost = $this->getCost(
            app(RajaOngkirService::class),
            $seller->userAddress->firstWhere('is_default', true)->origin_id,
            $address->origin_id,
            $product->weight * $quantity,
            $request->input('courier', 'jne')
        );


        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'weight' => $product->weight,
            ],
            'seller' => [
                'id' => $seller->id,
                'storeName' => $seller->sellerDetail->store_name ?? $seller->username,
                'origin_id' => $seller->userAddress->firstWhere('is_default', true)->origin_id ?? null,
            ],
            'quantity' => $quantity,
            'shipping_cost' => $shippingCost,
            'shipping_service' => $request->input('courier'),
            'total_price' => ($product->price * $quantity) + $shippingCost['cost'],
            'address' => [
                'full_name' => $address->nama_lengkap,
                'full_address' => $address->alamat_lengkap,
                'phone' => $address->nomor_telepon,
                'province' => $address->province ? $address->province->name : null,
                'city' => $address->kabupaten ? $address->kabupaten->name : null,
                'district' => $address->kecamatan ? $address->kecamatan->name : null,
                'postal_code' => $address->kode_pos,
            ],
        ]);
    }

    public function buyNow(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'shipping_cost' => 'required|numeric',
            'shipping_service' => 'required|string',
            'payment_method' => 'required|string',
        ]);


        $user = Auth::user();
        $product = Product::with('user')
            ->findOrFail($request->product_id);

        if ($product->stock < $request->quantity) {
            return response()->json([
                'message' => 'Insufficient stock for this product',
            ], 400);
        }
        $seller = $product->user;

        $subtotal = $product->price * $request->quantity;
        $platformFee = round($subtotal * 0.05); 
        $finalPrice = $subtotal + $request->shipping_cost + $platformFee;


        DB::beginTransaction();

        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'total_price' => $finalPrice,
                'platform_fee' => $platformFee,
                'shipping_cost' => $request->shipping_cost,
                'shipping_service' => $request->shipping_service,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
            ]);

            orderItem::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $product->price,
                'subtotal' => $subtotal,
            ]);

            $orderId = 'TUMBUH-' . $transaction->id . '-' . now()->timestamp;

            $params = [
                'enabled_payments' => [$request->payment_method],
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $finalPrice,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                'item_details' => [
                    [
                        'id' => $product->id,
                        'price' => $product->price,
                        'quantity' => $request->quantity,
                        'name' => $product->name,
                    ],
                    [
                        'id' => 'shipping_' . $seller->id,
                        'price' => $request->shipping_cost,
                        'quantity' => 1,
                        'name' => $request->shipping_service,
                    ],
                    [
                        'id' => 'platform_fee',
                        'price' => $platformFee,
                        'quantity' => 1,
                        'name' => 'Biaya Layanan Platform TUMBUH',
                    ]
                ],
            ];

            $snapUrl = Snap::createTransaction($params)->redirect_url;

            $transaction->update([
                'invoice_url' => $snapUrl,
                'midtrans_order_id' => $orderId,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaction created successfully',
                'snap_url' => $snapUrl,
                'payment_method' => $request->payment_method,
                'transaction' => [
                    'id' => $transaction->id,
                    'seller_id' => $seller->id,
                    'total_price' => $finalPrice,
                    'platform_fee' => $platformFee,
                    'shipping_cost' => $request->shipping_cost,
                    'shipping_service' => $request->shipping_service,
                ],
            ]);
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
        try {
            $serverKey = config('services.midtrans.server_key');

            $grossAmount = number_format((float) $request->gross_amount, 2, '.', '');

            $signature = hash(
                'sha512',
                $request->order_id .
                $request->status_code .
                $grossAmount .
                $serverKey
            );

            Log::info('Webhook Signature Debug', [
                'computed_signature' => $signature,
                'received_signature' => $request->signature_key,
                'order_id' => $request->order_id,
                'status_code' => $request->status_code,
                'gross_amount' => $grossAmount,
            ]);

            if ($signature !== $request->signature_key) {
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // Bypass untuk Test Webhook Midtrans
            if (str_starts_with($request->order_id, 'payment_notif_test')) {
                Log::info('Received Midtrans TEST Webhook', $request->all());
                return response()->json(['message' => 'Test Webhook OK'], 200);
            }

            $orderId = explode('-', $request->order_id)[1] ?? null;
            if (!$orderId) {
                return response()->json(['message' => 'Invalid order_id format'], 400);
            }

            $transaction = transaction::find($orderId);
            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            if (in_array($transaction->status, ['paid', 'expired', 'cancelled'])) {
                return response()->json(['message' => 'Transaction already processed'], 200);
            }

            $status = $request->transaction_status;

            if ($status === 'settlement') {
                foreach($transaction->orderItems as $item) {
                    $product = $item->product;
                    $product->decrement('stock', $item->quantity);
                }

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

        } catch (\Throwable $e) {
            Log::error('Midtrans Webhook Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }


    public function finishPayment(Request $request)
    {
        $invoiceId = $request->query('order_id');
    
        $transaction = Transaction::with('orderItems.product')
            ->where('midtrans_order_id', $invoiceId)
            ->first();
    
        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found',
                'order_id' => $invoiceId,
            ], 404);
        }
    
        return response()->json([
            'message' => $transaction->status === 'paid'
                ? 'Payment successful'
                : ($transaction->status === 'pending'
                    ? 'Payment is pending'
                    : 'Payment failed or expired'),
            'status' => $transaction->status,
            'transaction' => $transaction,
        ]);
    }
    

    public function paymentError(Request $request){
        $invoiceId = $request->query('order_id');

        $transaction = transaction::with('orderItems.product')
            ->where('midtrans_order_id', $invoiceId)
            ->first();

        if(!$transaction){
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        if($transaction->status !== 'paid'){
            return response()->json([
                'message' => 'Transaction is not completed',
                'status' => $transaction->status,
                'transaction' => $transaction,
            ], 400);
        } else {
            return response()->json([
                'message' => 'Payment successful',
                'status' => $transaction->status,
                'transaction' => $transaction,
            ]);
        }
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
        $status = strtolower($summaryStatus);
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

    public function confirmTransaction($id)
    {
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


}
