<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\ProductCategories;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerDetail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class productController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['productCategories', 'user', 'images', 'province', 'reviews'])
            ->when($request->product_category_id, fn($q) => $q->where('product_category_id', $request->product_category_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->province_id, fn($q) => $q->where('province_id', $request->province_id))
            ->latest()->paginate(6);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'products' => ProductResource::collection($products)->resolve(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'next_page_url' => $products->nextPageUrl(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);

    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|integer',
            'stock' => 'required|integer',
            'weight' => 'nullable|integer',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'province_id' => 'nullable|exists:provinces,id',
            'image' => 'nullable|array',
            'image.' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $product = Product::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'weight' => $request->weight,
            'product_category_id' => $request->product_category_id,
            'province_id' => $request->province_id,
        ]);

        if ($request->hasFile('image')) {
            foreach ($request->file('image') as $imageFile) {
                $path = $imageFile->store('products', 'public');

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('images'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|integer',
            'stock' => 'sometimes|integer',
            'weight' => 'sometimes|integer',
            'product_category_id' => 'sometimes|exists:product_categories,id',
            'image' => 'nullable|array',
            'image.' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $request->only([
            'name',
            'description',
            'price',
            'stock',
            'weight',
            'product_category_id',
            'province_id',
        ]);

        $product->update($data);

        if ($request->hasFile('image')) {
            foreach ($request->file('image') as $imageFile) {
                $path = $imageFile->store('products', 'public');

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                ]);
            }
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('images'),
        ]);
    }

    public function destroyImage($id)
    {
        $image = ProductImage::findOrFail($id);

        if ($image->product->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully']);
    }

    public function destroy($id)
    {
        $product = Product::where('user_id', Auth::id())->findOrFail($id);

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    public function show($id)
    {
        $product = Product::with(['productCategories', 'user.sellerDetail', 'reviews'])->findOrFail($id);

        return ProductResource::make($product)->resolve();
    }

    public function getProductByUser($userId)
    {
        $seller = User::findOrFail($userId);
        $sellerDetail = SellerDetail::where('user_id', $userId)->first();

        $products = Product::with([
            'productCategories',
            'images',
            'user',
            'reviews' => function ($query) {
                $query->select('id', 'product_id', 'user_id', 'rating', 'comment', 'created_at')
                    ->latest();
            },
            'reviews.user' => function ($query) {
                $query->select('id', 'username', 'photo');
            },
        ])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('user_id', $userId)
            ->latest()
            ->get();


        // Kategori
        $categories = $products
            ->pluck('productCategories')
            ->filter()
            ->unique('id')
            ->values()
            ->map(function ($category) use ($products) {
                $productCount = $products->filter(function ($product) use ($category) {
                    return $product->productCategories && $product->productCategories->id == $category->id;
                })->count();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'count' => $productCount,
                ];
            });

        // Produk
        $productList = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'image' => asset($product->images->first()->image_path) ?? null,
                'categories' => $product->productCategories->name ?? 'No Category',
                'rating' => round($product->reviews_avg_rating ?? 0, 1),
                'rating_count' => $product->reviews_count,
            ];
        });

        // Ambil semua review dari produk-produk milik seller
        $allReviews = $products->flatMap(function ($product) {
            return $product->reviews->map(function ($review) use ($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'username' => $review->user->username ?? 'Unknown',
                    'imageUser' => $review->user->photo ? asset($review->user->photo) : null,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'date' => $review->created_at->format('Y-m-d'),
                ];
            });
        })->sortByDesc('reviewed_at')->values()->take(5); // tampilkan 5 terbaru


        // Seller rating & total reviews (diambil dari semua produk)
        $totalReviewCount = $products->sum('reviews_count');
        $totalReviewScore = $products->sum(function ($product) {
            return ($product->reviews_avg_rating ?? 0) * $product->reviews_count;
        });

        $sellerAvgRating = $totalReviewCount > 0 ? round($totalReviewScore / $totalReviewCount, 1) : 0;

        return response()->json([
            'store' => [
                'id' => $seller->id,
                'store_name' => $sellerDetail->store_name ?? 'No Store Name',
                'store_description' => $sellerDetail->store_description ?? 'No Store Description',
                'store_address' => $sellerDetail->store_address ?? 'No Store Address',
                'store_phone' => $sellerDetail->store_phone ?? 'No Store Phone',
                'store_logo' => $sellerDetail && $sellerDetail->store_logo ? Storage::url($sellerDetail->store_logo) : null,
                'store_banner' => $sellerDetail && $sellerDetail->store_banner ? Storage::url($sellerDetail->store_banner) : null,
                'product_count' => $products->count(),
                'store_rating' => [
                    'rating' => $sellerAvgRating,
                    'rating_count' => $totalReviewCount,
                ]
            ],
            'tabs' => [
                'products' => $productList,
                'categories' => $categories,
                'reviews' => $allReviews, // bisa diisi nanti dengan review terbaru jika dibutuhkan
            ]
        ]);
    }

    public function getCategory(){
        $categories = ProductCategories::all();

        return response()->json([
            'message' => 'Product categories retrieved successfully',
            'categories' => $categories,
        ]);
    }

}
