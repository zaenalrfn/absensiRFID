<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleStoreRequest;
use App\Http\Requests\ScheduleUpdateRequest;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Schedules/Index', [
            'schedules' => Schedule::latest('id')->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Schedules/Form');
    }

    public function store(ScheduleStoreRequest $request): RedirectResponse
    {
        Schedule::create($request->validated());

        return redirect()->route('schedules.index')
            ->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('Schedules/Form', [
            'schedule' => $schedule,
        ]);
    }

    public function update(ScheduleUpdateRequest $request, Schedule $schedule): RedirectResponse
    {
        $schedule->update($request->validated());

        return redirect()->route('schedules.index')
            ->with('success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('success', 'Jadwal berhasil dihapus.');
    }
}
