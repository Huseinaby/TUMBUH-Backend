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

    public function generateVideos($videoKeyword, $modulId)
    {
        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => $videoKeyword,
            'type' => 'video',
            'maxResults' => 3,
            'key' => $youtubeApiKey,
        ]);

        $videoIds = collect($videoResponse['items'])->pluck('id.videoId')->implode(',');

        $detailVideo = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet, contentDetails',
            'id' => $videoIds,
            'key' => $youtubeApiKey,
        ]);

        $videos = collect($detailVideo['items'])->map(function ($item) {
            return [
                'title' => $item['snippet']['title'],
                'desription' => $item['snippet']['description'],
                'creator' => $item['snippet']['channelTitle'],
                'duration' => $this->convertToTime($item['contentDetails']['duration']),
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                'videoId' => $item['id'],
                'url' => 'https://www.youtube.com/watch?v=' . $item['id'],
            ];
        });

        foreach ($videos as $video) {
            Video::create([
                'modul_id' => $modulId,
                'title' => $video['title'],
                'desription' => $video['desription'],
                'creator' => $video['creator'],
                'duration' => $video['duration'],
                'link' => $video['url'],
                'thumbnail' => $video['thumbnail'],
            ]);
        }
        return $videos;
    }

    public function generateMoreVideo(Request $request){
        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => $request->videoKeyword,
            'type' => 'video',
            'maxResults' => 10,
            'key' => $youtubeApiKey,
        ]);

        $videoIds = collect($videoResponse['items'])->pluck('id.videoId')->implode(',');

        $detailVideo = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet, contentDetails',
            'id' => $videoIds,
            'key' => $youtubeApiKey,
        ]);

        $videos = collect($detailVideo['items'])->map(function ($item) {
            return [
                'title' => $item['snippet']['title'],
                'desription' => $item['snippet']['description'],
                'creator' => $item['snippet']['channelTitle'],
                'duration' => $this->convertToTime($item['contentDetails']['duration']),
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                'videoId' => $item['id'],
                'url' => 'https://www.youtube.com/watch?v=' . $item['id'],
            ];
        });

        foreach ($videos as $video) {
            Video::create([
                'modul_id' => $request->modulId,
                'title' => $video['title'],
                'desription' => $video['desription'],
                'creator' => $video['creator'],
                'duration' => $video['duration'],
                'link' => $video['url'],
                'thumbnail' => $video['thumbnail'],
            ]);
        }
        return $videos;
    }

    public function convertToTime($duration){
        $interval = new \DateInterval($duration);
        $minutes = ($interval->h * 60) + $interval->i;
        $seconds = $interval->s;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
