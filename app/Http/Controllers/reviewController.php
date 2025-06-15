<?php

namespace App\Http\Controllers;

use App\Models\orderItem;
use App\Models\Review;
use App\Models\transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class reviewController extends Controller
{

    public function getOrderItem()
    {
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

        $orderItem = orderItem::with('transaction')
            ->where('id', $request->order_item_id)
            ->firstOrFail();

        if ($orderItem->transaction->user_id !== $user->id || $orderItem->transaction->status !== 'completed') {
            return response()->json([
                'message' => 'You are not authorized to review this order item or the transaction is not completed.',
            ], 403);
        }

        $existingReview = Review::where('order_item_id', $request->order_item_id)
            ->where('user_id', $user->id)
            ->first();
        if ($existingReview) {
            return response()->json([
                'message' => 'You have already reviewed this order item.',
            ], 400);
        }

        $review = Review::create([
            'order_item_id' => $request->order_item_id,
            'user_id' => $user->id,
            'product_id' => $orderItem->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        $orderItem->update(['review_id' => $review->id]);

        return response()->json([
            'message' => 'Review created successfully',
            'review' => $review,
        ]);
    }

    public function getReviewsByProduct($productId)
    {
        $reviews = Review::where('product_id', $productId)
            ->with('user')
            ->get();

        return response()->json($reviews);
    }

    public function getReviewsByUser($userId)
    {
        $reviews = Review::where('user_id', $userId)
            ->with('product', 'orderItem')
            ->get();

        return response()->json($reviews);
    }


    public function updateReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|max:500',
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
            'review' => $review,
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
        if ($review->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
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
