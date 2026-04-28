# Refactored ESP32 RFID Firmware

> Disesuaikan dengan `diagram.json` Wokwi dan `docs/PROJECT_PLAN_ABSENSI_RFID.md`

## Perubahan dari kode lama

| Aspek | Sebelum | Sesudah |
|---|---|---|
| RFID Library | Simulasi saja (PN532 di komentar) | `MFRC522.h` + mode simulasi Wokwi |
| Config | Hardcode di `.ino` | Terpisah di `config.h` |
| Queue namespace | `rfid_q` | `rfid_queue` (sesuai plan §10.3) |
| Queue key format | `u0`, `u1` | `uid_0`, `uid_1` (sesuai plan §10.3) |
| Indikator timing | Tidak sesuai plan | Sesuai plan §10.4 |
| Banner | Tulis PN532 | Tulis MFRC522 |
| Ping | Tidak ada | Ada `pingServer()` |
| Auto-reconnect | Hanya saat offline scan | Juga di awal loop (periodik) |

---

## File 1: `config.h`

```cpp
#ifndef CONFIG_H
#define CONFIG_H

// ================================================================
//  KONFIGURASI — Ganti sesuai environment
//  JANGAN commit file ini ke GitHub!
// ================================================================

// --- WiFi ---
// Wokwi: "Wokwi-GUEST" + password kosong
// Hardware: ganti dengan SSID & password jaringanmu
const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";

// --- Server Laravel ---
// Lokal: expose dulu dengan ngrok http 8000
// Lalu isi dengan URL ngrok: https://xxxx.ngrok.io
const char* SERVER_URL = "https://YOUR-SERVER.com/api/presensi";
const char* PING_URL   = "https://YOUR-SERVER.com/api/ping";

// --- API Key (harus sama dengan RFID_API_KEY di .env Laravel) ---
const char* API_KEY = "Bearer GANTI_DENGAN_API_KEY_MINIMAL_32_KARAKTER";

// --- Device Identity (hardcode per perangkat) ---
const char* DEVICE_CODE = "ESP32-WOKWI-SIM";

// --- Pin Definitions (sesuai diagram.json Wokwi) ---
//  MFRC522:
//    SDA  → GPIO 5
//    SCK  → GPIO 18
//    MOSI → GPIO 23
//    MISO → GPIO 19
//    RST  → GPIO 22
//    VCC  → 3.3V
//    GND  → GND
#define PIN_RFID_SS   5
#define PIN_RFID_RST  22

//  LED & Buzzer:
//    LED Hijau (+) → GPIO 26 → R220Ω → GND
//    LED Merah (+) → GPIO 27 → R220Ω → GND
//    Buzzer    (+) → GPIO 25 → GND
#define PIN_LED_GREEN 26
#define PIN_LED_RED   27
#define PIN_BUZZER    25

// --- Timing ---
#define DELAY_AFTER_SCAN   2000   // ms — jeda anti double-scan
#define WIFI_TIMEOUT      10000   // ms — batas tunggu konek WiFi
#define HTTP_TIMEOUT       5000   // ms — batas tunggu response server
#define WIFI_RECHECK_MS   30000   // ms — interval cek ulang WiFi

#endif
```

---

## File 2: `rfid_absensi.ino`

```cpp
/*
 * ================================================================
 *  SISTEM ABSENSI RFID
 *  Hardware : ESP32 DevKit C v4 + MFRC522 + LED + Buzzer
 *  Server   : Laravel 13 API + Laravel Reverb
 *  Diagram  : Wokwi diagram.json
 *
 *  Mode Simulasi Wokwi (Serial Monitor):
 *    Ketik  0  → kartu "Budi Santoso"   (UID: A1B2C3D4) — terdaftar
 *    Ketik  1  → kartu "Sari Dewi"      (UID: E5F6A7B8) — terdaftar
 *    Ketik  2  → kartu tidak dikenal    (UID: DEADBEEF) — test 404
 *    Ketik  s  → lihat status device & queue
 *    Ketik  c  → clear offline queue
 *    Ketik  p  → ping server
 *
 *  Library (tambahkan di Library Manager):
 *    - MFRC522 (miguelbalboa)
 *    - ArduinoJson @ 7.x
 * ================================================================
 */

#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <SPI.h>
#include <MFRC522.h>
#include "config.h"

// ================================================================
//  MODE KOMPILASI
//  Definisikan WOKWI_SIM untuk simulasi via Serial Monitor.
//  Hapus/comment untuk mode hardware MFRC522 asli.
// ================================================================
#define WOKWI_SIM

// ================================================================
//  KARTU SIMULASI (hanya aktif saat WOKWI_SIM)
// ================================================================
#ifdef WOKWI_SIM
struct SimCard {
  const char* uid;
  const char* label;
};

const SimCard SIM_CARDS[] = {
  { "A1B2C3D4", "Budi Santoso  (terdaftar)" },
  { "E5F6A7B8", "Sari Dewi     (terdaftar)" },
  { "DEADBEEF", "Kartu Unknown (tidak terdaftar)" },
};
const int SIM_CARD_COUNT = 3;
#endif

// ================================================================
//  GLOBAL STATE
// ================================================================
MFRC522 mfrc522(PIN_RFID_SS, PIN_RFID_RST);
Preferences prefs;

bool isOnline             = false;
unsigned long lastScanTime    = 0;
unsigned long lastWiFiCheck   = 0;

#ifdef WOKWI_SIM
int simCardIndex = -1;
#endif

// ================================================================
//  PROTOTYPES
// ================================================================
void     connectWiFi();
String   readUID();
bool     sendPresensi(const String& uid);
bool     pingServer();
void     saveToQueue(const String& uid);
void     syncOfflineQueue();
void     successIndicator(const char* name, const char* status, const char* schedule);
void     unknownCardIndicator();
void     errorIndicator();
void     offlineIndicator();
void     buzz(int durationMs, int count = 1, int gapMs = 100);
void     printStatus();
void     printBanner();
void     printSeparator();
int      getQueueCount();

// ================================================================
//  SETUP
// ================================================================
void setup() {
  Serial.begin(115200);
  delay(600);

  // --- LED & Buzzer ---
  pinMode(PIN_LED_GREEN, OUTPUT);
  pinMode(PIN_LED_RED,   OUTPUT);
  pinMode(PIN_BUZZER,    OUTPUT);
  digitalWrite(PIN_LED_GREEN, LOW);
  digitalWrite(PIN_LED_RED,   LOW);
  digitalWrite(PIN_BUZZER,    LOW);

  // --- SPI & MFRC522 ---
  SPI.begin(18, 19, 23, PIN_RFID_SS);
  mfrc522.PCD_Init();
  delay(100);

  // --- Preferences (offline queue) ---
  prefs.begin("rfid_queue", false);

  printBanner();
  connectWiFi();

#ifdef WOKWI_SIM
  Serial.println();
  Serial.println(F("  [MODE SIMULASI WOKWI]"));
  Serial.println(F("  Ketik angka + Enter untuk simulasi kartu:"));
  Serial.println(F("  [0] Budi Santoso   (UID: A1B2C3D4)"));
  Serial.println(F("  [1] Sari Dewi      (UID: E5F6A7B8)"));
  Serial.println(F("  [2] Kartu Unknown  (UID: DEADBEEF)"));
  Serial.println(F("  [s] Status  [c] Clear queue  [p] Ping server"));
#else
  Serial.println(F("  [MODE HARDWARE MFRC522]"));
  Serial.println(F("  Tempelkan kartu RFID ke reader..."));

  // Cek MFRC522 terdeteksi
  byte v = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  if (v == 0x00 || v == 0xFF) {
    Serial.println(F("[ERROR] MFRC522 tidak terdeteksi! Cek wiring."));
  } else {
    Serial.print(F("[RFID] MFRC522 firmware: 0x"));
    Serial.println(v, HEX);
  }
#endif
  printSeparator();
}

// ================================================================
//  LOOP
// ================================================================
void loop() {

  // --- Periodik: cek WiFi & sync queue ---
  if (millis() - lastWiFiCheck > WIFI_RECHECK_MS) {
    lastWiFiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      connectWiFi();
    }
    if (WiFi.status() == WL_CONNECTED && getQueueCount() > 0) {
      syncOfflineQueue();
    }
  }

#ifdef WOKWI_SIM
  // --- Baca input Serial (simulasi tap kartu) ---
  if (Serial.available()) {
    String input = Serial.readStringUntil('\n');
    input.trim();

    if (input == "0" || input == "1" || input == "2") {
      simCardIndex = input.toInt();
    } else if (input == "s") {
      printStatus();
      return;
    } else if (input == "c") {
      prefs.putInt("count", 0);
      Serial.println(F("[Queue] Offline queue dikosongkan."));
      return;
    } else if (input == "p") {
      pingServer();
      return;
    } else {
      Serial.println(F("[!] Perintah tidak dikenal. Ketik 0/1/2/s/c/p"));
      return;
    }
  }

  if (simCardIndex < 0) {
    delay(50);
    return;
  }
#endif

  // --- Anti double-scan ---
  if (millis() - lastScanTime < DELAY_AFTER_SCAN) {
#ifdef WOKWI_SIM
    simCardIndex = -1;
#endif
    return;
  }

  // --- Baca UID ---
  String uid = readUID();
  if (uid.isEmpty()) {
#ifdef WOKWI_SIM
    simCardIndex = -1;
#endif
    delay(50);
    return;
  }

  lastScanTime = millis();

  printSeparator();
  Serial.print(F("[RFID] Kartu terdeteksi — UID: "));
  Serial.println(uid);

  // --- Cek WiFi ---
  isOnline = (WiFi.status() == WL_CONNECTED);
  Serial.print(F("[WiFi] Status: "));
  Serial.println(isOnline ? "ONLINE" : "OFFLINE");

  if (isOnline) {
    bool ok = sendPresensi(uid);
    if (ok) {
      syncOfflineQueue();
    }
  } else {
    Serial.println(F("[Queue] WiFi mati, UID disimpan ke offline queue."));
    saveToQueue(uid);
    offlineIndicator();
    connectWiFi();
  }

#ifdef WOKWI_SIM
  simCardIndex = -1;
#endif
  Serial.println(F("[INFO] Siap menerima kartu berikutnya..."));
  printSeparator();
}

// ================================================================
//  Baca UID
// ================================================================
String readUID() {
#ifdef WOKWI_SIM
  // --- Mode simulasi: ambil UID dari array ---
  if (simCardIndex < 0 || simCardIndex >= SIM_CARD_COUNT) {
    return "";
  }
  Serial.print(F("[SIM] Kartu: "));
  Serial.println(SIM_CARDS[simCardIndex].label);
  return String(SIM_CARDS[simCardIndex].uid);

#else
  // --- Mode hardware: baca dari MFRC522 ---
  if (!mfrc522.PICC_IsNewCardPresent()) {
    return "";
  }
  if (!mfrc522.PICC_ReadCardSerial()) {
    return "";
  }

  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) {
      uid += "0";
    }
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  return uid;
#endif
}

// ================================================================
//  Koneksi WiFi
// ================================================================
void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    return;
  }

  Serial.print(F("[WiFi] Menghubungkan ke: "));
  Serial.println(WIFI_SSID);

  WiFi.mode(WIFI_STA);
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
    Serial.println(F("[WiFi] Gagal terhubung. Mode offline."));
    isOnline = false;
  }
}

// ================================================================
//  Ping server
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
  http.setTimeout(HTTP_TIMEOUT);

  int code = http.GET();
  if (code == 200) {
    Serial.print(F("[Ping] OK — "));
    Serial.println(http.getString());
    http.end();
    return true;
  } else {
    Serial.print(F("[Ping] Gagal — HTTP "));
    Serial.println(code);
    http.end();
    return false;
  }
}

// ================================================================
//  Kirim presensi ke Laravel API
// ================================================================
bool sendPresensi(const String& uid) {
  Serial.println(F("[HTTP] Mengirim presensi ke server..."));

  HTTPClient http;
  http.begin(SERVER_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", API_KEY);
  http.setTimeout(HTTP_TIMEOUT);

  // Build JSON
  JsonDocument doc;
  doc["uid"]       = uid;
  doc["device_id"] = DEVICE_CODE;
  String body;
  serializeJson(doc, body);

  Serial.print(F("[HTTP] POST → "));
  Serial.println(body);

  int httpCode = http.POST(body);
  Serial.print(F("[HTTP] Response code: "));
  Serial.println(httpCode);

  if (httpCode == 200) {
    String payload = http.getString();
    Serial.print(F("[HTTP] Payload: "));
    Serial.println(payload);

    JsonDocument resp;
    DeserializationError err = deserializeJson(resp, payload);

    const char* name     = "?";
    const char* status   = "?";
    const char* schedule = "?";
    const char* ts       = "?";

    if (!err) {
      name     = resp["data"]["name"]      | "?";
      status   = resp["data"]["status"]    | "?";
      schedule = resp["data"]["schedule"]  | "Di luar jadwal";
      ts       = resp["data"]["timestamp"] | "?";
    }

    Serial.println();
    Serial.println(F("  ╔══════════════════════════════╗"));
    Serial.println(F("  ║     PRESENSI BERHASIL ✓      ║"));
    Serial.println(F("  ╠══════════════════════════════╣"));
    Serial.print(F("  ║ Nama    : ")); Serial.println(name);
    Serial.print(F("  ║ Status  : ")); Serial.println(status);
    Serial.print(F("  ║ Jadwal  : ")); Serial.println(schedule);
    Serial.print(F("  ║ Waktu   : ")); Serial.println(ts);
    Serial.println(F("  ╚══════════════════════════════╝"));

    successIndicator(name, status, schedule);
    http.end();
    return true;

  } else if (httpCode == 404) {
    Serial.println(F("[ERROR] UID tidak terdaftar di database!"));
    Serial.println(http.getString());
    unknownCardIndicator();
    http.end();
    return false;

  } else if (httpCode == 401) {
    Serial.println(F("[ERROR] Unauthorized — periksa API_KEY di config.h!"));
    errorIndicator();
    http.end();
    return false;

  } else if (httpCode == -1) {
    Serial.println(F("[ERROR] Tidak bisa konek ke server (timeout)."));
    Serial.println(F("[Queue] Data disimpan ke offline queue."));
    saveToQueue(uid);
    errorIndicator();
    http.end();
    return false;

  } else {
    Serial.print(F("[ERROR] HTTP error: "));
    Serial.println(httpCode);
    Serial.println(F("[Queue] Data disimpan ke offline queue."));
    saveToQueue(uid);
    errorIndicator();
    http.end();
    return false;
  }
}

// ================================================================
//  Offline Queue — simpan (sesuai plan §10.3)
//  Namespace: "rfid_queue"
//  Keys: "count" (int), "uid_0", "uid_1", ... (string)
// ================================================================
void saveToQueue(const String& uid) {
  int count = prefs.getInt("count", 0);
  String key = "uid_" + String(count);
  prefs.putString(key.c_str(), uid);
  prefs.putInt("count", count + 1);

  Serial.print(F("[Queue] Tersimpan ["));
  Serial.print(key);
  Serial.print(F("] = "));
  Serial.print(uid);
  Serial.print(F("  (total: "));
  Serial.print(count + 1);
  Serial.println(F(")"));
}

// ================================================================
//  Offline Queue — sync ke server
// ================================================================
void syncOfflineQueue() {
  int count = prefs.getInt("count", 0);
  if (count == 0) {
    return;
  }

  Serial.println();
  Serial.print(F("[Sync] Mengirim "));
  Serial.print(count);
  Serial.println(F(" data offline..."));

  int successCount = 0;
  for (int i = 0; i < count; i++) {
    String key = "uid_" + String(i);
    String uid = prefs.getString(key.c_str(), "");

    if (uid.isEmpty()) {
      successCount++;
      continue;
    }

    Serial.print(F("[Sync] "));
    Serial.print(i + 1);
    Serial.print(F("/"));
    Serial.print(count);
    Serial.print(F(" → UID: "));
    Serial.println(uid);

    bool ok = sendPresensi(uid);
    if (ok) {
      prefs.remove(key.c_str());
      successCount++;
      delay(300);
    } else {
      Serial.println(F("[Sync] Gagal, hentikan sync sementara."));
      break;
    }
  }

  if (successCount >= count) {
    prefs.putInt("count", 0);
    Serial.println(F("[Sync] Semua data offline berhasil dikirim!"));
  } else {
    int remaining = count - successCount;
    prefs.putInt("count", remaining);
    Serial.print(F("[Sync] Tersisa "));
    Serial.print(remaining);
    Serial.println(F(" data belum terkirim."));
  }
}

int getQueueCount() {
  return prefs.getInt("count", 0);
}

// ================================================================
//  Indikator: Sukses (sesuai plan §10.4)
//  LED Hijau ON 1 detik, Buzzer ON 100ms, LED OFF
// ================================================================
void successIndicator(const char* name, const char* status, const char* schedule) {
  Serial.println(F("[LED] ✓ Hijau menyala"));
  digitalWrite(PIN_LED_GREEN, HIGH);
  buzz(100);
  delay(900);
  digitalWrite(PIN_LED_GREEN, LOW);
}

// ================================================================
//  Indikator: Kartu tidak dikenal (sesuai plan §10.4)
//  LED Merah ON, Buzzer 3x (ON 100ms, OFF 100ms), LED OFF
// ================================================================
void unknownCardIndicator() {
  Serial.println(F("[LED] ✗ Merah — kartu tidak dikenal"));
  digitalWrite(PIN_LED_RED, HIGH);
  buzz(100, 3, 100);
  delay(200);
  digitalWrite(PIN_LED_RED, LOW);
}

// ================================================================
//  Indikator: Error server (sesuai plan §10.4)
//  LED Merah ON, Buzzer ON 500ms, LED OFF
// ================================================================
void errorIndicator() {
  Serial.println(F("[LED] ! Merah — error server"));
  digitalWrite(PIN_LED_RED, HIGH);
  buzz(500);
  delay(200);
  digitalWrite(PIN_LED_RED, LOW);
}

// ================================================================
//  Indikator: Offline (sesuai plan §10.4)
//  LED Merah kedip 3x cepat, Buzzer ON 300ms
// ================================================================
void offlineIndicator() {
  Serial.println(F("[LED] ~ Merah kedip — offline mode"));
  for (int i = 0; i < 3; i++) {
    digitalWrite(PIN_LED_RED, HIGH);
    delay(120);
    digitalWrite(PIN_LED_RED, LOW);
    delay(120);
  }
  buzz(300);
}

// ================================================================
//  Buzzer helper
// ================================================================
void buzz(int durationMs, int count, int gapMs) {
  for (int i = 0; i < count; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(durationMs);
    digitalWrite(PIN_BUZZER, LOW);
    if (i < count - 1) {
      delay(gapMs);
    }
  }
}

// ================================================================
//  Print status
// ================================================================
void printStatus() {
  printSeparator();
  Serial.println(F("  === STATUS DEVICE ==="));
  Serial.print(F("  Device   : ")); Serial.println(DEVICE_CODE);
  Serial.print(F("  WiFi     : "));
  Serial.println(WiFi.status() == WL_CONNECTED ? "ONLINE" : "OFFLINE");
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F("  IP       : ")); Serial.println(WiFi.localIP());
    Serial.print(F("  RSSI     : ")); Serial.print(WiFi.RSSI()); Serial.println(F(" dBm"));
  }
  Serial.print(F("  Server   : ")); Serial.println(SERVER_URL);
  Serial.print(F("  Queue    : "));
  Serial.print(getQueueCount());
  Serial.println(F(" data offline"));
#ifdef WOKWI_SIM
  Serial.println(F("  Mode     : Simulasi Wokwi"));
  Serial.println(F("  Perintah : 0/1/2=tap  s=status  c=clear  p=ping"));
#else
  Serial.println(F("  Mode     : Hardware MFRC522"));
#endif
  printSeparator();
}

void printBanner() {
  Serial.println();
  Serial.println(F("  ╔══════════════════════════════════════╗"));
  Serial.println(F("  ║    SISTEM ABSENSI RFID               ║"));
  Serial.println(F("  ║    ESP32 DevKit C v4 + MFRC522       ║"));
  Serial.println(F("  ║    Target: Laravel 13 + Reverb       ║"));
  Serial.println(F("  ╚══════════════════════════════════════╝"));
}

void printSeparator() {
  Serial.println(F("  ----------------------------------------"));
}
```

---

## Wiring (sesuai `diagram.json`)

| Komponen | Pin MFRC522/LED/Buzzer | Pin ESP32 |
|---|---|---|
| MFRC522 SDA | SDA | GPIO 5 |
| MFRC522 SCK | SCK | GPIO 18 |
| MFRC522 MOSI | MOSI | GPIO 23 |
| MFRC522 MISO | MISO | GPIO 19 |
| MFRC522 RST | RST | GPIO 22 |
| MFRC522 VCC | — | 3.3V |
| MFRC522 GND | — | GND |
| LED Hijau | Anode → R220Ω | GPIO 26 |
| LED Merah | Anode → R220Ω | GPIO 27 |
| Buzzer | Pin 1 | GPIO 25 |

## `libraries.txt` (Wokwi)

```
miguelbalboa/MFRC522
bblanchon/ArduinoJson@^7
```
