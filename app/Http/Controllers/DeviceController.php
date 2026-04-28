<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Devices/Index', [
            'devices' => Device::query()
                ->latest('last_seen_at')
                ->get()
                ->map(fn (Device $d) => [
                    'id' => $d->id,
                    'device_code' => $d->device_code,
                    'device_name' => $d->device_name,
                    'location' => $d->location,
                    'last_seen_at' => $d->last_seen_at?->format('Y-m-d H:i:s'),
                    'last_ip' => $d->last_ip,
                    'is_online' => $d->isOnline(),
                ]),
        ]);
    }
}
