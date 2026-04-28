<?php

namespace App\Http\Controllers\Api;

use App\Events\AttendanceCreated;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Schedule;
use App\Models\User;
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

        // Find user by RFID UID
        $user = User::where('rfid_uid', $validated['uid'])->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID tidak terdaftar',
            ], 404);
        }

        // Determine check-in or check-out status
        $lastAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('timestamp', today())
            ->latest('timestamp')
            ->first();

        $status = 'masuk';
        if ($lastAttendance) {
            $status = $lastAttendance->status === 'masuk' ? 'pulang' : 'masuk';
        }

        // Detect active schedule
        $now = now();
        $schedule = Schedule::whereTime('start_time', '<=', $now->format('H:i:s'))
            ->whereTime('end_time', '>=', $now->format('H:i:s'))
            ->first();

        // Save attendance
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'uid' => $validated['uid'],
            'status' => $status,
            'schedule_id' => $schedule?->id,
            'device_id' => $validated['device_id'],
            'timestamp' => $now,
            'created_at' => $now,
        ]);

        // Update device last_seen
        Device::where('device_code', $validated['device_id'])
            ->update([
                'last_seen_at' => $now,
                'last_ip' => $request->ip(),
            ]);

        // Broadcast event
        broadcast(new AttendanceCreated($attendance))->toOthers();

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil',
            'data' => [
                'name' => $user->name,
                'status' => $status,
                'schedule' => $schedule?->name ?? 'Di luar jadwal',
                'timestamp' => $now->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
