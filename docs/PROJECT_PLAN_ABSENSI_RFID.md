# PROJECT PLAN — Sistem Absensi RFID
> ESP32 + MFRC522 · Laravel 13 · Inertia.js · Vue 3 · Laravel Reverb

---

## Daftar Isi
1. [Gambaran Umum Proyek](#1-gambaran-umum-proyek)
2. [Teknologi Stack](#2-teknologi-stack)
3. [Arsitektur Sistem](#3-arsitektur-sistem)
4. [Struktur Folder Proyek](#4-struktur-folder-proyek)
5. [Skema Database](#5-skema-database)
6. [API Endpoint](#6-api-endpoint)
7. [Backend — Laravel 13](#7-backend--laravel-13)
8. [Frontend — Inertia Vue 3](#8-frontend--inertia-vue-3)
9. [Realtime — Laravel Reverb](#9-realtime--laravel-reverb)
10. [IoT — ESP32 + MFRC522](#10-iot--esp32--mfrc522)
11. [Wiring Diagram ESP32](#11-wiring-diagram-esp32)
12. [Alur Logika Lengkap](#12-alur-logika-lengkap)
13. [Rencana Pengerjaan (Milestones)](#13-rencana-pengerjaan-milestones)
14. [Testing Checklist](#14-testing-checklist)
15. [Catatan Penting & Konvensi Kode](#15-catatan-penting--konvensi-kode)

---

## 1. Gambaran Umum Proyek

**Nama Proyek:** Sistem Absensi RFID Berbasis IoT  
**Tujuan:** Membangun sistem presensi otomatis menggunakan kartu RFID yang terhubung ke server Laravel melalui ESP32, dengan dashboard realtime berbasis Vue.

**Fitur utama:**
- Scan kartu RFID → otomatis catat masuk/pulang berdasarkan jadwal
- Dashboard admin realtime (tanpa refresh) menggunakan laravel reverb
- Manajemen user, jadwal shift, dan perangkat IoT
- Offline mode: ESP32 tetap menyimpan data saat WiFi putus, lalu sync otomatis
- Indikator fisik (LED hijau/merah + buzzer) langsung di perangkat ESP32

---

## 2. Teknologi Stack

### Backend
| Komponen | Teknologi | Versi |
|---|---|---|
| Framework | Laravel | 13.x |
| PHP | PHP | >= 8.3 |
| Database | MySQL | 8.x |
| Laravel Reverb | terbaru |
| Auth API | laravel sanctum |
| ORM | Eloquent | bawaan Laravel |

### Frontend
| Komponen | Teknologi | Versi |
|---|---|---|
| SPA Bridge | Inertia.js | terbaru |
| Framework UI | Vue 3 (Composition API) | 3.x |
| Build Tool | Vite | terbaru |
| WebSocket Client | Laravel Echo | terbaru |
| Styling | Tailwind CSS | 3.x |

### IoT
| Komponen | Hardware / Library | Keterangan |
|---|---|---|
| Mikrokontroler | ESP32 Dev Board | WiFi built-in |
| Sensor RFID | MFRC522 | SPI interface |
| Indikator sukses | LED Hijau + Resistor 220Ω | GPIO output |
| Indikator gagal | LED Merah + Resistor 220Ω | GPIO output |
| Suara | Buzzer aktif 5V | GPIO output |
| Storage offline | EEPROM / Preferences.h | Simpan antrian UID |
| HTTP Client | HTTPClient.h | Bawaan ESP32 Arduino |
| JSON | ArduinoJson | Library tambahan |
| RFID Library | MFRC522.h | Library tambahan |

---

## 3. Arsitektur Sistem

```
[Kartu RFID]
     │  tap
     ▼
[ESP32 + MFRC522]
  ├─ Baca UID
  ├─ Cek koneksi WiFi
  ├─ [ONLINE]  → HTTP POST ke Laravel API
  └─ [OFFLINE] → simpan ke Preferences (queue)
                  sync otomatis saat WiFi kembali
     │
     │ HTTP POST /api/presensi
     │ Header: Authorization: Bearer {API_KEY}
     ▼
[Laravel 13 API]
  ├─ Middleware: ValidateApiKey
  ├─ Validasi UID → cari user di tabel users
  ├─ Tentukan status: masuk / pulang (by time)
  ├─ Deteksi jadwal aktif dari tabel schedules
  ├─ Simpan ke tabel attendances
  ├─ Update last_seen di tabel devices
  └─ Broadcast event AttendanceCreated
     │
     ▼
[MySQL Database]
  ├─ users
  ├─ devices
  ├─ schedules
  └─ attendances
     │
     ▼
[Laravel Reverb — WebSocket Server]
  └─ Channel: attendance-channel
     │
     ▼
[Dashboard Vue 3 — Inertia.js]
  ├─ Laravel Echo listener
  ├─ Tabel presensi update realtime
  └─ Stats card update otomatis
```

---

## 4. Struktur Folder Proyek

### Laravel (Backend + Frontend)

```
project-root/
├── app/
│   ├── Events/
│   │   └── AttendanceCreated.php        ← broadcast event
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AttendanceController.php
│   │   │   ├── UserController.php
│   │   │   ├── ScheduleController.php
│   │   │   └── DeviceController.php
│   │   └── Middleware/
│   │       └── ValidateApiKey.php       ← API Key auth untuk ESP32
│   └── Models/
│       ├── User.php
│       ├── Attendance.php
│       ├── Schedule.php
│       └── Device.php
│
├── database/
│   └── migrations/
│       ├── xxxx_create_users_table.php
│       ├── xxxx_create_devices_table.php
│       ├── xxxx_create_schedules_table.php
│       └── xxxx_create_attendances_table.php
│
├── routes/
│   ├── api.php                          ← route untuk ESP32
│   └── web.php                          ← route Inertia
│
└── resources/
    └── js/
        ├── Pages/
        │   ├── Dashboard.vue
        │   ├── Users/
        │   │   ├── Index.vue
        │   │   └── Form.vue
        │   ├── Schedules/
        │   │   ├── Index.vue
        │   │   └── Form.vue
        │   └── Attendances/
        │       └── Index.vue
        └── Components/
            ├── TableAttendance.vue
            ├── StatsCard.vue
            ├── DeviceStatusBadge.vue
            └── Layouts/
                └── AppLayout.vue
```

### ESP32 (Arduino / PlatformIO)

```
esp32-rfid/
├── src/
│   └── main.cpp         ← semua logika utama
├── include/
│   └── config.h         ← WiFi SSID, password, API URL, API Key
├── lib/                 ← library lokal jika ada
└── platformio.ini       ← konfigurasi PlatformIO
```

> Jika pakai Arduino IDE biasa, cukup satu file `rfid_absensi.ino` dengan `config.h` terpisah.

---

## 5. Skema Database

### Tabel: `users`

```sql
CREATE TABLE users (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    rfid_uid    VARCHAR(50) UNIQUE NOT NULL,        -- UID dari kartu RFID
    role        ENUM('admin', 'user') DEFAULT 'user',
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);
```

**Catatan:**
- `rfid_uid` harus unik. Satu kartu = satu user.
- `role` admin bisa akses semua halaman, role user hanya lihat presensi sendiri (opsional).

---

### Tabel: `devices`

```sql
CREATE TABLE devices (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code  VARCHAR(100) UNIQUE NOT NULL,   -- kode unik ESP32, hardcode di firmware
    device_name  VARCHAR(255) NOT NULL,
    location     VARCHAR(255) NULL,              -- misal "Pintu Masuk Kelas A"
    last_seen_at TIMESTAMP NULL,                 -- update setiap kali ada presensi
    last_ip      VARCHAR(50) NULL,
    created_at   TIMESTAMP NULL
);
```

**Catatan:**
- Devices tidak perlu `updated_at`, cukup `last_seen_at`.
- `device_code` di-hardcode di firmware ESP32, didaftarkan manual oleh admin.

---

### Tabel: `schedules`

```sql
CREATE TABLE schedules (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,    -- contoh: "Shift Pagi"
    start_time  TIME NOT NULL,            -- contoh: 08:00:00
    end_time    TIME NOT NULL,            -- contoh: 12:00:00
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
);
```

**Contoh data awal (seeder):**

| name | start_time | end_time |
|---|---|---|
| Shift Pagi | 08:00:00 | 12:00:00 |
| Shift Siang | 13:00:00 | 17:00:00 |

---

### Tabel: `attendances`

```sql
CREATE TABLE attendances (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    uid         VARCHAR(50) NOT NULL,              -- UID yang di-scan (raw)
    status      ENUM('masuk', 'pulang') NOT NULL,
    schedule_id BIGINT UNSIGNED NULL,              -- null jika scan di luar jadwal
    device_id   VARCHAR(100) NOT NULL,             -- device_code ESP32 pengirim
    timestamp   DATETIME NOT NULL,                 -- waktu scan di server
    created_at  TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id)
);
```

**Catatan:**
- `uid` disimpan raw sebagai bukti fisik scan (bisa berbeda dari rfid_uid user jika kartu rusak/diganti).
- `device_id` menggunakan string `device_code`, bukan FK, agar tidak error jika device belum terdaftar.

---

## 6. API Endpoint

### Base URL
```
https://yourdomain.com/api
```

### Daftar Endpoint

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/presensi` | API Key | Terima data scan dari ESP32 |
| GET | `/ping` | API Key | Cek koneksi dari ESP32 |

### Header wajib untuk semua request dari ESP32
```
Authorization: Bearer {API_KEY}
Content-Type: application/json
```

---

### POST `/api/presensi`

**Request Body:**
```json
{
    "uid": "A1B2C3D4",
    "device_id": "ESP32-KELAS-A"
}
```

**Response — Sukses (200):**
```json
{
    "status": "success",
    "message": "Presensi berhasil",
    "data": {
        "name": "Budi Santoso",
        "status": "masuk",
        "schedule": "Shift Pagi",
        "timestamp": "2025-01-15 08:23:45"
    }
}
```

**Response — UID Tidak Terdaftar (404):**
```json
{
    "status": "error",
    "message": "UID tidak terdaftar"
}
```

**Response — Unauthorized (401):**
```json
{
    "status": "error",
    "message": "Unauthorized"
}
```

---

### GET `/api/ping`

Digunakan ESP32 untuk cek apakah server bisa dijangkau sebelum kirim data.

**Response (200):**
```json
{
    "status": "ok",
    "timestamp": "2025-01-15 08:23:00"
}
```

---

## 7. Backend — Laravel 13

### 7.1 Middleware: `ValidateApiKey`

**File:** `app/Http/Middleware/ValidateApiKey.php`

```php
public function handle(Request $request, Closure $next): Response
{
    $key = $request->header('Authorization');

    if ($key !== 'Bearer ' . config('app.rfid_api_key')) {
        return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

- API Key disimpan di `.env` sebagai `RFID_API_KEY=rahasia123`
- Daftar middleware di `bootstrap/app.php` atau `Kernel.php`

---

### 7.2 Controller: `AttendanceController`

**File:** `app/Http/Controllers/AttendanceController.php`

**Method `store()` — logika lengkap:**

```
1. Validasi request: uid (required), device_id (required)
2. Cari user berdasarkan rfid_uid → 404 jika tidak ada
3. Tentukan status (masuk/pulang):
   - Cek apakah sudah ada attendance hari ini
   - Jika belum ada → status = 'masuk'
   - Jika sudah ada status 'masuk' → status = 'pulang'
   - Catatan: bisa juga pakai logika jam (sebelum tengah hari = masuk)
4. Deteksi jadwal aktif:
   - Query schedules WHERE start_time <= NOW() AND end_time >= NOW()
   - Jika tidak ada jadwal aktif, schedule_id = null
5. Simpan attendance baru ke database
6. Update devices: set last_seen_at = now(), last_ip = request IP
7. Broadcast event AttendanceCreated
8. Return JSON response sukses
```

**Logika penentuan status yang lebih akurat (recommended):**

```
- Cek attendance terakhir user di hari ini
- Jika tidak ada → 'masuk'
- Jika ada dan status terakhir = 'masuk' → 'pulang'
- Jika ada dan status terakhir = 'pulang' → 'masuk' (masuk lagi)
```

Ini lebih robust dibanding cek jam karena user bisa pulang lebih awal.

---

### 7.3 Models

**User.php** — tambahkan relasi:
```php
public function attendances(): HasMany
{
    return $this->hasMany(Attendance::class);
}
```

**Attendance.php** — fillable + relasi:
```php
protected $fillable = ['user_id', 'uid', 'status', 'schedule_id', 'device_id', 'timestamp'];

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function schedule(): BelongsTo
{
    return $this->belongsTo(Schedule::class);
}
```

---

### 7.4 Routes

**`routes/api.php`:**
```php
Route::middleware('validate.api.key')->group(function () {
    Route::post('/presensi', [AttendanceController::class, 'store']);
    Route::get('/ping', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));
});
```

**`routes/web.php`** (Inertia):
```php
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('/users', UserController::class);
    Route::resource('/schedules', ScheduleController::class);
    Route::resource('/attendances', AttendanceController::class)->only(['index']);
    Route::resource('/devices', DeviceController::class)->only(['index']);
});
```

---

## 8. Frontend — Inertia Vue 3

### 8.1 Halaman yang Dibutuhkan

| Halaman | Route | Fitur |
|---|---|---|
| Dashboard | `/dashboard` | Statistik hari ini, log realtime, device aktif |
| Users Index | `/users` | Tabel user + tombol tambah/edit/hapus |
| Users Form | `/users/create` dan `/users/{id}/edit` | Form nama, role, input UID RFID |
| Schedules Index | `/schedules` | Tabel jadwal shift |
| Schedules Form | `/schedules/create` dst | Form nama shift, jam mulai, jam selesai |
| Attendances | `/attendances` | Riwayat presensi, filter tanggal & user |

---

### 8.2 Dashboard.vue — Struktur Komponen

```
Dashboard.vue
├── StatsCard (Total presensi hari ini)
├── StatsCard (Sudah masuk)
├── StatsCard (Sudah pulang)
├── StatsCard (Device aktif)
└── TableAttendance (log realtime, data terbaru di atas)
    └── Echo listener (update otomatis via WebSocket)
```

**Data yang dikirim dari controller (Inertia props):**
```php
return Inertia::render('Dashboard', [
    'stats' => [
        'total_today'  => Attendance::whereDate('timestamp', today())->count(),
        'masuk'        => Attendance::whereDate('timestamp', today())->where('status', 'masuk')->count(),
        'pulang'       => Attendance::whereDate('timestamp', today())->where('status', 'pulang')->count(),
    ],
    'recent_attendances' => Attendance::with(['user', 'schedule'])
                                ->latest('timestamp')
                                ->take(20)
                                ->get(),
    'active_devices' => Device::whereNotNull('last_seen_at')
                               ->where('last_seen_at', '>=', now()->subMinutes(10))
                               ->count(),
]);
```

---

### 8.3 Users Form — Input UID RFID

Pada form tambah/edit user, field `rfid_uid` diisi manual (ketik UID dari kartu yang sudah dibaca via serial monitor ESP32, atau tambahkan fitur scan langsung via perangkat tambahan di browser — opsional untuk fase lanjut).

---

## 9. Realtime — Laravel Reverb

### 9.1 Event: `AttendanceCreated`

**File:** `app/Events/AttendanceCreated.php`

```php
class AttendanceCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Attendance $attendance)
    {
        $this->attendance->load(['user', 'schedule']); // eager load relasi
    }

    public function broadcastOn(): array
    {
        return [new Channel('attendance-channel')];
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->attendance->id,
            'user_name' => $this->attendance->user->name,
            'status'    => $this->attendance->status,
            'schedule'  => $this->attendance->schedule?->name ?? 'Di luar jadwal',
            'device_id' => $this->attendance->device_id,
            'timestamp' => $this->attendance->timestamp,
        ];
    }
}
```

---

### 9.2 Vue Echo Listener

**Di `Dashboard.vue` (dalam `onMounted`):**

```javascript
import { onMounted, onUnmounted } from 'vue'

let channel = null

onMounted(() => {
    channel = window.Echo.channel('attendance-channel')
        .listen('AttendanceCreated', (e) => {
            // tambahkan ke paling atas array
            recentAttendances.value.unshift(e)
            // update stats
            stats.value.total_today++
            if (e.status === 'masuk') stats.value.masuk++
            else stats.value.pulang++
        })
})

onUnmounted(() => {
    channel?.stopListening('AttendanceCreated')
    window.Echo.leave('attendance-channel')
})
```

---

### 9.3 Konfigurasi `.env` untuk Reverb

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=my-app
REVERB_APP_KEY=my-key
REVERB_APP_SECRET=my-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 10. IoT — ESP32 + MFRC522

### 10.1 File `config.h`

```cpp
#ifndef CONFIG_H
#define CONFIG_H

// WiFi
const char* WIFI_SSID     = "NamaWiFimu";
const char* WIFI_PASSWORD = "PasswordWiFimu";

// Server
const char* SERVER_URL    = "http://192.168.1.100/api/presensi";
const char* PING_URL      = "http://192.168.1.100/api/ping";
const char* API_KEY       = "Bearer rahasia123";

// Device Identity
const char* DEVICE_CODE   = "ESP32-KELAS-A";

// Pin definitions
#define PIN_LED_GREEN  26
#define PIN_LED_RED    27
#define PIN_BUZZER     25
#define PIN_RFID_SS    5
#define PIN_RFID_RST   22

// Timing
#define DELAY_AFTER_SCAN    2000   // ms, jeda setelah scan agar tidak double
#define WIFI_TIMEOUT        10000  // ms, batas waktu konek WiFi
#define HTTP_TIMEOUT        5000   // ms, batas waktu tunggu response server

#endif
```

---

### 10.2 Struktur Logika `main.cpp` / `.ino`

```
setup():
  1. Inisialisasi Serial (debug)
  2. Inisialisasi SPI dan MFRC522
  3. Set pinMode LED dan Buzzer
  4. Sambungkan WiFi (dengan timeout)
  5. Inisialisasi Preferences (penyimpanan offline)

loop():
  1. Cek apakah ada kartu RFID baru terdeteksi → skip jika tidak ada
  2. Baca UID dari kartu, konversi ke string hex uppercase
  3. Tampilkan UID ke Serial Monitor
  4. Cek status WiFi:
     a. Jika ONLINE:
        - Coba kirim HTTP POST ke /api/presensi
        - Jika response 200 → successIndicator()
        - Jika response 404 → unknownCardIndicator()
        - Jika response lain atau timeout → errorIndicator()
        - Panggil syncOfflineQueue() untuk kirim data yang tersimpan
     b. Jika OFFLINE:
        - Simpan UID + timestamp ke Preferences (queue)
        - offlineIndicator()
  5. Hentikan komunikasi RFID
  6. Delay 2000ms (anti double scan)
```

---

### 10.3 Format Data Offline Queue

Disimpan di `Preferences.h` dengan namespace `"rfid_queue"`:

```
Key: "count"    → int, jumlah antrian
Key: "uid_0"    → string, UID pertama
Key: "uid_1"    → string, UID kedua
... dst
```

Saat sync, iterasi dari `uid_0` sampai `uid_{count-1}`, kirim satu per satu, hapus setelah berhasil terkirim.

> **Batas:** Preferences ESP32 terbatas ~4KB. Cukup untuk ratusan UID. Untuk skala besar, pertimbangkan SPIFFS/LittleFS.

---

### 10.4 Fungsi Indikator

```
successIndicator():
  - LED Hijau ON 1 detik
  - Buzzer ON 100ms
  - LED Hijau OFF

unknownCardIndicator():
  - LED Merah ON
  - Buzzer 3x (ON 100ms, OFF 100ms) × 3
  - LED Merah OFF

errorIndicator():
  - LED Merah ON
  - Buzzer ON 500ms (panjang)
  - LED Merah OFF

offlineIndicator():
  - LED Merah kedip 3x cepat
  - Buzzer ON 300ms
```

---

## 11. Wiring Diagram ESP32

### Koneksi MFRC522 ke ESP32

| MFRC522 | ESP32 |
|---|---|
| SDA (SS) | GPIO 5 |
| SCK | GPIO 18 |
| MOSI | GPIO 23 |
| MISO | GPIO 19 |
| RST | GPIO 22 |
| GND | GND |
| 3.3V | 3.3V |

> **PENTING:** MFRC522 beroperasi di 3.3V, BUKAN 5V. Salah tegangan = sensor rusak.

### Koneksi LED dan Buzzer ke ESP32

| Komponen | ESP32 | Keterangan |
|---|---|---|
| LED Hijau (+) | GPIO 26 | Seri dengan resistor 220Ω ke GND |
| LED Merah (+) | GPIO 27 | Seri dengan resistor 220Ω ke GND |
| Buzzer (+) | GPIO 25 | Langsung ke GPIO (buzzer aktif 3.3V) |
| Semua (-) | GND | Ground ESP32 |

---

## 12. Alur Logika Lengkap

### Skenario 1: Presensi Normal (Online)

```
1. User tap kartu ke MFRC522
2. ESP32 baca UID: "A1B2C3D4"
3. WiFi tersambung ✓
4. ESP32 POST ke /api/presensi dengan {"uid":"A1B2C3D4","device_id":"ESP32-KELAS-A"}
5. Laravel terima request
6. Middleware cek API Key → valid ✓
7. Cari User dengan rfid_uid = "A1B2C3D4" → ditemukan: "Budi Santoso"
8. Cek attendance terakhir hari ini → belum ada → status = 'masuk'
9. Cek jadwal aktif → Shift Pagi (08:00-12:00) aktif ✓
10. Simpan attendance baru
11. Update devices.last_seen_at
12. Broadcast AttendanceCreated ke attendance-channel
13. Return 200 {"status":"success","message":"Presensi berhasil"}
14. ESP32 terima 200 → successIndicator() → LED hijau + buzzer 1x
15. Dashboard Vue terima broadcast → tambah baris baru ke tabel realtime
```

---

### Skenario 2: UID Tidak Terdaftar

```
1-4. (sama seperti skenario 1)
5. Laravel terima request
6. Middleware cek API Key → valid ✓
7. Cari User dengan rfid_uid = "XXXXXXXX" → tidak ditemukan
8. Return 404 {"status":"error","message":"UID tidak terdaftar"}
9. ESP32 terima 404 → unknownCardIndicator() → LED merah + buzzer 3x
```

---

### Skenario 3: ESP32 Offline

```
1. User tap kartu
2. ESP32 baca UID: "A1B2C3D4"
3. WiFi tidak tersambung ✗
4. ESP32 simpan ke Preferences: uid_0 = "A1B2C3D4", count = 1
5. offlineIndicator() → LED merah kedip + buzzer panjang
--- (waktu berlalu, WiFi kembali) ---
6. loop() berikutnya: WiFi tersambung ✓
7. syncOfflineQueue() dipanggil
8. Ambil uid_0 dari Preferences, POST ke server
9. Server proses dan return 200
10. Hapus uid_0 dari Preferences, kurangi count
11. Lanjut hingga queue kosong
```

---

## 13. Rencana Pengerjaan (Milestones)

### Fase 1 — Setup & Database (Estimasi: 1-2 hari)
- [ ] Buat proyek Laravel 13 baru
- [ ] Install Inertia.js + Vue 3 + Tailwind CSS
- [ ] Buat semua migration (users, devices, schedules, attendances)
- [ ] Buat semua Model dengan relasi
- [ ] Buat seeder untuk data dummy (1 admin, 3 user, 2 jadwal, 5 device)
- [ ] Setup auth Laravel (bisa pakai Laravel Breeze)

### Fase 2 — Backend API untuk ESP32 (Estimasi: 1-2 hari)
- [ ] Buat middleware `ValidateApiKey`
- [ ] Daftarkan API Key di `.env`
- [ ] Buat `AttendanceController@store` dengan logika lengkap
- [ ] Buat route `/api/presensi` dan `/api/ping`
- [ ] Test API dengan Postman/Insomnia

### Fase 3 — Backend Web (Estimasi: 2-3 hari)
- [ ] Buat `DashboardController`
- [ ] Buat `UserController` (CRUD)
- [ ] Buat `ScheduleController` (CRUD)
- [ ] Buat `AttendanceController@index` dengan filter tanggal & user
- [ ] Buat `DeviceController@index`
- [ ] Setup Inertia route

### Fase 4 — Frontend Vue (Estimasi: 3-4 hari)
- [ ] Buat `AppLayout.vue` (sidebar + header)
- [ ] Buat `Dashboard.vue` (stats + tabel)
- [ ] Buat `Users/Index.vue` dan `Users/Form.vue`
- [ ] Buat `Schedules/Index.vue` dan `Schedules/Form.vue`
- [ ] Buat `Attendances/Index.vue` dengan filter
- [ ] Buat komponen reusable: `StatsCard.vue`, `TableAttendance.vue`

### Fase 5 — Realtime Reverb (Estimasi: 1 hari)
- [ ] Install dan konfigurasi Laravel Reverb
- [ ] Buat event `AttendanceCreated`
- [ ] Setup Laravel Echo di `bootstrap.js`
- [ ] Tambahkan listener di `Dashboard.vue`
- [ ] Test broadcast end-to-end

### Fase 6 — ESP32 Firmware (Estimasi: 2-3 hari)
- [ ] Setup PlatformIO atau Arduino IDE
- [ ] Install library: MFRC522, ArduinoJson, HTTPClient
- [ ] Tulis `config.h`
- [ ] Implementasi logika scan RFID + baca UID
- [ ] Implementasi HTTP POST ke Laravel
- [ ] Implementasi indikator LED + Buzzer
- [ ] Implementasi offline queue dengan Preferences
- [ ] Implementasi syncOfflineQueue()
- [ ] Test end-to-end dengan server lokal

### Fase 7 — Integrasi & Testing (Estimasi: 1-2 hari)
- [ ] Test semua skenario (sukses, gagal, offline)
- [ ] Test realtime dashboard
- [ ] Test filter dan export di halaman Attendances
- [ ] Fix bug

---

## 14. Testing Checklist

### API Testing (Postman)
- [ ] POST `/api/presensi` tanpa Authorization header → harus 401
- [ ] POST `/api/presensi` dengan API Key salah → harus 401
- [ ] POST `/api/presensi` dengan UID tidak terdaftar → harus 404
- [ ] POST `/api/presensi` dengan UID valid → harus 200 + data user
- [ ] POST `/api/presensi` 2x oleh user yang sama hari ini → status harus beda (masuk → pulang)
- [ ] GET `/api/ping` → harus 200

### ESP32 Testing
- [ ] Tap kartu terdaftar saat online → LED hijau + buzzer 1x
- [ ] Tap kartu tidak terdaftar saat online → LED merah + buzzer 3x
- [ ] Tap kartu saat WiFi mati → data tersimpan di queue, LED merah kedip
- [ ] Nyalakan WiFi kembali → data queue terkirim otomatis
- [ ] Tap kartu 2x dalam 2 detik → hanya 1 presensi tercatat (debounce delay)

### Frontend Testing
- [ ] Dashboard stats menampilkan angka yang benar
- [ ] Saat presensi baru masuk → baris baru muncul di tabel tanpa refresh
- [ ] Filter attendance by tanggal berfungsi
- [ ] CRUD user (tambah, edit, hapus) berfungsi
- [ ] CRUD jadwal berfungsi
- [ ] Halaman devices menampilkan last_seen_at yang akurat

---

## 15. Catatan Penting & Konvensi Kode

### Konvensi Laravel
- Gunakan **Resource Controller** untuk semua CRUD web
- Semua response API menggunakan format: `{"status": "...", "message": "...", "data": {...}}`
- API Key disimpan di `.env`, **jangan hardcode di kode**
- Gunakan **Form Request** untuk validasi (opsional tapi recommended)
- Gunakan **API Resource** untuk transform data sebelum dikirim ke frontend

### Konvensi Vue
- Gunakan **Composition API** (`<script setup>`) untuk semua komponen
- Semua state reaktif menggunakan `ref()` atau `reactive()`
- Selalu cleanup Echo listener di `onUnmounted()`
- Gunakan Inertia `useForm()` untuk semua form CRUD

### Konvensi ESP32
- Semua konfigurasi di `config.h`, **jangan hardcode di `main.cpp`**
- Gunakan `Serial.println()` untuk debug, **hapus/disable sebelum production**
- Selalu set timeout pada HTTP request (`http.setTimeout(HTTP_TIMEOUT)`)
- Gunakan `MFRC522::PICC_HaltA()` setelah setiap scan untuk reset kartu

### Keamanan
- API Key minimal 32 karakter acak
- Gunakan HTTPS di production (bukan HTTP)
- Tambahkan rate limiting di route API (pakai `throttle` middleware Laravel)
- Jangan simpan API Key di kode yang di-commit ke GitHub → gunakan `.gitignore` untuk `config.h`

### File `.env` yang perlu ditambahkan
```env
RFID_API_KEY=isi_dengan_string_acak_minimal_32_karakter
```

---

*Dokumen ini adalah living document. Update sesuai perkembangan implementasi.*
