<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Device;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with today's stats and recent attendances.
     */
    public function index(Request $request): Response
    {
        $today = today();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_today' => Attendance::whereDate('timestamp', $today)->count(),
                'masuk' => Attendance::whereDate('timestamp', $today)->where('status', 'masuk')->count(),
                'pulang' => Attendance::whereDate('timestamp', $today)->where('status', 'pulang')->count(),
                'active_devices' => Device::whereNotNull('last_seen_at')
                    ->where('last_seen_at', '>=', now()->subMinutes(10))
                    ->count(),
            ],
            'recentAttendances' => Attendance::with(['user:id,name', 'schedule:id,name'])
                ->latest('timestamp')
                ->take(20)
                ->get()
                ->map(fn (Attendance $a) => [
                    'id' => $a->id,
                    'user_name' => $a->user->name,
                    'uid' => $a->uid,
                    'status' => $a->status,
                    'schedule' => $a->schedule?->name ?? 'Di luar jadwal',
                    'device_id' => $a->device_id,
                    'timestamp' => $a->timestamp->format('Y-m-d H:i:s'),
                ]),
        ]);
    }
}
