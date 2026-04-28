<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Attendance $attendance)
    {
        $this->attendance->load(['user', 'schedule']);
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
            'id' => $this->attendance->id,
            'user_name' => $this->attendance->user->name,
            'status' => $this->attendance->status,
            'schedule' => $this->attendance->schedule?->name ?? 'Di luar jadwal',
            'device_id' => $this->attendance->device_id,
            'timestamp' => $this->attendance->timestamp->format('Y-m-d H:i:s'),
        ];
    }
}
