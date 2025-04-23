<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Article;

class articleController extends Controller
{
    public function index(){
        $articles = Article::all();

        return response()->json([
            'message' => 'semua artikel',
            'data' => $articles
        ]);
    }

    public function store(Request $request){
        $validator = validator($request->all(), [
            'modul_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'link' => 'required|url',
            'snippet' => 'required|string',
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $article = Article::create($request->all());

        return response()->json([
            'message' => 'Artikel created successfully',
            'data' => $article
        ], 201);
    }
    

    public function show($id){
        $article = Article::find($id);

        if(!$article){
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Artikel found',
            'data' => $article
        ]);
    }

    public function update(Request $request, $id){
        $article = Article::find($id);

        if(!$article){
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        $validator = validator($request->all(), [
            'modul_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'link' => 'required|url',
            'snippet' => 'required|string',
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $article->update($request->all());

        return response()->json([
            'message' => 'Artikel updated successfully',
            'data' => $article
        ]);
    }

    public function destroy($id){
        $article = Article::find($id);

        if(!$article){
            return response()->json([
                'message' => 'Artikel not found'
            ], 404);
        }

        $article->delete();

        return response()->json([
            'message' => 'Artikel deleted successfully'
        ]);
    }
}
