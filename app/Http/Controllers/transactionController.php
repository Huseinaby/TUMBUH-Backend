<?php

namespace App\Http\Controllers;

use App\Events\OrderCreated;
use App\Events\UserNotification;
use App\Models\transaction;
use App\Models\WalletHistory;
use Illuminate\Http\Request;
use App\Models\cartItem;
use App\Models\orderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ShippingCostDetail;
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

        $transactions = transaction::with('seller', 'orderItems.product.images')
            ->where('user_id', $user->id)
            ->get();

        if(!$transactions) {
            return response()->json(['message' => 'Transaction Not Found']);
        }

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function getByUserPaid()
    {
        $user = Auth::user();

        $transactions = transaction::with('seller', 'orderItems.product')
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'message' => 'No paid transactions found for this user',
            ], 404);
        }

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function getByUserPending()
    {
        $user = Auth::user();

        $transactions = transaction::with('seller', 'orderItems.product')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'message' => 'No pending transactions found for this user',
            ], 404);
        }

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function getByUserCompleted()
    {
        $user = Auth::user();

        $transactions = transaction::with('seller', 'orderItems.product')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'message' => 'No completed transactions found for this user',
            ], 404);
        }

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
            'shipping_options' => 'nullable|array',
        ]);

        $user = Auth::user();
        $cartIds = $request->input('cart_ids');
        $shippingOptions = collect($request->input('shipping_options', []));

        $cartData = $this->getCartGroupedBySeller($cartIds);

        if (empty($cartData)) {
            return response()->json([
                'message' => 'No valid cart items found for the provided IDs',
            ], 404);
        }

        $address = UserAddress::with(['province', 'kabupaten', 'kecamatan'])
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if (!$address) {
            return response()->json([
                'message' => 'No default address found for the user',
            ], 404);
        }

        $formatAddress = [
            'id' => $address->id,
            'full_name' => $address->nama_lengkap,
            'full_address' => $address->alamat_lengkap,
            'phone' => $address->nomor_telepon,
            'province' => $address->province ? $address->province->name : null,
            'city' => $address->kabupaten ? $address->kabupaten->name : null,
            'district' => $address->kecamatan ? $address->kecamatan->name : null,
            'postal_code' => $address->kode_pos,
            'origin_id' => $address->origin_id,
        ];

        $sellerSummaries = [];
        $overallProductTotal = 0;
        $totalShippingCost = 0;
        $overallPlatformFee = 0;

        foreach ($cartData as $group) {
            $sellerId = $group['seller']['id'];
            $items = $group['items'];

            $subTotal = collect($items)->sum('subTotal');

            $totalWeight = collect($items)->sum(function ($item) {
                return $item['quantity'] * ($item['product']['weight'] ?? 0);
            });

            $option = $shippingOptions->firstWhere('seller_id', $sellerId);
            $shippingCost = $option['cost'] ?? 0;
            $shippingName = $option['shipping_name'] ?? 'unknown';
            $shippingService = $option['shipping_service'] ?? 'unknown';

            if ($subTotal < 40000) {
                $platformFee = 4500;
            } elseif ($subTotal < 100000) {
                $platformFee = (int) round($subTotal * 0.07);
            } else {
                $platformFee = (int) round($subTotal * 0.05);
            }

            $grandTotal = $subTotal + $shippingCost + $platformFee;

            $sellerSummaries[] = [
                'seller' => $group['seller'],
                'items' => $items,
                'product_total' => $subTotal,
                'total_weight' => $totalWeight,
                'shipping_cost' => $shippingCost,
                'shipping_name' => $shippingName,
                'shipping_service' => $shippingService,
                'platform_fee' => $platformFee,
                'grand_total' => $grandTotal,
            ];

            $overallProductTotal += $subTotal;
            $totalShippingCost += $shippingCost;
            $overallPlatformFee += $platformFee;
        }

        return response()->json([
            'cart_summary' => $sellerSummaries,
            'user' => [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
            ],
            'address' => $formatAddress,
            'summary_totals' => [
                'product_total' => $overallProductTotal,
                'total_shipping' => $totalShippingCost,
                'platform_fee' => $overallPlatformFee,
                'grand_total' => $overallProductTotal + $totalShippingCost + $overallPlatformFee,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cart_ids' => 'required|array',
            'shipping_costs' => 'required|array',
            'payment_method' => 'required|string',
            'product_total' => 'required|numeric',
            'platform_fee' => 'required|numeric',
            'total_shipping' => 'required|numeric',
            'grand_total' => 'required|numeric',
        ]);

        $user = Auth::user();
        $cartIds = $request->cart_ids;
        $shippingMap = collect($request->shipping_costs)->keyBy('seller_id');
        $transactions = [];

        DB::beginTransaction();

        try {
            $cartData = $this->getCartGroupedBySeller($cartIds);

            foreach ($cartData as $group) {
                $sellerId = $group['seller']['id'];
                $items = $group['items'];

                $shipping = $shippingMap[$sellerId];
                $productTotal = $shipping['product_total'];
                $shippingCost = $shipping['shipping_cost'];
                $shippingName = $shipping['shipping_name'];
                $shippingService = $shipping['shipping_service'];
                $platformFee = $shipping['platform_fee'];
                $finalPrice = $shipping['grand_total'];

                // Simpan transaksi
                $transaction = transaction::create([
                    'user_id' => $user->id,
                    'seller_id' => $sellerId,
                    'total_price' => $finalPrice,
                    'platform_fee' => $platformFee,
                    'shipping_cost' => $shippingCost,
                    'shipping_name' => $shippingName,
                    'shipping_service' => $shippingService,
                    'status' => 'pending',
                    'payment_method' => $request->payment_method,
                ]);

                // Simpan detail produk & hapus dari keranjang
                foreach ($items as $item) {
                    orderItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['product']['price'],
                        'subtotal' => $item['subTotal'],
                    ]);
                    cartItem::where('id', $item['cart_id'])->delete();
                }

                // Siapkan untuk Midtrans
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
                    'price' => $shippingCost,
                    'quantity' => 1,
                    'name' => 'Ongkir - ' . strtoupper($shippingName) . ' (' . strtoupper($shippingService) . ')',
                ];

                $itemDetails[] = [
                    'id' => 'platform_fee',
                    'price' => $platformFee,
                    'quantity' => 1,
                    'name' => 'Biaya Layanan TUMBUH',
                ];

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
                    'item_details' => $itemDetails,
                ];

                $snapUrl = Snap::createTransaction($params)->redirect_url;

                $transaction->update([
                    'invoice_url' => $snapUrl,
                    'midtrans_order_id' => $orderId,
                ]);

                // hapus shipping cache user setelah transaksi
                $this->clearUserShippingCost($sellerId, $user->id);

                $transactions[] = [
                    'transaction_id' => $transaction->id,
                    'snap_url' => $snapUrl,
                    'total_price' => $finalPrice,
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
            'shipping_name' => 'nullable|string',
            'shipping_service' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $quantity = $request->quantity;

        $product = Product::with(['images', 'user.sellerDetail', 'user.userAddress'])
            ->findOrFail($request->product_id);

        $image = $product->images()->first();
        $seller = $product->user;

        $address = UserAddress::with(['province', 'kabupaten', 'kecamatan'])
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if (!$address) {
            return response()->json([
                'message' => 'No default address found for the user',
            ], 404);
        }

        $origin = optional($seller->userAddress->firstWhere('is_default', true))->origin_id;
        $storeLogo = optional($seller->sellerDetail)->store_logo;
        $destination = optional($address)->origin_id;

        $productTotal = $product->price * $quantity;

        // Platform fee tiers
        if ($productTotal < 40000) {
            $platformFee = 4500;
        } elseif ($productTotal < 100000) {
            $platformFee = round($productTotal * 0.07);
        } else {
            $platformFee = round($productTotal * 0.05);
        }

        $shippingCost = null;
        $shippingName = null;
        $shippingService = null;

        // Jika dikirim dari frontend
        if ($request->filled(['shipping_name', 'shipping_service', 'shipping_cost'])) {
            $shippingName = $request->shipping_name;
            $shippingService = $request->shipping_service;
            $shippingCost = $request->shipping_cost;
        }
        // Jika hanya courier, hit API
        elseif ($request->filled('courier')) {
            $shipping = $this->getCost(
                app(RajaOngkirService::class),
                $origin,
                $destination,
                $product->weight * $quantity,
                $request->courier
            );

            if (is_array($shipping)) {
                $shippingCost = $shipping['cost'] ?? 0;
            }

            $shippingName = $request->courier;
            $shippingService = $shipping['service'] ?? null;
        }

        $grandTotal = $productTotal + ($shippingCost ?? 0) + $platformFee;
        $totalWeight = $product->weight * $quantity;

        return response()->json([
            'summary' => [
                [
                    'seller' => [
                        'id' => $seller->id,
                        'storeName' => $seller->sellerDetail->store_name ?? $seller->username,
                        'logo' => $storeLogo ? 'storage/' . $storeLogo : null,
                        'origin_id' => $origin,
                    ],
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'image' => $image ? 'storage/' . $image->image_path : null,
                            'subTotal' => $product->price,
                            'total_weight' => $totalWeight,
                            'product' => [
                                'name' => $product->name,
                                'price' => $product->price,
                                'stock' => $product->stock,
                                'weight' => $product->weight
                            ],
                        ]
                    ],
                    'product_total' => $productTotal,
                    'shipping_cost' => $shippingCost,
                    'shipping_name' => $shippingName,
                    'shipping_service' => $shippingService,
                    'platform_fee' => $platformFee,
                    'grand_total' => $grandTotal
                ]
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'address' => [
                'id' => $address->id,
                'full_name' => $address->nama_lengkap,
                'full_address' => $address->alamat_lengkap,
                'phone' => $address->nomor_telepon,
                'province' => optional($address->province)->name,
                'city' => optional($address->kabupaten)->name,
                'district' => optional($address->kecamatan)->name,
                'postal_code' => $address->kode_pos,
                'origin_id' => $address->origin_id,
            ],
            'summary_totals' => [
                'product_total' => $productTotal,
                'total_shipping' => $shippingCost,
                'platform_fee' => $platformFee,
                'grand_total' => $grandTotal
            ]
        ]);
    }



    public function buyNow(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'shipping_cost' => 'required|numeric|min:0',
            'shipping_name' => 'required|string',
            'shipping_service' => 'required|string',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();
        $product = Product::with('user')->findOrFail($request->product_id);
        $quantity = $request->quantity;

        if ($product->stock < $quantity) {
            return response()->json([
                'message' => 'Insufficient stock for this product',
            ], 400);
        }

        $seller = $product->user;
        $subtotal = $product->price * $quantity;

        // 💰 Hitung platform fee tier
        if ($subtotal < 40000) {
            $platformFee = 4500;
        } elseif ($subtotal < 100000) {
            $platformFee = round($subtotal * 0.07);
        } else {
            $platformFee = round($subtotal * 0.05);
        }

        $shippingCost = $request->shipping_cost;
        $finalPrice = $subtotal + $shippingCost + $platformFee;

        DB::beginTransaction();

        try {
            // 🔃 Simpan transaksi
            $transaction = transaction::create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'total_price' => $finalPrice,
                'platform_fee' => $platformFee,
                'shipping_cost' => $shippingCost,
                'shipping_name' => $request->shipping_name,
                'shipping_service' => $request->shipping_service,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
            ]);

            // 🛒 Simpan order item
            orderItem::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
                'subtotal' => $subtotal,
            ]);

            // 📦 Order ID & Midtrans
            $orderId = 'TUMBUH-' . $transaction->id . '-' . now()->timestamp;

            $itemDetails = [
                [
                    'id' => $product->id,
                    'price' => $product->price,
                    'quantity' => $quantity,
                    'name' => $product->name,
                ],
                [
                    'id' => 'shipping_' . $seller->id,
                    'price' => $shippingCost,
                    'quantity' => 1,
                    'name' => 'Ongkir - ' . strtoupper($request->shipping_name) . ' (' . strtoupper($request->shipping_service) . ')',
                ],
                [
                    'id' => 'platform_fee',
                    'price' => $platformFee,
                    'quantity' => 1,
                    'name' => 'Biaya Layanan TUMBUH',
                ]
            ];

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
                'item_details' => $itemDetails,
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
                    'shipping_cost' => $shippingCost,
                    'shipping_name' => $request->shipping_name,
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



    public function getCourierCost(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:users,id',
            'user_id' => 'required|exists:users,id',
            'weight' => 'required|integer|min:1',
        ]);

        $userAddress = UserAddress::where('user_id', $request->user_id)
            ->where('is_default', true)
            ->first();

        $sellerAddress = UserAddress::where('user_id', $request->seller_id)
            ->where('is_default', true)
            ->first();

        if (!$userAddress || !$sellerAddress) {
            return response()->json([
                'message' => 'Default address not found for user or seller',
            ], 404);
        }

        $originId = $sellerAddress->origin_id;
        $destinationId = $userAddress->origin_id;
        $weight = $request->weight;

        $couriers = ['jne', 'jnt', 'sicepat'];
        $allServices = [];
        $cached = true;

        foreach ($couriers as $courier) {
            $existing = ShippingCostDetail::where([
                'origin_id' => $originId,
                'destination_id' => $destinationId,
                'weight' => $weight,
                'code' => $courier,
            ])->get();

            if ($existing->count() > 0) {
                $allServices[$courier] = $existing->map(function ($item) {
                    return [
                        'name' => $item->name,
                        'code' => $item->code,
                        'service' => $item->service,
                        'description' => $item->description,
                        'cost' => $item->cost,
                        'etd' => $item->etd,
                    ];
                });
            } else {
                $cached = false;
                $rajaOngkirService = app(RajaOngkirService::class);

                try {
                    $cost = $rajaOngkirService->calculateDomesticCost(
                        $originId,
                        $destinationId,
                        $weight,
                        $courier
                    );

                    if (!isset($cost['data']) || !is_array($cost['data'])) {
                        continue;
                    }

                    $services = [];

                    foreach ($cost['data'] as $entry) {
                        $services[] = [
                            'name' => $entry['name'],
                            'code' => $courier,
                            'service' => $entry['service'],
                            'description' => $entry['description'] ?? null,
                            'cost' => $entry['cost'],
                            'etd' => $entry['etd'] ?? null,
                        ];

                        ShippingCostDetail::updateOrCreate([
                            'origin_id' => $originId,
                            'destination_id' => $destinationId,
                            'weight' => $weight,
                            'code' => $courier,
                            'service' => $entry['service'],
                        ], [
                            'name' => $entry['name'],
                            'description' => $entry['description'] ?? null,
                            'cost' => $entry['cost'],
                            'etd' => $entry['etd'] ?? null,
                        ]);
                    }

                    $allServices[$courier] = $services;

                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return response()->json([
            'seller_id' => $request->seller_id,
            'cached' => $cached,
            'available_couriers' => $allServices,
        ]);
    }


    public function clearUserShippingCost($sellerId, $UserId)
    {

        $originId = UserAddress::where('user_id', $sellerId)
            ->where('is_default', true)
            ->value('origin_id');

        $destinationId = UserAddress::where('user_id', $UserId)
            ->where('is_default', true)
            ->value('origin_id');

        ShippingCostDetail::where('origin_id', $originId)
            ->where('destination_id', $destinationId)
            ->delete();
    }

    public function clearShippingCost(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:users,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $this->clearUserShippingCost($request->seller_id, $request->user_id);

        return response()->json([
            'message' => 'Shipping costs cleared successfully',
        ]);
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

            $firstItem = $items->first();
            $user = optional($firstItem->product)->user;
            $sellerDetail = optional($user)->sellerDetail;
            $userAddress = optional($user)->userAddress;

            $result[] = [
                'seller' => [
                    'id' => $sellerId,
                    'storeName' => $sellerDetail?->store_name ?? $user->username,
                    'logo' => $sellerDetail?->store_logo ? 'storage/' . $sellerDetail->store_logo : null,
                    'origin_id' => $userAddress?->firstWhere('is_default', true)->origin_id ?? null,
                ],
                'items' => $items->map(function ($item) {
                    return [
                        'cart_id' => $item->id,
                        'product_id' => $item->product->id,
                        'quantity' => $item->quantity,
                        'image' => $item->product->images->first() ? 'storage/' . $item->product->images->first()->image_path : null,
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
        $transaction = transaction::with('user', 'orderItems.product')
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
                Log::info('transaction masuk settlement');
                foreach ($transaction->orderItems as $item) {
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
        $statusCode = $request->query('status_code');
        $transactionStatus = $request->query('transaction_status');

        $transaction = transaction::with('orderItems.product')
            ->where('midtrans_order_id', $invoiceId)
            ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found',
                'order_id' => $invoiceId,
            ], 404);
        }

        $date = now()->format('Y-m-d H:i:s');


        if ($transactionStatus === 'settlement' && $transaction->status !== 'paid') {
            $transaction->status = 'paid';
            $transaction->paid_at = now();
            $transaction->save();
        } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
            $transaction->status = 'failed';
            $transaction->save();
        }

        return redirect()->away("tumbuh://checkout/payment/result?order_id={$invoiceId}&status={$transactionStatus}&amount={$transaction->total_price}&date={$date}");
    }



    public function paymentError(Request $request)
    {
        $invoiceId = $request->query('order_id');
        $transactionStatus = $request->query('transaction_status');

        $transaction = transaction::with('orderItems.product')
            ->where('midtrans_order_id', $invoiceId)
            ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transaction not found',
                'order_id' => $invoiceId,
            ], 404);
        }

        $status = $transaction->status;
        $statusMessage = match ($status) {
            'pending' => 'Transaksi belum dibayar.',
            'failed' => 'Transaksi gagal atau dibatalkan.',
            'expired' => 'Transaksi kadaluarsa.',
            'cancelled' => 'Transaksi dibatalkan.',
            default => 'Status transaksi: ' . $status,
        };

        return redirect()->away("tumbuh://checkout/payment/result?order_id={$invoiceId}&status={$status}&message=" . urlencode($statusMessage));
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

        if (!$transaction->resi_number || !$transaction->shipping_name) {
            return response()->json([
                'message' => 'Resi number or shipping service not available',
            ], 404);
        }

        $trackingInfo = $this->binderByteService->track($transaction->shipping_name, $transaction->resi_number);

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
            'shipping_name' => $transaction->shipping_name,
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
        $transaction = transaction::with('orderItems.product')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($transaction->confirmed_received_at) {
            return response()->json([
                'message' => 'Transaction already confirmed received',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $transaction->update([
                'status' => 'completed',
                'confirmed_received_at' => now(),
            ]);

            foreach ($transaction->orderItems as $item) {
                $seller = $item->product->user;
                $subtotal = $item->price * $item->quantity;

                dd($seller, $subtotal);

                // Tambahkan ke saldo seller_detail
                $sellerDetail = $seller->sellerDetail;

                if ($sellerDetail) {
                    $sellerDetail->increment('saldo', $subtotal);
                }

                // Buat histori wallet
                WalletHistory::create([
                    'user_id' => $seller->id,
                    'type' => 'income',
                    'amount' => $subtotal,
                    'description' => 'Penerimaan dari transaksi #' . $transaction->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Transaction confirmed received successfully',
                'transaction' => $transaction,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to confirm transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
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