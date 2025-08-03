<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Auth;
use Illuminate\Http\Request;
use Storage;

class PostController extends Controller
{
    public function index()
    {
        $post = Post::where('group_id', 1)
            ->with(['user'])
            ->latest()
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $post,
        ]);
    }

    public function store(Request $request, $groupId)
    {
        $validateData = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048', // Optional image validation
        ]);

        $validateData['user_id'] = Auth::id();
        $validateData['group_id'] = $groupId;

        if($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('post_images', 'public');
            $validateData['image'] = $imagePath;
        }

        $post = Post::create($validateData);

        return response()->json([
            'status' => 'success',
            'data' => $post,
        ]);
    }

    public function show($id)
    {
        $post = Post::with(['user'])->findOrFail($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $post,
        ]);
    }

    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        if ($post->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action',
            ], 403);
        }

        $validateData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'image' => 'nullable|image|max:2048', // Optional image validation
        ]);

        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($post->image) {
                Storage::disk('public')->delete($post->image);
            }
            $imagePath = $request->file('image')->store('post_images', 'public');
            $validateData['image'] = $imagePath;
        }

        $post->update($validateData);

        return response()->json([
            'status' => 'success',
            'data' => $post,
        ]);
    }

    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        if ($post->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action',
            ], 403);
        }

        // Delete the image if it exists
        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Post deleted successfully',
        ]);
    }
}
