<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'data' => $groups,
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
            'data' => $group->load('createdBy', 'members'),
        ]);
    }
}
