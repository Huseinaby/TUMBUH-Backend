<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\PostComment;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function index($postId)
    {
        $comments = PostComment::where('post_id', $postId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(
            [
                'message' => 'Comments retrieved successfully',
                'comments' => CommentResource::collection($comments),
            ]
        );
    }

    public function store(Request $request, $postId)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $post = PostComment::find($postId);

        $comment = $post->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => CommentResource::make($comment->load('user')),
        ], 201);
    }

    public function show($commentId)
    {
        $comment = PostComment::with('user')->findOrFail($commentId);
        return response()->json([
            'message' => 'Comment retrieved successfully',
            'comment' => CommentResource::make($comment),
        ]);
    }

    public function update(Request $request, $commentId)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment = PostComment::findOrFail($commentId);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->update([
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => CommentResource::make($comment->load('user')),
        ]);
    }

    public function destroy($commentId)
    {
        $comment = PostComment::findOrFail($commentId);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
