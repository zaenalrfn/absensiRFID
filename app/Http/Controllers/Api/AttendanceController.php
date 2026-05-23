<?php

namespace App\Http\Controllers\Api;

use App\Events\AttendanceCreated;
use App\Events\RfidCardScanned;
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

        broadcast(new RfidCardScanned($card))->toOthers();

        // 2. Check Assignment
        if (! $card->user_id) {
            return response()->json([
                'status' => 'registered',
                'message' => 'Kartu baru terdaftar/belum di-assign ke user',
            ], 200); // Arduino treats 200 as success, but shows message
        }

        $user = $card->user;

        // 3. Find Last Attendance to Determine Status or Checkout Schedule
        $lastAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('timestamp', today())
            ->latest('timestamp')
            ->first();

        $status = 'masuk';
        $currentTime = $now->format('H:i:s');

        if ($lastAttendance && $lastAttendance->status === 'masuk') {
            // Already checked in, user wants to check out!
            // Retrieve the schedule they checked in to
            $schedule = $lastAttendance->schedule;
            $status = 'pulang';

            if (! $schedule) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Jadwal tidak ditemukan',
                ], 400);
            }

            // Validate if it is time to check out
            $endTime = $schedule->end_time->format('H:i:s');
            if ($currentTime < $endTime) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sudah absen masuk. Belum jam pulang.',
                ], 400);
            }
        } else {
            // No attendance today, or already checked out from a previous shift.
            // User wants to check in (absen masuk) for a new shift.

            // Find Active Schedule for checking in
            $schedule = Schedule::whereTime('start_time', '<=', $currentTime)
                ->whereTime('end_time', '>=', $currentTime)
                ->first();

            // If no active schedule, try to find the "closest" one based on closest start_time or end_time
            if (! $schedule) {
                $schedule = Schedule::all()->sortBy(function (Schedule $s) use ($currentTime) {
                    $startSec = strtotime($s->start_time->format('H:i:s'));
                    $endSec = strtotime($s->end_time->format('H:i:s'));
                    $currentSec = strtotime($currentTime);

                    $diffStart = abs($startSec - $currentSec);
                    $diffEnd = abs($endSec - $currentSec);

                    return min($diffStart, $diffEnd);
                })->first();
            }

            if (! $schedule) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada jadwal aktif',
                ], 400);
            }

            $endTime = $schedule->end_time->format('H:i:s');
            $status = ($currentTime < $endTime) ? 'masuk' : 'pulang';

            // If they already completed this schedule today, prevent duplicate check-out
            if ($lastAttendance && $lastAttendance->status === 'pulang') {
                $hasCompletedThisSchedule = Attendance::where('user_id', $user->id)
                    ->whereDate('timestamp', today())
                    ->where('schedule_id', $schedule->id)
                    ->where('status', 'pulang')
                    ->exists();

                if ($hasCompletedThisSchedule) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Sudah absen pulang hari ini',
                    ], 400);
                }
            }
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

        // 6. Update Device Status (Create if not exists)
        $device = Device::firstOrCreate(
            ['device_code' => $validated['device_id']],
            [
                'device_name' => 'Device Baru',
                'created_at' => $now,
            ]
        );

        $device->update([
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
