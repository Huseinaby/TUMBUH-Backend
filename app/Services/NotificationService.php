<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Notification;

class NotificationService
{
    protected $expoUrl = 'https://exp.host/--/api/v2/push/send';

    public function sendToUser($user, $title, $body, $type, $data = [])
    {
        $token = $user->fcm_token;

        if (!$token) return false;

        // Kirim ke Expo
        $response = Http::post($this->expoUrl, [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'channelId' => 'default',
            'data' => $data,
        ]);

        // Simpan ke database
        Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => json_encode($data),
            'is_read' => false,
        ]);

        return $response->successful();
    }
}
