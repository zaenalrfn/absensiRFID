<?php

namespace App\Http\Controllers\Api;

use App\Events\AttendanceCreated;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Store a new attendance record from ESP32 RFID scan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uid' => ['required', 'string', 'max:50'],
            'device_id' => ['required', 'string', 'max:100'],
        ]);

        $now = now();

        // 1. Find or Register RFID Card
        $card = RfidCard::firstOrCreate(
            ['uid' => $validated['uid']],
            ['label' => 'Kartu Baru']
        );

        $card->update(['last_seen_at' => $now]);

        // 2. Check Assignment
        if (! $card->user_id) {
            return response()->json([
                'status' => 'registered',
                'message' => 'Kartu baru terdaftar/belum di-assign ke user',
            ], 200); // Arduino treats 200 as success, but shows message
        }

        $user = $card->user;

        // 3. Find Active Schedule
        $schedule = Schedule::whereTime('start_time', '<=', $now->format('H:i:s'))
            ->whereTime('end_time', '>=', $now->format('H:i:s'))
            ->first();

        // If no active schedule, try to find the "current" one based on closest end_time
        if (! $schedule) {
            $schedule = Schedule::orderByRaw('ABS(TIMESTAMPDIFF(SECOND, end_time, ?))', [$now->format('H:i:s')])->first();
        }

        if (! $schedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada jadwal aktif',
            ], 400);
        }

        // 4. Attendance Logic
        $lastAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('timestamp', today())
            ->latest('timestamp')
            ->first();

        $status = 'masuk';
        $currentTime = $now->format('H:i:s');
        $endTime = $schedule->end_time->format('H:i:s');

        if (! $lastAttendance) {
            // First tap of the day
            $status = ($currentTime < $endTime) ? 'masuk' : 'pulang';
        } elseif ($lastAttendance->status === 'masuk') {
            // Already checked in, check if it's time to check out
            if ($currentTime >= $endTime) {
                $status = 'pulang';
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sudah absen masuk. Belum jam pulang.',
                ], 400);
            }
        } else {
            // Already checked out
            return response()->json([
                'status' => 'error',
                'message' => 'Sudah absen pulang hari ini',
            ], 400);
        }

        // 5. Save Attendance
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'uid' => $validated['uid'],
            'status' => $status,
            'schedule_id' => $schedule->id,
            'device_id' => $validated['device_id'],
            'timestamp' => $now,
            'created_at' => $now,
        ]);

        // 6. Update Device Status
        Device::where('device_code', $validated['device_id'])
            ->update([
                'last_seen_at' => $now,
                'last_ip' => $request->ip(),
            ]);

        // 7. Broadcast Event
        broadcast(new AttendanceCreated($attendance))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil',
            'data' => [
                'name' => $user->name,
                'status' => $status,
                'schedule' => $schedule->name,
                'timestamp' => $now->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
