<?php

namespace App\Http\Controllers;

use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\GroupMember;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Storage;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('members')
            ->with('createdBy')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => GroupResource::collection($groups),
        ]);
    }

    public function store(Request $request)
    {
        $validateData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $validateData['city'] = Str::title($validateData['city'] ?? '');
        $validateData['slug'] = Str::slug($validateData['name']);
        $validateData['created_by'] = Auth::id();        

        if($request->hasFile('cover_image')) {
            $imagePath = $request->file('cover_image')->store('group_covers', 'public');
            $validateData['cover_image'] = $imagePath;
        }

        $group = Group::create($validateData);

        $group->members()->create([
            'user_id' => Auth::id(),    
            'role' => 'admin', // Default role for the creator
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Group created successfully.',
            'data' => GroupResource::make($group),
        ]);
    }

    public function show($id)
    {
        $group = Group::with(['createdBy'])
            ->findOrFail($id);

        if(!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => GroupResource::make($group),
        ]);
    }

    public function update(Request $request, $id)
    {
        $group = Group::findOrFail($id);

        if(!$group) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group not found.',
            ], 404);
        }

        if ($group->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to update this group.',
            ], 403);
        }

        $validateData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'city' => 'sometimes|string|max:100',
            'cover_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if(isset($validateData['city'])) {
            $validateData['city'] = Str::title($validateData['city']);
        }

        if (isset($validateData['name'])) {
            $validateData['slug'] = Str::slug($validateData['name']);
        }

        if ($request->hasFile('cover_image')) {
            // Delete old cover image if exists
            if ($group->cover_image) {
                Storage::disk('public')->delete($group->cover_image);
            }
            $imagePath = $request->file('cover_image')->store('group_covers', 'public');
            $validateData['cover_image'] = $imagePath;
        }

        $group->update($validateData);

        return response()->json([
            'status' => 'success',
            'message' => 'Group updated successfully.',
            'data' => GroupResource::make($group),
        ]);
    }

    public function destroy($id)
    {
        $group = Group::findOrFail($id);

        if ($group->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete this group.',
            ], 403);
        }

        if($group->cover_image) {
            // Delete cover image if exists
            Storage::disk('public')->delete($group->cover_image);
        }

        $group->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Group deleted successfully.',
        ]);
    }

    public function join($id)
    {
        $group = Group::findOrFail($id);

        if ($group->members()->where('user_id', Auth::id())->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already a member of this group.',
            ], 400);
        }

        $group->members()->create([
            'user_id' => Auth::id(),
            'role' => 'member', // Default role for new members
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'You have joined the group successfully.',
            'data' => GroupResource::make($group),
        ]);
    }

    public function leave($id){
        $group = Group::findOrFail($id);

        $member = $group->members()->where('user_id', Auth::id())->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not a member of this group.',
            ], 400);
        }

        // Prevent the creator from leaving the group
        if ($group->created_by === Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'The group creator cannot leave the group.',
            ], 403);
        }

        $member->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'You have left the group successfully.',
        ]);
    }
}