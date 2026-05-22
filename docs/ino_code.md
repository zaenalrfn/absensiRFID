#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <SPI.h>
#include <MFRC522.h>

const char* WIFI_SSID     = "";
const char* WIFI_PASSWORD = "";

const char* SERVER_URL = "http://[IP_ADDRESS]/api/presensi";
const char* PING_URL   = "http://[IP_ADDRESS]/api/ping";

const char* API_KEY     = "Bearer ----";
const char* DEVICE_CODE = "ESP32-MOBILE-IOT";

#define PIN_RFID_SS   33
#define PIN_RFID_RST  32
#define PIN_BUZZER    12

#define BUZZER_FREQ_HZ   2800
#define DELAY_AFTER_SCAN 2000
#define WIFI_TIMEOUT     10000
#define HTTP_TIMEOUT     8000
#define WIFI_RECHECK_MS  30000

MFRC522     mfrc522(PIN_RFID_SS, PIN_RFID_RST);
Preferences prefs;

bool          isOnline      = false;
unsigned long lastScanTime  = 0;
unsigned long lastWiFiCheck = 0;

void   connectWiFi();
String readUID();
bool   sendPresensi(const String& uid);
bool   pingServer();
void   saveToQueue(const String& uid);
void   syncOfflineQueue();
int    getQueueCount();
void   successIndicator();
void   unknownCardIndicator();
void   errorIndicator();
void   offlineIndicator();
void   buzzTone(int durationMs, int count = 1, int gapMs = 100);
void   buzzOff();
void   printStatus();
void   printBanner();
void   printSeparator();

void setup() {
  Serial.begin(115200);
  delay(600);

  pinMode(PIN_BUZZER, OUTPUT);
  noTone(PIN_BUZZER);

  SPI.begin(18, 19, 23, PIN_RFID_SS);
  mfrc522.PCD_Init();
  delay(100);

  prefs.begin("rfid_queue", false);

  printBanner();
  connectWiFi();

  Serial.println(F("[MODE HARDWARE MFRC522]"));
  Serial.println(F("Tempelkan kartu RFID ke reader..."));

  byte v = mfrc522.PCD_ReadRegister(mfrc522.VersionReg);
  if (v == 0x00 || v == 0xFF) {
    Serial.println(F("[ERROR] MFRC522 tidak terdeteksi! Cek wiring SPI."));
  } else {
    Serial.print(F("[RFID] MFRC522 firmware: 0x"));
    Serial.println(v, HEX);
  }

  printSeparator();
}

void loop() {

  if (millis() - lastWiFiCheck > WIFI_RECHECK_MS) {
    lastWiFiCheck = millis();
    if (WiFi.status() != WL_CONNECTED) {
      connectWiFi();
    }
    if (WiFi.status() == WL_CONNECTED && getQueueCount() > 0) {
      syncOfflineQueue();
    }
  }

  if (millis() - lastScanTime < DELAY_AFTER_SCAN) {
    return;
  }

  String uid = readUID();
  if (uid.isEmpty()) {
    delay(50);
    return;
  }

  lastScanTime = millis();

  printSeparator();
  Serial.print(F("[RFID] Kartu terdeteksi — UID: "));
  Serial.println(uid);

  isOnline = (WiFi.status() == WL_CONNECTED);
  Serial.print(F("[WiFi] Status: "));
  Serial.println(isOnline ? "ONLINE" : "OFFLINE");

  if (isOnline) {
    bool ok = sendPresensi(uid);
    if (ok) syncOfflineQueue();
  } else {
    Serial.println(F("[Queue] WiFi mati, UID disimpan ke offline queue."));
    saveToQueue(uid);
    offlineIndicator();
    connectWiFi();
  }

  Serial.println(F("[INFO] Siap menerima kartu berikutnya..."));
  printSeparator();
}

String readUID() {
  if (!mfrc522.PICC_IsNewCardPresent()) return "";
  if (!mfrc522.PICC_ReadCardSerial())   return "";

  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  return uid;
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

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
    Serial.println(F("[WiFi] Gagal terhubung. Mode offline aktif."));
    isOnline = false;
  }
}

bool pingServer() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(F("[Ping] WiFi tidak terhubung."));
    return false;
  }

  Serial.println(F("[Ping] Menghubungi server..."));
  HTTPClient http;
  http.begin(PING_URL);
  http.addHeader("Authorization", API_KEY);
  http.addHeader("ngrok-skip-browser-warning", "true");
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

bool sendPresensi(const String& uid) {
  Serial.println(F("[HTTP] Mengirim presensi ke server..."));

  HTTPClient http;
  http.begin(SERVER_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", API_KEY);
  http.addHeader("ngrok-skip-browser-warning", "true");
  http.setTimeout(HTTP_TIMEOUT);

  JsonDocument doc;
  doc["uid"]       = uid;
  doc["device_id"] = DEVICE_CODE;
  String body;
  serializeJson(doc, body);

  Serial.print(F("[HTTP] POST body: "));
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
    const char* apiStatus = !err ? (resp["status"] | "error") : "error";

    if (String(apiStatus) == "success") {
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

      successIndicator();

    } else if (String(apiStatus) == "registered") {
      Serial.println();
      Serial.println(F("  ╔══════════════════════════════╗"));
      Serial.println(F("  ║     KARTU TERDAFTAR ✓        ║"));
      Serial.println(F("  ╠══════════════════════════════╣"));
      Serial.println(F("  ║ Kartu baru/belum di-assign.  ║"));
      Serial.println(F("  ║ Silakan hubungi admin web.   ║"));
      Serial.println(F("  ╚══════════════════════════════╝"));

      buzzTone(100, 2, 100);
      delay(800);
    }

    http.end();
    return true;

  } else if (httpCode == 400) {
    Serial.println(F("[ERROR] Validasi Gagal!"));
    String payload = http.getString();
    JsonDocument resp;
    deserializeJson(resp, payload);
    const char* message = resp["message"] | "Error validasi";
    Serial.print(F("[Pesan] ")); Serial.println(message);
    errorIndicator();
    http.end();
    return false;

  } else if (httpCode == 404) {
    Serial.println(F("[ERROR] UID tidak terdaftar!"));
    unknownCardIndicator();
    http.end();
    return false;

  } else if (httpCode == 401) {
    Serial.println(F("[ERROR] Unauthorized — periksa API_KEY!"));
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
    Serial.print(F("[ERROR] HTTP error: ")); Serial.println(httpCode);
    saveToQueue(uid);
    errorIndicator();
    http.end();
    return false;
  }
}

void saveToQueue(const String& uid) {
  int    count = prefs.getInt("count", 0);
  String key   = "uid_" + String(count);
  prefs.putString(key.c_str(), uid);
  prefs.putInt("count", count + 1);

  Serial.print(F("[Queue] Tersimpan [")); Serial.print(key);
  Serial.print(F("] = ")); Serial.print(uid);
  Serial.print(F(" (total: ")); Serial.print(count + 1); Serial.println(F(")"));
}

void syncOfflineQueue() {
  int count = prefs.getInt("count", 0);
  if (count == 0) return;

  Serial.print(F("[Sync] Mensinkronkan "));
  Serial.print(count);
  Serial.println(F(" data offline..."));

  int successCount = 0;
  for (int i = 0; i < count; i++) {
    String key = "uid_" + String(i);
    String uid = prefs.getString(key.c_str(), "");

    if (uid.isEmpty()) { successCount++; continue; }

    Serial.print(F("[Sync] ")); Serial.print(i + 1);
    Serial.print(F("/")); Serial.print(count);
    Serial.print(F(" → UID: ")); Serial.println(uid);

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
    prefs.putInt("count", count - successCount);
    Serial.print(F("[Sync] Tersisa ")); Serial.print(count - successCount);
    Serial.println(F(" data belum terkirim."));
  }
}

int getQueueCount() {
  return prefs.getInt("count", 0);
}

void successIndicator() {
  Serial.println(F("[BUZZER] Sukses — 1 beep"));
  buzzTone(150);
  delay(850);
}

void unknownCardIndicator() {
  Serial.println(F("[BUZZER] Kartu tidak terdaftar — 3 beep"));
  buzzTone(100, 3, 100);
  delay(200);
}

void errorIndicator() {
  Serial.println(F("[BUZZER] Error — 1 beep panjang"));
  buzzTone(600);
  delay(200);
}

void offlineIndicator() {
  Serial.println(F("[BUZZER] Offline — 1 beep sedang"));
  buzzTone(250);
}

void buzzTone(int durationMs, int count, int gapMs) {
  for (int i = 0; i < count; i++) {
    tone(PIN_BUZZER, BUZZER_FREQ_HZ, durationMs);
    delay(durationMs);
    if (i < count - 1) {
      noTone(PIN_BUZZER);
      delay(gapMs);
    }
  }
  noTone(PIN_BUZZER);
}

void buzzOff() {
  noTone(PIN_BUZZER);
}

void printStatus() {
  printSeparator();
  Serial.println(F(" === STATUS DEVICE ==="));
  Serial.print(F(" Device : ")); Serial.println(DEVICE_CODE);
  Serial.print(F(" WiFi   : ")); Serial.println(WiFi.status() == WL_CONNECTED ? "ONLINE" : "OFFLINE");
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print(F(" IP     : ")); Serial.println(WiFi.localIP());
    Serial.print(F(" RSSI   : ")); Serial.print(WiFi.RSSI()); Serial.println(F(" dBm"));
  }
  Serial.print(F(" Server : ")); Serial.println(SERVER_URL);
  Serial.print(F(" Queue  : ")); Serial.print(getQueueCount()); Serial.println(F(" data offline"));
  Serial.print(F(" Buzzer : ")); Serial.print(BUZZER_FREQ_HZ); Serial.println(F(" Hz"));
  Serial.println(F(" Mode   : Hardware MFRC522"));
  printSeparator();
}

void printBanner() {
  Serial.println();
  Serial.println(F(" ╔══════════════════════════════════════╗"));
  Serial.println(F(" ║      SISTEM ABSENSI RFID             ║"));
  Serial.println(F(" ╚══════════════════════════════════════╝"));
}

void printSeparator() {
  Serial.println(F(" ----------------------------------------"));
}