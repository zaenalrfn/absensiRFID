<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_code' => 'ESP32-'.strtoupper(fake()->unique()->bothify('???-##')),
            'device_name' => 'Reader '.fake()->word(),
            'location' => fake()->randomElement(['Pintu Masuk Utama', 'Pintu Kelas A', 'Pintu Kelas B', 'Lobby']),
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'last_ip' => fake()->localIpv4(),
            'created_at' => now(),
        ];
    }

    /**
     * Indicate the device is currently online (seen within 10 minutes).
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 5)),
        ]);
    }

    /**
     * Indicate the device is offline (not seen recently).
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }
}
