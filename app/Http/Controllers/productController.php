<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class productController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['productCategories', 'user', 'images', 'province' ])
            ->when($request->product_category_id, fn ($q)  => $q->where('product_category_id', $request->product_category_id))
            ->when($request->search, fn ($q)  => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->province_id, fn ($q)  => $q->where('province_id', $request->province_id))
            ->latest()->get();
        
        return ProductResource::collection($products)->resolve();
    }

    public function store(Request $request){
        $request->validate([
           'name' => 'required|string|max:255',
           'description' => 'required|string',
           'price' => 'required|integer',
           'stock' => 'required|integer',
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
            'product_category_id' => $request->product_category_id,
            'province_id' => $request->province_id,
        ]);

        if($request->hasFile('image')) {
            foreach($request->file('image') as $imageFile) {
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

    public function update(Request $request, $id){
        $product  = Product::where('user_id', Auth::id())->findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|integer',
            'stock' => 'sometimes|integer',
            'product_category_id' => 'sometimes|exists:product_categories,id',
            'image' => 'nullable|array',
            'image.' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $request->only([
            'name',
            'description',
            'price',
            'stock',
            'product_category_id',
            'province_id',
        ]);
        
        $product->update($data);

        if($request->hasFile('image')) {
            foreach($request->file('image') as $imageFile) {
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

    public function destroy($id){
        $product = Product::where('user_id', Auth::id())->findOrFail($id);

        if($product->image){
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    public function show($id){
        $product = Product::with(['productCategories', 'user'])->findOrFail($id);

        return ProductResource::make($product)->resolve();
    }

    public function getProductByUser($userId){
        $products = Product::with(['productCategories', 'user'])
            ->where('user_id', $userId)
            ->latest()
            ->get();

        return ProductResource::collection($products)->resolve();
    }
}
