/\*

- ================================================================
- SISTEM ABSENSI RFID
- Hardware : ESP32 DevKit C v4 + MFRC522 + LED Hijau + LED Merah + Buzzer
- Server : Laravel 13 API + Laravel Reverb (WebSocket)
-
- Cara pakai:
-   - Uncomment #define WOKWI_SIM untuk mode simulasi Wokwi
-   - Comment #define WOKWI_SIM untuk hardware nyata (MFRC522 fisik)
- ================================================================
  \*/

#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <SPI.h>
#include <MFRC522.h>

// ================================================================
// TOGGLE MODE: uncomment baris di bawah untuk mode simulasi Wokwi
// Comment kembali saat deploy ke hardware nyata
// ================================================================
// #define WOKWI_SIM

// ================================================================
// KONFIGURASI — Edit bagian ini sesuai environment kamu
// ================================================================

// --- WiFi ---
// Wokwi : gunakan "Wokwi-GUEST", password kosong
// Real HW : ganti dengan SSID dan password WiFi jaringanmu
const char* WIFI_SSID = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";

// --- Laravel Server ---
// Lokal via ngrok: jalankan "ngrok http 8000" → copy URL-nya ke sini
// Contoh: "https://abcd1234.ngrok-free.app/api/presensi"
const char* SERVER_URL = "https://absensirfid-production.up.railway.app/api/presensi";
const char* PING_URL = "https://absensirfid-production.up.railway.app/api/ping";

// --- API Key ---
// Harus sama persis dengan RFID_API_KEY di file .env Laravel
const char\* API_KEY = "Bearer rfid-secret-key-change-me-in-production-32chars";

// --- Device Identity ---
// Hardcode per perangkat fisik, didaftarkan manual oleh admin di web
const char\* DEVICE_CODE = "ESP32-WOKWI-SIM";

// ================================================================
// PIN DEFINITIONS
// ================================================================
#define PIN_RFID_SS 5 // SDA / SS chip select MFRC522
#define PIN_RFID_RST 22 // RST reset MFRC522
// SCK=18, MOSI=23, MISO=19 → pin SPI default ESP32, tidak perlu didefinisikan ulang

#define PIN_LED_GREEN 26 // GPIO untuk LED hijau (presensi sukses)
#define PIN_LED_RED 27 // GPIO untuk LED merah (gagal/tidak dikenal/offline)
#define PIN_BUZZER 25 // GPIO untuk buzzer (output PWM)

// ================================================================
// BUZZER CONFIG
// Menggunakan tone() — kompatibel ESP32 Arduino core v2.x DAN v3.x
// Frekuensi 2000–4000 Hz terdengar jelas tapi tidak nyaring
// tone() menghasilkan gelombang 50% duty cycle (volume standar)
// ================================================================
#define BUZZER_FREQ_HZ 2800 // Frekuensi nada (Hz) — makin tinggi makin nyaring
// Range nyaman: 2000–3500 Hz
// Turunkan ke 1500 Hz jika masih terlalu nyaring

// ================================================================
// TIMING
// ================================================================
#define DELAY_AFTER_SCAN 2000 // ms — jeda anti double-scan setelah tap kartu
#define WIFI_TIMEOUT 10000 // ms — batas waktu tunggu koneksi WiFi
#define HTTP_TIMEOUT 8000 // ms — batas waktu tunggu response server
#define WIFI_RECHECK_MS 30000 // ms — interval cek ulang koneksi WiFi di loop

// ================================================================
// KARTU SIMULASI (hanya aktif saat WOKWI_SIM di-define)
// UID ini harus terdaftar di tabel users.rfid_uid di database Laravel
// ================================================================
#ifdef WOKWI_SIM
struct SimCard {
const char* uid;
const char* label;
};

const SimCard SIM_CARDS[] = {
{ "A1B2C3D4", "Budi Santoso (terdaftar)" },
{ "E5F6A7B8", "Sari Dewi (terdaftar)" },
{ "DEADBEEF", "Kartu Unknown (tidak terdaftar)" },
};
const int SIM_CARD_COUNT = 3;
int simCardIndex = -1; // -1 = belum ada kartu yang di-tap
#endif

// ================================================================
// GLOBAL OBJECTS & STATE
// ================================================================
MFRC522 mfrc522(PIN_RFID_SS, PIN_RFID_RST); // objek driver RFID
Preferences prefs; // penyimpanan flash (offline queue)

bool isOnline = false; // status koneksi WiFi saat ini
unsigned long lastScanTime = 0; // timestamp scan terakhir (anti double-scan)
unsigned long lastWiFiCheck = 0; // timestamp cek WiFi terakhir

// ================================================================
// PROTOTYPES — deklarasi fungsi agar bisa dipanggil sebelum definisi
// ================================================================
void connectWiFi();
String readUID();
bool sendPresensi(const String& uid);
bool pingServer();
void saveToQueue(const String& uid);
void syncOfflineQueue();
int getQueueCount();
void successIndicator();
void unknownCardIndicator();
void errorIndicator();
void offlineIndicator();
void buzzTone(int durationMs, int count = 1, int gapMs = 100);
void buzzOff();
void printStatus();
void printBanner();
void printSeparator();

// ================================================================
// SETUP — dijalankan sekali saat ESP32 pertama kali menyala
// ================================================================
void setup() {
Serial.begin(115200);
delay(600);

// --- Init pin LED sebagai output, mulai dengan kondisi mati ---
pinMode(PIN_LED_GREEN, OUTPUT);
pinMode(PIN_LED_RED, OUTPUT);
digitalWrite(PIN_LED_GREEN, LOW);
digitalWrite(PIN_LED_RED, LOW);

// --- Init pin buzzer ---
// Gunakan tone()/noTone() — kompatibel ESP32 core v2.x dan v3.x
// Tidak perlu ledcSetup/ledcAttachPin yang berbeda antar versi core
pinMode(PIN_BUZZER, OUTPUT);
noTone(PIN_BUZZER); // pastikan buzzer mati saat startup

// --- Init SPI untuk MFRC522 ---
// SCK=18, MISO=19, MOSI=23 adalah pin SPI hardware ESP32
SPI.begin(18, 19, 23, PIN_RFID_SS);
mfrc522.PCD_Init(); // inisialisasi chip MFRC522
delay(100);

// --- Init Preferences (penyimpanan non-volatile di flash ESP32) ---
// Digunakan untuk menyimpan antrian UID saat WiFi mati (offline mode)
prefs.begin("rfid_queue", false);

// --- Tampilkan info startup ---
printBanner();
connectWiFi();

#ifdef WOKWI_SIM
// Mode simulasi: kartu diinput via Serial Monitor
Serial.println(F(" [MODE SIMULASI WOKWI]"));
Serial.println(F(" Ketik angka + Enter untuk simulasi kartu:"));
Serial.println(F(" [0] Budi Santoso (UID: A1B2C3D4)"));
Serial.println(F(" [1] Sari Dewi (UID: E5F6A7B8)"));
Serial.println(F(" [2] Kartu Unknown (UID: DEADBEEF)"));
Serial.println(F(" [s] Status [c] Clear queue [p] Ping server"));
#else
// Mode hardware nyata: baca versi firmware MFRC522 sebagai health check
Serial.println(F(" [MODE HARDWARE MFRC522]"));
Serial.println(F(" Tempelkan kartu RFID ke reader..."));
byte v = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
if (v == 0x00 || v == 0xFF) {
// 0x00 atau 0xFF artinya SPI tidak terhubung / wiring salah
Serial.println(F("[ERROR] MFRC522 tidak terdeteksi! Cek wiring SPI."));
} else {
Serial.print(F("[RFID] MFRC522 firmware: 0x"));
Serial.println(v, HEX); // versi normal: 0x91 atau 0x92
}
#endif

printSeparator();
}

// ================================================================
// LOOP — dijalankan berulang terus-menerus setelah setup()
// ================================================================
void loop() {

// --- Cek koneksi WiFi secara berkala & sync queue jika ada ---
// Tidak setiap loop (boros waktu), tapi setiap WIFI_RECHECK_MS
if (millis() - lastWiFiCheck > WIFI_RECHECK_MS) {
lastWiFiCheck = millis();
if (WiFi.status() != WL_CONNECTED) {
connectWiFi(); // coba reconnect jika putus
}
// Jika WiFi kembali online dan ada data antrian, kirim sekarang
if (WiFi.status() == WL_CONNECTED && getQueueCount() > 0) {
syncOfflineQueue();
}
}

#ifdef WOKWI_SIM
// --- Mode Simulasi: baca perintah dari Serial Monitor ---
if (Serial.available()) {
String input = Serial.readStringUntil('\n');
input.trim();

    if      (input == "0" || input == "1" || input == "2") { simCardIndex = input.toInt(); }
    else if (input == "s") { printStatus(); return; }
    else if (input == "c") { prefs.putInt("count", 0); Serial.println(F("[Queue] Dikosongkan.")); return; }
    else if (input == "p") { pingServer(); return; }
    else { Serial.println(F("[!] Ketik: 0/1/2=kartu  s=status  c=clear  p=ping")); return; }

}

// Tidak ada kartu yang dipilih → skip
if (simCardIndex < 0) { delay(50); return; }
#endif

// --- Anti double-scan: abaikan jika scan terlalu cepat ---
if (millis() - lastScanTime < DELAY_AFTER_SCAN) {
#ifdef WOKWI_SIM
simCardIndex = -1;
#endif
return;
}

// --- Baca UID kartu ---
String uid = readUID();
if (uid.isEmpty()) {
// Tidak ada kartu terdeteksi (hardware) atau index tidak valid (simulasi)
#ifdef WOKWI_SIM
simCardIndex = -1;
#endif
delay(50);
return;
}

// Catat waktu scan terakhir (untuk anti double-scan berikutnya)
lastScanTime = millis();

printSeparator();
Serial.print(F("[RFID] Kartu terdeteksi — UID: "));
Serial.println(uid);

// --- Tentukan status koneksi ---
isOnline = (WiFi.status() == WL_CONNECTED);
Serial.print(F("[WiFi] Status: "));
Serial.println(isOnline ? "ONLINE" : "OFFLINE");

if (isOnline) {
// Kirim langsung ke server
bool ok = sendPresensi(uid);
// Jika berhasil kirim, coba juga kirim data antrian offline yang mungkin tersisa
if (ok) syncOfflineQueue();
} else {
// WiFi mati → simpan ke flash, kasih indikator offline
Serial.println(F("[Queue] WiFi mati, UID disimpan ke offline queue."));
saveToQueue(uid);
offlineIndicator();
connectWiFi(); // langsung coba reconnect
}

#ifdef WOKWI_SIM
simCardIndex = -1; // reset setelah diproses
#endif
Serial.println(F("[INFO] Siap menerima kartu berikutnya..."));
printSeparator();
}

// ================================================================
// readUID() — Baca UID kartu RFID
// Mode Wokwi : ambil dari array SIM_CARDS sesuai index yang dipilih
// Mode Hardware: baca dari chip MFRC522 via SPI
// Return : String UID uppercase hex (contoh: "A1B2C3D4")
// atau string kosong "" jika tidak ada kartu
// ================================================================
String readUID() {
#ifdef WOKWI_SIM
if (simCardIndex < 0 || simCardIndex >= SIM_CARD_COUNT) return "";
Serial.print(F("[SIM] Kartu: "));
Serial.println(SIM_CARDS[simCardIndex].label);
return String(SIM_CARDS[simCardIndex].uid);
#else
// PICC_IsNewCardPresent() → cek apakah ada kartu baru di depan reader
// PICC_ReadCardSerial() → baca serial number (UID) kartu tersebut
if (!mfrc522.PICC_IsNewCardPresent()) return "";
if (!mfrc522.PICC_ReadCardSerial()) return "";

// Konversi array byte UID ke string hex uppercase (contoh: {0xA1,0xB2} → "A1B2")
String uid = "";
for (byte i = 0; i < mfrc522.uid.size; i++) {
if (mfrc522.uid.uidByte[i] < 0x10) uid += "0"; // padding leading zero
uid += String(mfrc522.uid.uidByte[i], HEX);
}
uid.toUpperCase();

// Hentikan komunikasi dengan kartu agar tidak terbaca terus-menerus
mfrc522.PICC_HaltA();
mfrc522.PCD_StopCrypto1();
return uid;
#endif
}

// ================================================================
// connectWiFi() — Hubungkan ESP32 ke jaringan WiFi
// Menunggu hingga terhubung atau timeout (WIFI_TIMEOUT ms)
// Tidak melakukan apa-apa jika sudah terhubung
// ================================================================
void connectWiFi() {
if (WiFi.status() == WL_CONNECTED) return; // sudah online, skip

Serial.print(F("[WiFi] Menghubungkan ke: "));
Serial.println(WIFI_SSID);

WiFi.mode(WIFI_STA); // mode station (client), bukan access point
WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

unsigned long t = millis();
while (WiFi.status() != WL_CONNECTED && millis() - t < WIFI_TIMEOUT) {
delay(400);
Serial.print(".");
}
Serial.println();

if (WiFi.status() == WL_CONNECTED) {
Serial.print(F("[WiFi] Terhubung! IP: "));
Serial.println(WiFi.localIP());
isOnline = true;
} else {
Serial.println(F("[WiFi] Gagal terhubung. Mode offline aktif."));
isOnline = false;
}
}

// ================================================================
// pingServer() — Cek apakah server Laravel bisa dijangkau
// Memanggil GET /api/ping, bukan mengirim data presensi
// Berguna untuk troubleshooting koneksi sebelum deployment
// Return: true jika server merespons HTTP 200
// ================================================================
bool pingServer() {
if (WiFi.status() != WL_CONNECTED) {
Serial.println(F("[Ping] WiFi tidak terhubung."));
return false;
}

Serial.println(F("[Ping] Menghubungi server..."));
HTTPClient http;
http.begin(PING_URL);
http.addHeader("Authorization", API_KEY);
http.addHeader("ngrok-skip-browser-warning", "true"); // bypass halaman warning ngrok
http.setTimeout(HTTP_TIMEOUT);

int code = http.GET();
if (code == 200) {
Serial.print(F("[Ping] Server OK — "));
Serial.println(http.getString());
http.end();
return true;
} else {
Serial.print(F("[Ping] Gagal — HTTP code: "));
Serial.println(code);
http.end();
return false;
}
}

// ================================================================
// sendPresensi() — Kirim data UID ke endpoint POST /api/presensi
// Laravel akan:
// 1. Validasi API Key dari header Authorization
// 2. Cari user berdasarkan rfid_uid
// 3. Tentukan status masuk/pulang
// 4. Simpan ke tabel attendances
// 5. Broadcast event ke WebSocket (Reverb)
// Return: true jika server merespons HTTP 200 (berhasil)
// ================================================================
bool sendPresensi(const String& uid) {
Serial.println(F("[HTTP] Mengirim presensi ke server..."));

HTTPClient http;
http.begin(SERVER_URL);
http.addHeader("Content-Type", "application/json");
http.addHeader("Authorization", API_KEY);
http.addHeader("ngrok-skip-browser-warning", "true");
http.setTimeout(HTTP_TIMEOUT);

// Buat JSON body: {"uid":"A1B2C3D4","device_id":"ESP32-RUANG-A"}
JsonDocument doc;
doc["uid"] = uid;
doc["device_id"] = DEVICE_CODE;
String body;
serializeJson(doc, body);

Serial.print(F("[HTTP] POST body: "));
Serial.println(body);

int httpCode = http.POST(body);
Serial.print(F("[HTTP] Response code: "));
Serial.println(httpCode);

if (httpCode == 200) {
    // --- Parse response JSON ---
    String payload = http.getString();
    Serial.print(F("[HTTP] Payload: "));
    Serial.println(payload);

    JsonDocument resp;
    DeserializationError err = deserializeJson(resp, payload);

    const char* apiStatus = !err ? (resp["status"] | "error") : "error";

    if (String(apiStatus) == "success") {
        // Absensi benar-benar berhasil
        const char* name     = resp["data"]["name"]      | "?";
        const char* status   = resp["data"]["status"]    | "?";
        const char* schedule = resp["data"]["schedule"]  | "Di luar jadwal";
        const char* ts       = resp["data"]["timestamp"] | "?";

        Serial.println();
        Serial.println(F("  ╔══════════════════════════════╗"));
        Serial.println(F("  ║     PRESENSI BERHASIL ✓      ║"));
        Serial.println(F("  ╠══════════════════════════════╣"));
        Serial.print(F("  ║ Nama    : ")); Serial.println(name);
        Serial.print(F("  ║ Status  : ")); Serial.println(status);
        Serial.print(F("  ║ Jadwal  : ")); Serial.println(schedule);
        Serial.print(F("  ║ Waktu   : ")); Serial.println(ts);
        Serial.println(F("  ╚══════════════════════════════╝"));

        successIndicator(); // LED hijau + beep 1x pelan
    } 
    else if (String(apiStatus) == "registered") {
        // Kartu baru terdaftar otomatis atau belum di-assign
        Serial.println();
        Serial.println(F("  ╔══════════════════════════════╗"));
        Serial.println(F("  ║     KARTU TERDAFTAR ✓        ║"));
        Serial.println(F("  ╠══════════════════════════════╣"));
        Serial.println(F("  ║ Kartu baru/belum di-assign.  ║"));
        Serial.println(F("  ║ Silakan hubungi admin web.   ║"));
        Serial.println(F("  ╚══════════════════════════════╝"));

        unknownCardIndicator(); // LED merah + beep 3x
    }

    http.end();
    return true;

} else if (httpCode == 400) {
    // Error validasi (Misal: Sudah absen masuk/belum jam pulang)
    // Langsung tampilkan error, jangan simpan ke offline queue
    Serial.println(F("[ERROR] Validasi Gagal!"));
    String payload = http.getString();
    JsonDocument resp;
    deserializeJson(resp, payload);
    const char* message = resp["message"] | "Error validasi";
    
    Serial.print(F("[Pesan] ")); Serial.println(message);
    
    errorIndicator(); // LED merah + beep panjang
    http.end();
    return false;

} else if (httpCode == 404) {
    // UID tidak ditemukan dan tidak terdaftar (biasanya jika fitur registrasi dimatikan)
    Serial.println(F("[ERROR] UID tidak terdaftar!"));
    unknownCardIndicator();
    http.end();
    return false;

} else if (httpCode == 401) {
    // API Key salah atau tidak ada → cek config API_KEY
    Serial.println(F("[ERROR] Unauthorized — periksa API_KEY!"));
    errorIndicator(); // LED merah + beep panjang
    http.end();
    return false;

} else if (httpCode == -1) {
    // Koneksi ke server gagal (timeout, server mati, URL salah)
    Serial.println(F("[ERROR] Tidak bisa konek ke server (timeout)."));
    Serial.println(F("[Queue] Data disimpan ke offline queue."));
    saveToQueue(uid);
    errorIndicator();
    http.end();
    return false;

} else {
    // Error sistem lain (500, dll) -> simpan ke antrean untuk dicoba lagi nanti
    Serial.print(F("[ERROR] HTTP error: ")); Serial.println(httpCode);
    saveToQueue(uid);
    errorIndicator();
    http.end();
    return false;
}
}

// ================================================================
// saveToQueue() — Simpan UID ke antrian offline di flash ESP32
// Menggunakan Preferences.h (key-value store di NVS flash)
// Data tetap ada walau ESP32 dimatikan / restart
// Format key: "uid*0", "uid_1", "uid_2", dst.
// ================================================================
void saveToQueue(const String& uid) {
int count = prefs.getInt("count", 0); // ambil jumlah antrian saat ini
String key = "uid*" + String(count); // buat key baru di posisi terakhir
prefs.putString(key.c_str(), uid); // simpan UID
prefs.putInt("count", count + 1); // increment counter

Serial.print(F("[Queue] Tersimpan ["));
Serial.print(key); Serial.print(F("] = ")); Serial.print(uid);
Serial.print(F(" (total: ")); Serial.print(count + 1); Serial.println(F(")"));
}

// ================================================================
// syncOfflineQueue() — Kirim semua data antrian offline ke server
// Dipanggil otomatis setelah WiFi kembali terhubung
// Jika ada yang gagal kirim, proses dihentikan (coba lagi nanti)
// ================================================================
void syncOfflineQueue() {
int count = prefs.getInt("count", 0);
if (count == 0) return; // tidak ada antrian, skip

Serial.print(F("[Sync] Mensinkronkan "));
Serial.print(count);
Serial.println(F(" data offline..."));

int successCount = 0;
for (int i = 0; i < count; i++) {
String key = "uid\_" + String(i);
String uid = prefs.getString(key.c_str(), "");

    if (uid.isEmpty()) { successCount++; continue; } // slot kosong, lewati

    Serial.print(F("[Sync] ")); Serial.print(i + 1);
    Serial.print(F("/")); Serial.print(count);
    Serial.print(F(" → UID: ")); Serial.println(uid);

    bool ok = sendPresensi(uid);
    if (ok) {
      prefs.remove(key.c_str()); // hapus dari flash setelah berhasil terkirim
      successCount++;
      delay(300); // jeda antar request agar tidak flood server
    } else {
      Serial.println(F("[Sync] Gagal, hentikan sync sementara."));
      break; // berhenti, coba lagi di iterasi loop berikutnya
    }

}

// Reset counter jika semua berhasil, atau kurangi sesuai sisa
if (successCount >= count) {
prefs.putInt("count", 0);
Serial.println(F("[Sync] Semua data offline berhasil dikirim!"));
} else {
prefs.putInt("count", count - successCount);
Serial.print(F("[Sync] Tersisa ")); Serial.print(count - successCount);
Serial.println(F(" data belum terkirim."));
}
}

// ================================================================
// getQueueCount() — Ambil jumlah data yang ada di antrian offline
// Digunakan untuk cek apakah perlu sync setelah WiFi reconnect
// ================================================================
int getQueueCount() {
return prefs.getInt("count", 0);
}

// ================================================================
// INDIKATOR VISUAL & AUDIO
// Setiap kejadian punya pola LED + buzzer yang berbeda
// agar user tahu hasilnya tanpa melihat layar
// ================================================================

// Presensi BERHASIL → LED hijau nyala 1 detik + beep 1x pendek pelan
void successIndicator() {
Serial.println(F("[LED] Hijau ON — presensi berhasil"));
digitalWrite(PIN_LED_GREEN, HIGH);
buzzTone(150); // 1 beep pendek
delay(850);
digitalWrite(PIN_LED_GREEN, LOW);
Serial.println(F("[LED] Hijau OFF"));
}

// Kartu TIDAK DIKENAL (404) → LED merah + beep 3x pendek
void unknownCardIndicator() {
Serial.println(F("[LED] Merah — kartu tidak terdaftar"));
digitalWrite(PIN_LED_RED, HIGH);
buzzTone(100, 3, 100); // 3 beep pendek
delay(200);
digitalWrite(PIN_LED_RED, LOW);
}

// Error SERVER / timeout → LED merah + beep panjang 1x
void errorIndicator() {
Serial.println(F("[LED] Merah — error server/koneksi"));
digitalWrite(PIN_LED_RED, HIGH);
buzzTone(600); // 1 beep panjang
delay(200);
digitalWrite(PIN_LED_RED, LOW);
}

// Mode OFFLINE (WiFi mati) → LED merah kedip 3x + beep pendek
void offlineIndicator() {
Serial.println(F("[LED] Merah kedip — offline, data disimpan"));
for (int i = 0; i < 3; i++) {
digitalWrite(PIN_LED_RED, HIGH); delay(120);
digitalWrite(PIN_LED_RED, LOW); delay(120);
}
buzzTone(250); // 1 beep sedang
}

// ================================================================
// buzzTone() — Bunyikan buzzer dengan nada PWM yang lembut
// Menggunakan tone() yang kompatibel semua versi ESP32 Arduino core
//
// Parameter:
// durationMs : lama satu beep (ms)
// count : berapa kali beep (default 1)
// gapMs : jeda antar beep jika count > 1 (default 100ms)
//
// Kenapa tone(), bukan ledcSetup/ledcAttachPin?
// → ESP32 Arduino core v3.x menghapus ledcSetup() & ledcAttachPin()
// sehingga kode lama error "was not declared in this scope".
// tone() tersedia di semua versi (v2.x dan v3.x) dan secara
// internal tetap menggunakan LEDC hardware ESP32.
// Untuk kurangi volume: turunkan BUZZER_FREQ_HZ atau jauhkan
// buzzer dari user — tone() tidak support kontrol duty cycle.
// ================================================================
void buzzTone(int durationMs, int count, int gapMs) {
for (int i = 0; i < count; i++) {
tone(PIN_BUZZER, BUZZER_FREQ_HZ, durationMs); // nyala selama durationMs lalu mati otomatis
delay(durationMs); // tunggu sampai beep selesai
if (i < count - 1) {
noTone(PIN_BUZZER); // pastikan berhenti
delay(gapMs); // jeda antar beep
}
}
noTone(PIN_BUZZER); // pastikan mati setelah selesai
}

// ================================================================
// buzzOff() — Paksa matikan buzzer
// Dipanggil saat perlu memastikan buzzer berhenti (safety)
// ================================================================
void buzzOff() {
noTone(PIN_BUZZER);
}

// ================================================================
// printStatus() — Tampilkan status device ke Serial Monitor
// Dipanggil dengan ketik 's' di Serial Monitor (mode Wokwi)
// ================================================================
void printStatus() {
printSeparator();
Serial.println(F(" === STATUS DEVICE ==="));
Serial.print(F(" Device : ")); Serial.println(DEVICE_CODE);
Serial.print(F(" WiFi : ")); Serial.println(WiFi.status() == WL_CONNECTED ? "ONLINE" : "OFFLINE");
if (WiFi.status() == WL_CONNECTED) {
Serial.print(F(" IP : ")); Serial.println(WiFi.localIP());
Serial.print(F(" RSSI : ")); Serial.print(WiFi.RSSI()); Serial.println(F(" dBm"));
}
Serial.print(F(" Server : ")); Serial.println(SERVER_URL);
Serial.print(F(" Queue : ")); Serial.print(getQueueCount()); Serial.println(F(" data offline"));
Serial.print(F(" Buzzer : ")); Serial.print(BUZZER_FREQ_HZ); Serial.println(F(" Hz (tone)"));
#ifdef WOKWI_SIM
Serial.println(F(" Mode : Simulasi Wokwi"));
Serial.println(F(" Perintah : 0/1/2=kartu s=status c=clear p=ping"));
#else
Serial.println(F(" Mode : Hardware MFRC522"));
#endif
printSeparator();
}

void printBanner() {
Serial.println();
Serial.println(F(" ╔══════════════════════════════════════╗"));
Serial.println(F(" ║ SISTEM ABSENSI RFID ║"));
Serial.println(F(" ║ ESP32 DevKit C v4 + MFRC522 ║"));
Serial.println(F(" ║ Target: Laravel 13 + Reverb ║"));
Serial.println(F(" ╚══════════════════════════════════════╝"));
}

void printSeparator() {
Serial.println(F(" ----------------------------------------"));
}
