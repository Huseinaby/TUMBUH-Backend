<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostImages;
use Auth;
use Illuminate\Http\Request;
use Storage;

class PostController extends Controller
{
    public function index($groupId)
    {
        $posts = Post::where('group_id', $groupId)
            ->with(['user'])
            ->withCount('likedBy')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => PostResource::collection($posts),
        ]);
    }

    public function store(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);
        $isMember = $group->members()->where('user_id', Auth::id())->exists();

        if (!$isMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|array', 
            'image.*' => 'image|max:2048', 
        ]);

        $post = Post::create([
            'user_id' => Auth::id(),
            'group_id' => $groupId,
            'title' => $request->title,
            'content' => $request->get('content'),
        ]);

        if($request->hasFile('image')) {
            foreach($request->file('image') as $image) {
                $imagePath = $image->store('post_images', 'public');
                
                PostImages::create([
                    'post_id' => $post->id,
                    'image_path' => $imagePath,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => PostResource::make($post),
        ]);
    }

    public function show($id)
    {
        $post = Post::with(['user'])
            ->withCount('likedBy')
            ->findOrFail($id);

        if (!$post) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => PostResource::make($post),
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

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'image' => 'nullable|array',
            'image.*' => 'image|max:2048',
        ]);

        $data = $request->only(['title', 'content']);

        $post->update($data);

        if($request->hasFile('image')) {
            foreach($request->file('image') as $image) {
                $imagePath = $image->store('post_images', 'public');
                
                PostImages::create([
                    'post_id' => $post->id,
                    'image_path' => $imagePath,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => PostResource::make($post),
        ]);
    }

    public function destroyImage($id){
        $image = PostImages::findOrFail($id);

        if (!$image) {
            return response()->json([
                'status' => 'error',
                'message' => 'Image not found',
            ], 404);
        }

        if ($image->post->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized action',
            ], 403);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Image deleted successfully',
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
    
        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Post deleted successfully',
        ]);
    }

    public function toggleLikePost($postId)
    {
        $user = Auth::user();
        $post = Post::findOrFail($postId);

        $alreadyLiked = $post->likedBy()->where('user_id', $user->id)->exists();

        if($alreadyLiked) {
            $post->likedBy()->detach($user->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Post unliked successfully',
            ]);

        } else {
            $post->likedBy()->attach($user->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Post liked successfully',
            ]);
        }
    }
}
