<?php

namespace App\Services;

use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService
{
    public function sendToToken(string $token, string $title, string $body)
    {
        $messaging = Firebase::messaging();

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create($title, $body));

        return $messaging->send($message);
    }
}
