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
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OrderCreated implements ShouldBroadcastNow

{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sellerId;
    public $message;

    public function __construct($sellerId, $message)
    {
        $this->sellerId = $sellerId;
        $this->message = $message;

        Log::info("Broadcasting ke seller.{$this->sellerId} dengan pesan: {$this->message}");

    }

    public function broadcastOn()
    {
        return new Channel('seller.' . $this->sellerId);
    }

    public function broadcastAs()
    {
        return 'order.created';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
        ];
    }
}