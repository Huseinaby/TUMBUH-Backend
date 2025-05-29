<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartGroupResource;
use Illuminate\Http\Request;
use App\Models\cartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class cartController extends Controller
{
    public function index()
    {
        $cart = cartItem::with(['product.user', 'product.images'])->where('user_id', Auth::id())->get();

        if($cart->isEmpty()) {
            return response()->json([
                'message' => 'Cart is empty',
            ]);
        }

        $grouped = $cart->groupBy(fn ($item) => $item->product->user_id)->map(function ($items, $sellerId){
            return (object)[
                'seller' => $items->first()->product->user,
                'items' => $items
            ];
        });

        $total = $grouped->sum(function ($group) {
            return collect($group->items)->sum(fn ($item) => $item->product->price * $item->quantity);
        });

        return response()->json([
            'cart' => CartGroupResource::collection($grouped->values()),
            'total' => $total,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $existing = cartItem::where('user_id', Auth::id())
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            $existing->quantity += $request->quantity;
            $existing->save();
        } else {
            cartitem::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]); 
        }

        return response()->json([
            'message' => 'Item added to cart successfully',
        ]);
    }

    public function update(Request $request, $id){
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $item = cartItem::where('user_id', Auth::id())->findOrFail($id);
        $item->quantity = $request->quantity;
        $item->save();

        return response()->json([
            'message' => 'Item updated successfully',
        ]);
    }

    public function destroy($id){
        $item = cartItem::where('user_id', Auth::id())->findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart successfully',
        ]);
    }
}
