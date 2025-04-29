<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class videoController extends Controller
{
    //
    public function index()
    {
        $videos = Video::all();

        return response()->json([
            'message' => 'semua video',
            'data' => $videos
        ]);
    }

    public function show($id){
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Video found',
            'data' => $video
        ]);
    }

    public function destroy($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found'
            ], 404);
        }

        $video->delete();

        return response()->json([
            'message' => 'Video deleted successfully'
        ]);
    }

    public function getByModul($modulId){
        $videos = Video::where('modul_id', $modulId)->get();

        if($videos->isEmpty()) {
            return response()->json([
                'message' => 'No videos found for this module'
            ], 404);
        }

        return response()->json([
            'message' => 'Videos found',
            'data' => $videos
        ]);
    }

    public function generateVideos($title, $modulId)
    {
        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => $title,
            'type' => 'video',
            'maxResults' => 3,
            'key' => $youtubeApiKey,
        ]);

        $videos = collect($videoResponse['items'])->map(function ($item) {
            return [
                'title' => $item['snippet']['title'],
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                'videoId' => $item['id']['videoId'],
                'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId'],
            ];
        });

        foreach ($videos as $video) {
            Video::create([
                'modul_id' => $modulId,
                'title' => $video['title'],
                'link' => $video['url'],
                'thumbnail' => $video['thumbnail'],
            ]);
        }
        return $videos;
    }

    public function generateMoreVideo($title, $modulId){
        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => $title,
            'type' => 'video',
            'maxResults' => 3,
            'key' => $youtubeApiKey,
        ]);

        $videos = collect($videoResponse['items'])->map(function ($item) {
            return [
                'title' => $item['snippet']['title'],
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                'videoId' => $item['id']['videoId'],
                'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId'],
            ];
        });

        foreach ($videos as $video) {
            Video::create([
                'modul_id' => $modulId,
                'title' => $video['title'],
                'link' => $video['url'],
                'thumbnail' => $video['thumbnail'],
            ]);
        }
        return $videos;
    }
}
