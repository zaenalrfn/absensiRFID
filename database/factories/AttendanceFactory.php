<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        return [
            'user_id' => $user->id,
            'uid' => $user->rfid_uid ?? strtoupper(fake()->bothify('########')),
            'status' => fake()->randomElement(['masuk', 'pulang']),
            'schedule_id' => Schedule::inRandomOrder()->first()?->id,
            'device_id' => fake()->randomElement(['ESP32-WOKWI-SIM', 'ESP32-KELAS-A', 'ESP32-LOBBY']),
            'timestamp' => fake()->dateTimeBetween('-7 days', 'now'),
            'created_at' => now(),
        ];
    }

    /**
     * Indicate this is a check-in attendance.
     */
    public function masuk(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'masuk',
        ]);
    }

    /**
     * Indicate this is a check-out attendance.
     */
    public function pulang(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pulang',
        ]);
    }
}
