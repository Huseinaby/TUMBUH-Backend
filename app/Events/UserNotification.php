<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UserNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;
    public $message;
    public $type;

    /**
     * Create a new event instance.
     */
    public function __construct($userId, $message, $type = 'info')
    {
        $this->userId = $userId;
        $this->message = $message;
        $this->type = $type;

        Log::info("Broadcasting notification to user {$this->userId} with message: {$this->message} and type: {$this->type}");
    }


    public function broadcastOn()
    {
        return new Channel('user.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'user.notification';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
        ];
    }
}
