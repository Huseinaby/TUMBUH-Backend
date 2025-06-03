<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class reviewController extends Controller
{
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

        return response()->json([
            'message' => 'Review submitted successfully',
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
