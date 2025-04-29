<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class videoController extends Controller
{
    //

    public function generateVideos(Request $request, $modulId)
    {
        $youtubeApiKey = env('YOUTUBE_API_KEY');

        $videoResponse = Http::get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => $request->title,
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
