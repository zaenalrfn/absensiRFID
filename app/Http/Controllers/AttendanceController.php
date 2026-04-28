<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Attendance::with(['user:id,name', 'schedule:id,name'])
            ->latest('timestamp');

        if ($request->filled('date')) {
            $query->whereDate('timestamp', $request->input('date'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate(20)->withQueryString();

        $paginator->through(fn (Attendance $a) => [
            'id' => $a->id,
            'user_name' => $a->user->name,
            'uid' => $a->uid,
            'status' => $a->status,
            'schedule' => $a->schedule?->name ?? 'Di luar jadwal',
            'device_id' => $a->device_id,
            'timestamp' => $a->timestamp->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Attendances/Index', [
            'attendances' => $paginator,
            'filters' => $request->only(['date', 'user_id', 'status']),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
        ]);
    }
}
