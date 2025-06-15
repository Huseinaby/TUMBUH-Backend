<?php

namespace App\Http\Controllers;

use App\Models\orderItem;
use App\Models\Review;
use App\Models\transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class reviewController extends Controller
{

    public function getOrderItem(){
        $user = Auth::user();

        $items = orderItem::with(['product', 'review'])
            ->whereHas('transaction', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', 'completed');
            })->get()
            ->map(function ($item) {
                $image = $item->product->images()->first();
                return [
                    'order_item_id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'image' => $image ? asset('storage/' . $image->image_path) : null,
                    ],
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'review' => $item->review ? [
                        'id' => $item->review->id,
                        'rating' => $item->review->rating,
                        'comment' => $item->review->comment,
                        'created_at' => $item->review->created_at,
                    ] : null,
                ];
            });
        
        return response()->json($items);
        
    }
    public function store(Request $request)
    {
        $request->validate([
            'order_item_id' => 'required|exists:order_items,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        $orderItem = orderItem::with('transaction')->findOrFail($request->order_item_id);

        if($orderItem->transaction->user_id !== $user->id) {
            return response()->json([
                'message' => 'You are not authorized to review this order item',
            ], 403);
        }

        if($orderItem->transaction->shipping_status !== 'completed') {
            return response()->json([
                'message' => 'You can only review completed transactions',
            ], 400);
        }

        if($orderItem->review) {
            return response()->json([
                'message' => 'You have already reviewed this order item',
            ], 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $orderItem->product_id,
            'order_item_id' => $orderItem->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review created successfully',
            'review' => $review,
        ], 201);
    }

    public function getReviewsByProduct($productId)
    {
        $reviews = Review::where('product_id', $productId)
            ->with('user:id,name,photo')
            ->get();

        return response()->json($reviews);
    }

    public function getReviewsByUser()
    {
        $reviews = Review::where('user_id', Auth::id())
            ->with('product:id,name,photo')
            ->get();

        return response()->json($reviews);
    }

    public function updateReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $review = Review::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$review) {
            return response()->json([
                'message' => 'Review not found or you are not authorized to update this review',
            ], 404);
        }

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review updated successfully',
        ]);
    }

    public function deleteReview($id)
    {
        $review = Review::where('id', $id)
            ->firstOrFail();

        if (!$review) {
            return response()->json([
                'message' => 'Review not found or you are not authorized to delete this review',
            ], 404);
        }

        if ($review->user_id !== Auth::id() || Auth::user()->role !== 'admin') {
            return response()->json([
                'message' => 'You are not authorized to delete this review',
            ], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully',
        ]);
    }
}
