<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class productController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['category', 'user'])
            ->when($request->category_id, fn ($q)  => $q->where('category_id', $request->category_id))
            ->when($request->location, fn ($q)  => $q->where('location', 'like', "%{$request->location}%"))
            ->when($request->search, fn ($q)  => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(10);
        
        return response()->json($products);
    }

    public function store(Request $request){
        $request->validate([
           'name' => 'required|string|max:255',
           'description' => 'required|string',
           'location' => 'nullable|string|max:255',
           'price' => 'required|integer',
           'stock' => 'required|integer',
           'category_id' => 'nullable|exists:product_categories,id',
           'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $product = Product::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'location' => $request->location,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'image' => $request->file('image') ? $request->file('image')->store('products', 'public') : null,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    public function 
}
