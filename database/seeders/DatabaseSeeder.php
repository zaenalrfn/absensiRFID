<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'rfid_uid' => null,
        ]);

        // Regular users with RFID cards
        $users = collect();
        $users->push(User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'rfid_uid' => 'A1B2C3D4',
        ]));
        $users->push(User::factory()->create([
            'name' => 'Sari Dewi',
            'email' => 'sari@example.com',
            'rfid_uid' => 'E5F6A7B8',
        ]));
        $users->push(User::factory()->create([
            'name' => 'Andi Pratama',
            'email' => 'andi@example.com',
            'rfid_uid' => '11223344',
        ]));

        // Schedules
        $shiftPagi = Schedule::create([
            'name' => 'Shift Pagi',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
        $shiftSiang = Schedule::create([
            'name' => 'Shift Siang',
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);

        // Devices
        Device::factory()->online()->create([
            'device_code' => 'ESP32-WOKWI-SIM',
            'device_name' => 'Wokwi Simulator',
            'location' => 'Virtual / Development',
        ]);
        Device::factory()->online()->create([
            'device_code' => 'ESP32-KELAS-A',
            'device_name' => 'Reader Kelas A',
            'location' => 'Pintu Masuk Kelas A',
        ]);

        // Sample attendances (today)
        foreach ($users as $user) {
            Attendance::create([
                'user_id' => $user->id,
                'uid' => $user->rfid_uid,
                'status' => 'masuk',
                'schedule_id' => $shiftPagi->id,
                'device_id' => 'ESP32-WOKWI-SIM',
                'timestamp' => today()->setTime(8, fake()->numberBetween(1, 30)),
                'created_at' => now(),
            ]);
        }

        // Some users also checked out
        Attendance::create([
            'user_id' => $users[0]->id,
            'uid' => $users[0]->rfid_uid,
            'status' => 'pulang',
            'schedule_id' => $shiftPagi->id,
            'device_id' => 'ESP32-WOKWI-SIM',
            'timestamp' => today()->setTime(12, fake()->numberBetween(0, 15)),
            'created_at' => now(),
        ]);

        // Some historical data
        Attendance::factory()->count(10)->create();
    }
}
