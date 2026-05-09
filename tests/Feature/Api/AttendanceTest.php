<?php

use App\Models\Device;
use App\Models\RfidCard;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->device = Device::factory()->create(['device_code' => 'TEST-DEVICE']);
    $this->schedule = Schedule::factory()->create([
        'name' => 'Shift Pagi',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);
});

test('it registers a new unknown card', function () {
    $response = $this->postJson('/api/presensi', [
        'uid' => 'NEW-CARD-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'registered']);

    $this->assertDatabaseHas('rfid_cards', [
        'uid' => 'NEW-CARD-UID',
        'user_id' => null,
    ]);
});

test('it prevents attendance for unassigned cards', function () {
    RfidCard::create(['uid' => 'UNASSIGNED-UID', 'label' => 'Unassigned Card']);

    $response = $this->postJson('/api/presensi', [
        'uid' => 'UNASSIGNED-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(200)
        ->assertJson(['status' => 'registered']);
});

test('it records "masuk" for first tap before end_time', function () {
    Carbon::setTestNow('2024-01-01 09:00:00');
    $user = User::factory()->create();
    RfidCard::create(['uid' => 'ASSIGNED-UID', 'user_id' => $user->id]);

    $response = $this->postJson('/api/presensi', [
        'uid' => 'ASSIGNED-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => ['status' => 'masuk'],
        ]);

    $this->assertDatabaseHas('attendances', [
        'user_id' => $user->id,
        'status' => 'masuk',
    ]);
});

test('it prevents double check-in before end_time', function () {
    Carbon::setTestNow('2024-01-01 09:00:00');
    $user = User::factory()->create();
    RfidCard::create(['uid' => 'ASSIGNED-UID', 'user_id' => $user->id]);

    // First tap
    $this->postJson('/api/presensi', ['uid' => 'ASSIGNED-UID', 'device_id' => 'TEST-DEVICE']);

    // Second tap
    Carbon::setTestNow('2024-01-01 10:00:00');
    $response = $this->postJson('/api/presensi', [
        'uid' => 'ASSIGNED-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Sudah absen masuk. Belum jam pulang.']);
});

test('it records "pulang" for tap after end_time', function () {
    $user = User::factory()->create();
    RfidCard::create(['uid' => 'ASSIGNED-UID', 'user_id' => $user->id]);

    // Check-in at 09:00
    Carbon::setTestNow('2024-01-01 09:00:00');
    $this->postJson('/api/presensi', ['uid' => 'ASSIGNED-UID', 'device_id' => 'TEST-DEVICE']);

    // Check-out at 18:00
    Carbon::setTestNow('2024-01-01 18:00:00');
    $response = $this->postJson('/api/presensi', [
        'uid' => 'ASSIGNED-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => ['status' => 'pulang'],
        ]);

    $this->assertDatabaseHas('attendances', [
        'user_id' => $user->id,
        'status' => 'pulang',
    ]);
});

test('it records "pulang" for first tap of day after end_time', function () {
    Carbon::setTestNow('2024-01-01 18:00:00');
    $user = User::factory()->create();
    RfidCard::create(['uid' => 'ASSIGNED-UID', 'user_id' => $user->id]);

    $response = $this->postJson('/api/presensi', [
        'uid' => 'ASSIGNED-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => ['status' => 'pulang'],
        ]);
});

test('it prevents tap after already "pulang"', function () {
    $user = User::factory()->create();
    RfidCard::create(['uid' => 'ASSIGNED-UID', 'user_id' => $user->id]);

    // First tap (masuk)
    Carbon::setTestNow('2024-01-01 09:00:00');
    $this->postJson('/api/presensi', ['uid' => 'ASSIGNED-UID', 'device_id' => 'TEST-DEVICE']);

    // Second tap (pulang)
    Carbon::setTestNow('2024-01-01 18:00:00');
    $this->postJson('/api/presensi', ['uid' => 'ASSIGNED-UID', 'device_id' => 'TEST-DEVICE']);

    // Third tap (error)
    Carbon::setTestNow('2024-01-01 19:00:00');
    $response = $this->postJson('/api/presensi', [
        'uid' => 'ASSIGNED-UID',
        'device_id' => 'TEST-DEVICE',
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Sudah absen pulang hari ini']);
});
