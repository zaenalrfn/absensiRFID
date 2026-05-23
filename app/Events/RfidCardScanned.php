<?php

namespace App\Events;

use App\Models\RfidCard;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RfidCardScanned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public RfidCard $card)
    {
        $this->card->load('user:id,name');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('attendance-channel')];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->card->id,
            'uid' => $this->card->uid,
            'user_id' => $this->card->user_id,
            'label' => $this->card->label,
            'last_seen_at' => $this->card->last_seen_at ? $this->card->last_seen_at->toIso8601String() : null,
            'user' => $this->card->user ? [
                'id' => $this->card->user->id,
                'name' => $this->card->user->name,
            ] : null,
        ];
    }
}
