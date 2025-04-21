<?php

namespace App\Http\Controllers;

use Google\Service\CloudTrace\Module;
use Illuminate\Http\Request;
use App\Models\modul;

class modulController extends Controller
{
    public function index()
    {
        $moduls = Modul::all();

        return response()->json([
            'message' => 'semua modul',
            'data' => $moduls
        ]);
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $modul = Modul::create($request->all());

        return response()->json([
            'message' => 'Modul created successfully',
            'data' => $modul
        ], 201);
    }

    public function show($id)
    {
        $modul = Modul::find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Modul found',
            'data' => $modul
        ]);
    }

    public function update(Request $request, $id)
    {

        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string|max:255',
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $modul = Modul::find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        $modul->update($request->all());

        return response()->json([
            'message' => 'Modul found',
            'data' => $modul
        ]);
    }

    public function destroy($id)
    {
        $modul = Modul::find($id);

        if (!$modul) {
            return response()->json([
                'message' => 'Modul not found'
            ], 404);
        }

        $modul->delete();

        return response()->json([
            'message' => 'Modul deleted successfully'
        ]);
    }
}
