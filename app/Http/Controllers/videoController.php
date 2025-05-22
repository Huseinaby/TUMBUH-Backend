<?php

namespace App\Http\Controllers;

use App\Http\Resources\VideoResource;
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
            'data' => new VideoResource($videos),
        ]);
    }

    public function show($id)
    {
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

    public function getByModul($modulId)
    {
        $videos = Video::where('modul_id', $modulId)->get();

        if ($videos->isEmpty()) {
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
        $videoKeywords = [
            'pengertian',
            'menanam',
            'merawat',
            'ide bisnis',
        ];

        $result = [];

        foreach ($videoKeywords as $keyword) {
            $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => $keyword . ' dari tanaman ' . $title,
                'type' => 'video',
                'maxResults' => 5,
                'key' => $youtubeApiKey,
            ]);

            if (!$videoResponse->successful()) {
                $result[$keyword] = ['error' => 'Failed to fetch videos for keyword: ' . $keyword];
                continue;
            }

            $videoIds = collect($videoResponse['items'])->pluck('id.videoId')->implode(',');

            $detailVideo = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet, contentDetails',
                'id' => $videoIds,
                'key' => $youtubeApiKey,
            ]);

            $videos = collect($detailVideo['items'])->map(function ($item) {
                return [
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'] ?? '',
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
                    'description' => $video['description'],
                    'creator' => $video['creator'],
                    'duration' => $video['duration'],
                    'link' => $video['url'],
                    'thumbnail' => $video['thumbnail'],            
                    'category' => $keyword,
                    'keyword' => $keyword . ' tanaman ' . $title,
                    'nextPageToken' => $videoResponse['nextPageToken'] ?? null,
                ]);
            }

            $result[$keyword] = [
                'videos' => $videos,
                'keyword' => $keyword . ' tanaman ' . $title,
                'nextPageToken' => $videoResponse['nextPageToken'] ?? null,
            ];
            
        }
        return $result;
    }


    public function generateMoreVideo(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string',
            'modulId' => 'required|integer|exists:moduls,id',
            'nextPageToken' => 'nullable|string',
        ]);

        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $params = [
            'part' => 'snippet',
            'q' => $request->keyword,
            'type' => 'video',
            'maxResults' => 5,
            'key' => $youtubeApiKey,
        ];

        if ($request->filled('nextPageToken')) {
            $params['nextPageToken'] = $request->nextPageToken;
        }

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', $params);

        if (!$videoResponse->successful()) {
            return response()->json([
                'message' => 'Failed to fetch videos from YouTube API',
                'error' => $videoResponse->body()
            ], 500);
        }

        $videoIds = collect($videoResponse['items'])->pluck('id.videoId')->implode(',');

        $detailVideo = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet, contentDetails',
            'id' => $videoIds,
            'key' => $youtubeApiKey,
        ]);

        $videos = collect($detailVideo['items'])->map(function ($item) {
            return [
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'creator' => $item['snippet']['channelTitle'],
                'duration' => $this->convertToTime($item['contentDetails']['duration']),
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                'videoId' => $item['id'],
                'url' => 'https://www.youtube.com/watch?v=' . $item['id'],
            ];
        });

        $categories = explode(' ', $request->keyword);
        $category = implode(' ', array_slice($categories, 0, -2));

        foreach ($videos as $video) {
            Video::create([
                'modul_id' => $request->modulId,
                'title' => $video['title'],
                'description' => $video['description'],
                'creator' => $video['creator'],
                'duration' => $video['duration'],
                'link' => $video['url'],
                'thumbnail' => $video['thumbnail'],
                'category' => $category,
                'keyword' => $request->keyword,
                'nextPageToken' => $videoResponse['nextPageToken'] ?? null,
            ]);
        }
        return response()->json([
            'message' => 'Videos fetched successfully',
            'videos' => $videos,
            'nextPageToken' => $videoResponse['nextPageToken'] ?? null,
        ]);
    }

    public function convertToTime($duration)
    {
        $interval = new \DateInterval($duration);
        $minutes = ($interval->h * 60) + $interval->i;
        $seconds = $interval->s;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
