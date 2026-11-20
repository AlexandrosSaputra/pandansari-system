# Panduan Integrasi Scanner ESP32

Dokumen ini ditujukan kepada tim pengembang firmware ESP32 yang menghubungkan barcode scanner dengan REST API Pandansari System. Dokumen membedakan kontrak API yang **sudah tersedia** dari kontrak keamanan **target** yang masih harus diimplementasikan oleh tim backend.

## Ringkasan status

Per 24 Oktober 2026, backend menyediakan endpoint scan dasar:

```http
POST /api/transactions/{transaction}/scans
```

Endpoint tersebut sudah:

- menggunakan Bearer Token Laravel Sanctum;
- memerlukan ability token `transaction:scan`;
- menerima nilai teks barcode melalui metode `POST`;
- memastikan transaksi dapat diubah oleh pemiliknya;
- memastikan scanner pada unit operasional aktif;
- memastikan item aktif dan terdaftar pada katalog unit transaksi;
- menambahkan satu unit barang ke keranjang tanpa mengurangi stok;
- mengurangi stok hanya ketika checkout berhasil.

Endpoint tersebut belum:

- memiliki akun atau tabel perangkat khusus;
- mengikat satu token secara permanen ke satu ESP dan satu outlet;
- menerima atau menyimpan `scan_id`;
- menjamin retry idempoten;
- menggunakan UUID/ULID publik sebagai ID transaksi di URL;
- melakukan pairing otomatis antara ESP dan keranjang POS aktif;
- mengirim pembaruan keranjang ke halaman POS secara real-time.

Tim firmware boleh menggunakan kontrak saat ini untuk demonstrasi. Jangan menganggap `scan_id` sudah melindungi dari duplikasi sampai tim backend menyatakan kontrak target sudah tersedia dan lulus pengujian.

## Istilah

| Istilah | Fungsi | Siklus hidup |
| --- | --- | --- |
| Bearer Token | Mengautentikasi pemanggil API | Dapat dicabut atau dirotasi |
| `device_id` | Mengenali satu ESP | Tetap untuk perangkat tersebut |
| `transaction_id` | Mengenali satu keranjang POS | Berubah setelah checkout |
| `scan_id` | Mengenali satu kejadian scan | Baru untuk setiap scan fisik |
| `barcode` | Mengenali SKU barang | Mengikuti barcode master item |

`transaction_id` dan `scan_id` tidak saling menggantikan. ID transaksi menentukan keranjang tujuan, sedangkan ID scan mencegah satu kejadian scan diproses dua kali akibat retry jaringan.

## Topologi jaringan

```text
Barcode scanner
      |
      | UART/TTL atau antarmuka lain yang disepakati
      v
ESP32 -- Wi-Fi --> Laravel REST API --> MySQL
                                      |
                                      v
                                  Keranjang POS
```

ESP tidak boleh memakai `127.0.0.1` sebagai alamat server. Dari sisi ESP, alamat tersebut menunjuk ke ESP sendiri. Gunakan alamat LAN komputer/server, misalnya:

```text
http://192.168.1.10:8000/api
```

Untuk demonstrasi lokal, jalankan Laravel agar mendengarkan koneksi LAN:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

ESP dan server harus berada pada jaringan yang sama, dan firewall harus mengizinkan port yang digunakan. Produksi yang melewati jaringan tidak tepercaya wajib menggunakan HTTPS dan verifikasi sertifikat. Metode `POST` tidak mengenkripsi token atau body request.

## Konfigurasi per perangkat

Setiap ESP harus memiliki konfigurasi sendiri:

```text
DEVICE_ID
API_BASE_URL
API_TOKEN
ACTIVE_TRANSACTION_ID
SCANNER_BAUD_RATE
SCANNER_RX_PIN
SCANNER_TX_PIN
```

Contoh pemetaan tiga perangkat:

| Perangkat | Unit tujuan | Token | Transaksi aktif |
| --- | --- | --- | --- |
| `ESP01` | Outlet 1 | Token khusus ESP01 | Keranjang aktif Outlet 1 |
| `ESP02` | Outlet 2 | Token khusus ESP02 | Keranjang aktif Outlet 2 |
| `ESP03` | Outlet 3 | Token khusus ESP03 | Keranjang aktif Outlet 3 |

Jangan menyimpan token asli dalam Git, dokumentasi, tangkapan layar, atau log serial. Pada tahap demonstrasi, token dapat diprovisikan secara manual. Pada tahap produksi, token harus dapat dicabut dan dirotasi oleh backend.

## Kontrak API yang tersedia sekarang

### Request

```http
POST /api/transactions/{transaction}/scans
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

Body:

```json
{
  "barcode": "8990000000004"
}
```

Aturan input saat ini:

| Field | Wajib | Aturan |
| --- | --- | --- |
| `barcode` | Ya | String, dipangkas dari spasi awal/akhir, maksimum 255 karakter |

`transaction` pada URL saat ini masih merupakan ID integer internal database. Nilainya tidak boleh dianggap permanen. Setelah transaksi selesai, firmware harus memperoleh ID keranjang yang baru sebelum mengirim scan berikutnya.

### Contoh URL LAN

```text
http://192.168.1.10:8000/api/transactions/3/scans
```

### Respons berhasil

Status: `200 OK`

```json
{
  "data": {
    "id": 3,
    "transaction_number": "TRX-20261023-000003",
    "operational_unit": {
      "id": 1,
      "code": "OU0001",
      "name": "Outlet 1",
      "type": "OUTLET"
    },
    "status": "CART",
    "subtotal": "7000.00",
    "total": "7000.00",
    "items": [
      {
        "item": {
          "item_code": "PROD-AQUA-15-L",
          "barcode": "8990000000004",
          "name": "AQUA 1,5 L"
        },
        "quantity": 1,
        "unit_price": "7000.00",
        "subtotal": "7000.00",
        "input_method": "SCANNER"
      }
    ]
  }
}
```

Memindai barcode yang sama lagi menaikkan `quantity` pada baris barang yang sama. Stok belum berubah pada tahap ini.

### Barcode kontrol checkout

Untuk outlet dengan scanner aktif, halaman POS menampilkan barcode Code 128 bernilai `PDS-PAY` di bawah kartu keranjang. Kirim nilai tersebut melalui endpoint scan yang sama untuk menjalankan checkout:

```json
{
  "barcode": "PDS-PAY"
}
```

Respons berhasil tetap memakai `200 OK`, tetapi `data.status` berubah menjadi `PAID`. Backend menjalankan layanan checkout yang sama dengan tombol **Konfirmasi Pembayaran**, termasuk pemeriksaan stok, pengurangan saldo, pencatatan mutasi `SALE`, dan penguncian transaksi selesai. Kode `PDS-PAY` dicadangkan sehingga tidak dapat disimpan sebagai barcode master item. Barcode kontrol hanya boleh dipindai setelah keranjang berisi item.

### Kode respons yang harus ditangani firmware

| Status | Arti | Tindakan firmware |
| ---: | --- | --- |
| `200` | Scan berhasil | Beri indikator sukses |
| `401` | Token tidak ada atau tidak valid | Hentikan retry; minta provisioning ulang |
| `403` | Token tidak berhak atau bukan pemilik transaksi | Hentikan retry; laporkan konfigurasi salah |
| `404` | Transaksi tidak ditemukan | Minta ID keranjang terbaru |
| `409` | Scanner unit nonaktif atau transaksi tidak dapat diubah | Hentikan retry; sinkronkan ulang status keranjang |
| `422` | Barcode kosong, tidak dikenal, item nonaktif, atau tidak tersedia di unit | Beri indikator scan ditolak |
| `429` | Terlalu banyak request | Retry dengan jeda yang lebih panjang |
| `500`–`599` | Gangguan server | Retry terbatas dengan payload yang sama |
| Nilai negatif dari `HTTPClient` | Gangguan koneksi ESP | Sambungkan ulang lalu retry terbatas |

Pada kontrak saat ini, retry request yang sebenarnya sudah diterima server dapat menaikkan kuantitas dua kali. Firmware demonstrasi sebaiknya tidak melakukan retry otomatis agresif.

## Contoh firmware untuk kontrak saat ini

Contoh ini memakai library bawaan ESP32 dan ArduinoJson. Pin, baud rate, SSID, URL, token, dan ID transaksi harus disesuaikan.

```cpp
#include <ArduinoJson.h>
#include <HTTPClient.h>
#include <WiFi.h>

const char* WIFI_SSID = "NAMA_WIFI";
const char* WIFI_PASSWORD = "PASSWORD_WIFI";
const char* API_BASE_URL = "http://192.168.1.10:8000/api";
const char* API_TOKEN = "TOKEN_HASIL_PROVISIONING";

long activeTransactionId = 3;

HardwareSerial barcodeScanner(2);

bool postCurrentScan(const String& rawBarcode)
{
    String barcode = rawBarcode;
    barcode.trim();

    if (barcode.length() == 0 || WiFi.status() != WL_CONNECTED) {
        return false;
    }

    String url = String(API_BASE_URL)
        + "/transactions/"
        + String(activeTransactionId)
        + "/scans";

    StaticJsonDocument<320> requestDocument;
    requestDocument["barcode"] = barcode;

    String payload;
    serializeJson(requestDocument, payload);

    WiFiClient client;
    HTTPClient http;
    http.setConnectTimeout(3000);
    http.setTimeout(5000);
    http.begin(client, url);
    http.addHeader("Accept", "application/json");
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + API_TOKEN);

    int statusCode = http.POST(payload);
    String responseBody = http.getString();

    Serial.printf("HTTP %d\n", statusCode);
    Serial.println(responseBody);

    http.end();

    return statusCode == 200;
}

void connectWifi()
{
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print('.');
    }

    Serial.println();
    Serial.println("Wi-Fi terhubung");
}

void setup()
{
    Serial.begin(115200);
    barcodeScanner.begin(9600, SERIAL_8N1, 16, 17);
    barcodeScanner.setTimeout(250);
    connectWifi();
}

void loop()
{
    if (barcodeScanner.available()) {
        String barcode = barcodeScanner.readStringUntil('\n');

        if (postCurrentScan(barcode)) {
            Serial.println("Scan diterima API");
        } else {
            Serial.println("Scan gagal");
        }
    }
}
```

Sebagian scanner mengakhiri data dengan `\r`, sebagian dengan `\n`, dan sebagian dengan keduanya. Tim firmware harus memeriksa keluaran perangkat yang dipilih dan membersihkan karakter akhir sebelum mengirim request.

## Kontrak keamanan target

Bagian ini adalah kontrak yang direkomendasikan, tetapi belum tersedia pada backend saat ini.

### Request target

```http
POST /api/transactions/{transaction_public_id}/scans
Authorization: Bearer {device_token}
Accept: application/json
Content-Type: application/json
```

Contoh URL dengan ID publik UUID/ULID:

```text
https://api.example.com/api/transactions/01K83M5N4F7Q2X9J6C8D/scans
```

Body:

```json
{
  "scan_id": "ESP01-00000123",
  "barcode": "8990000000004"
}
```

Aturan target:

| Field | Wajib | Aturan |
| --- | --- | --- |
| `scan_id` | Ya | String 1–64 karakter, hanya huruf, angka, `_`, dan `-` |
| `barcode` | Ya | String 1–255 karakter setelah `trim` |
| `transaction_public_id` | Ya | UUID/ULID unik untuk satu transaksi, dikirim pada URL |

Backend harus memeriksa:

1. Bearer Token valid dan memiliki ability scan.
2. Token terikat pada perangkat aktif.
3. Perangkat terikat pada unit operasional yang benar.
4. ID transaksi publik ada dan statusnya masih `CART`.
5. Unit transaksi sama dengan unit perangkat.
6. Scanner unit aktif.
7. Barcode aktif dan tersedia dalam katalog unit.
8. Kombinasi `(device_id, scan_id)` unik.
9. Penyimpanan scan dan penambahan item berlangsung atomik.

ID publik hanya mengurangi kemudahan enumerasi URL. ID tersebut tidak menggantikan Bearer Token dan pemeriksaan outlet pada backend.

### Semantik idempotensi

| Kondisi | Hasil target |
| --- | --- |
| `scan_id` baru | Tambahkan satu unit barang |
| `scan_id` sama, transaksi dan barcode sama | Kembalikan hasil pertama tanpa menambah barang |
| `scan_id` sama tetapi transaksi atau barcode berbeda | `409 Conflict` |

Database backend harus memiliki minimal:

```text
UNIQUE(transactions.public_id)
UNIQUE(device_id, scan_id)
INDEX(items.barcode)
```

### Pembentukan `scan_id` di ESP

Gunakan `device_id` tetap dan penghitung yang disimpan pada NVS/Preferences:

```text
ESP01-00000123
ESP02-00000417
ESP03-00000089
```

Contoh pembentukan ID:

```cpp
#include <Preferences.h>

Preferences preferences;
const char* DEVICE_ID = "ESP01";

String createScanId()
{
    uint32_t sequence = preferences.getUInt("scan_seq", 0) + 1;
    preferences.putUInt("scan_seq", sequence);

    char buffer[32];
    snprintf(buffer, sizeof(buffer), "%s-%08lu", DEVICE_ID, sequence);

    return String(buffer);
}

void initializeScanSequence()
{
    preferences.begin("scanner", false);
}
```

`scan_id` dibuat sekali ketika barcode fisik selesai dibaca. Apabila request timeout, firmware wajib mengirim ulang `scan_id`, barcode, dan ID transaksi yang sama. Jangan membuat `scan_id` baru pada setiap percobaan retry.

### Contoh pengiriman target dengan retry

```cpp
bool isRetryableStatus(int statusCode)
{
    return statusCode < 0
        || statusCode == 408
        || statusCode == 429
        || statusCode >= 500;
}

bool postTargetScan(
    const String& transactionPublicId,
    const String& scanId,
    const String& barcode
)
{
    String url = String(API_BASE_URL)
        + "/transactions/"
        + transactionPublicId
        + "/scans";

    StaticJsonDocument<384> requestDocument;
    requestDocument["scan_id"] = scanId;
    requestDocument["barcode"] = barcode;

    String payload;
    serializeJson(requestDocument, payload);

    const unsigned long retryDelays[] = {500, 1000, 2000};

    for (size_t attempt = 0; attempt < 3; attempt++) {
        WiFiClient client;
        HTTPClient http;
        http.setConnectTimeout(3000);
        http.setTimeout(5000);
        http.begin(client, url);
        http.addHeader("Accept", "application/json");
        http.addHeader("Content-Type", "application/json");
        http.addHeader("Authorization", String("Bearer ") + API_TOKEN);

        int statusCode = http.POST(payload);
        String responseBody = http.getString();
        http.end();

        if (statusCode == 200 || statusCode == 201) {
            return true;
        }

        if (!isRetryableStatus(statusCode)) {
            Serial.printf("Scan ditolak permanen: HTTP %d\n", statusCode);
            Serial.println(responseBody);
            return false;
        }

        delay(retryDelays[attempt]);
    }

    return false;
}
```

Program pemanggilnya harus mempertahankan ID yang sama selama retry:

```cpp
String barcode = readBarcode();
String scanId = createScanId();

postTargetScan(activeTransactionPublicId, scanId, barcode);
```

## Siklus transaksi dan pairing

ID transaksi tidak boleh ditanam permanen dalam firmware:

```text
Keranjang A (CART)
      -> checkout
Transaksi A (PAID, tidak dapat diubah)
      -> buat/pasangkan keranjang baru
Keranjang B (CART, ID baru)
```

Untuk demonstrasi, ID transaksi dapat diberikan secara manual kepada ESP. Untuk produksi, tim backend dan frontend masih harus menentukan mekanisme pairing. Pilihan yang disarankan:

1. Petugas membuka keranjang POS.
2. POS memilih perangkat yang terdaftar pada outlet yang sama.
3. Backend menyimpan pairing perangkat–transaksi.
4. ESP memperoleh ID transaksi aktif melalui endpoint konfigurasi/perangkat.
5. Setelah checkout, pairing lama berakhir dan ESP memperoleh ID baru.

Firmware harus menyediakan cara memperbarui ID transaksi tanpa melakukan flash ulang, misalnya melalui endpoint konfigurasi, captive portal lokal, atau pairing dari POS. Mekanisme final belum tersedia di repositori.

## Kebijakan retry

| Kondisi | Retry? | Ketentuan |
| --- | --- | --- |
| Wi-Fi putus | Ya | Sambungkan ulang; gunakan `scan_id` yang sama |
| Timeout koneksi/respons | Ya | Maksimum terbatas; gunakan payload yang sama |
| `408`, `429`, `5xx` | Ya | Backoff `500 ms`, `1 s`, lalu `2 s` |
| `400`, `401`, `403`, `404`, `409`, `422` | Tidak otomatis | Perlu koreksi konfigurasi atau input |

Jumlah retry dan timeout harus dapat dikonfigurasi. Jangan melakukan retry tanpa batas karena dapat menutupi kerusakan konfigurasi dan memenuhi jaringan dengan request.

## Indikator perangkat

Minimal sediakan indikator berikut melalui LED, buzzer, atau layar:

| Keadaan | Indikator yang disarankan |
| --- | --- |
| Wi-Fi terhubung | LED koneksi stabil |
| Scan sedang dikirim | Kedipan singkat |
| `200`/`201` | Bunyi/warna sukses |
| `401`/`403` | Indikator konfigurasi/token bermasalah |
| `404`/`409` | Indikator perlu pairing ulang |
| `422` | Indikator barcode ditolak |
| Timeout setelah seluruh retry | Indikator offline dan simpan status untuk operator |

Jangan menyatakan scan berhasil hanya karena scanner berhasil membaca simbol. Keberhasilan operasional ditentukan oleh respons API.

## Keamanan firmware dan jaringan

- Gunakan satu token berbeda untuk setiap ESP.
- Berikan token hanya ability yang diperlukan untuk scan.
- Jangan gunakan token Superadmin pada produksi.
- Jangan menampilkan token pada Serial Monitor.
- Jangan menyimpan token dalam repository firmware.
- Gunakan HTTPS dan validasi sertifikat pada produksi; jangan memakai mode TLS `insecure`.
- Sediakan prosedur rotasi dan pencabutan token jika perangkat hilang.
- Backend tetap wajib memeriksa perangkat, outlet, transaksi, dan status keranjang; UUID/ULID bukan pengganti otorisasi.
- Tambahkan rate limit per perangkat untuk membatasi perangkat rusak atau disalahgunakan.
- Simpan penghitung `scan_id` agar tidak kembali ke nol setelah restart.

## Dampak performa

Bearer Token, validasi `scan_id`, UUID/ULID transaksi, dan unique index hanya menambah pekerjaan backend yang kecil dibanding latensi Wi-Fi dan koneksi HTTP. Tiga ESP merupakan beban ringan selama query memiliki index dan koneksi server stabil.

Hindari membuka koneksi TLS baru secara berlebihan jika library dan server mendukung penggunaan ulang koneksi. Angka latensi aktual harus diukur pada ESP, access point, server, dan mode HTTP/HTTPS yang benar-benar digunakan; dokumentasi ini tidak menetapkan jaminan waktu respons.

## Checklist integrasi

### Kontrak saat ini

- [ ] ESP dan server berada pada jaringan yang dapat saling mengakses.
- [ ] Base URL memakai IP server, bukan `127.0.0.1`.
- [ ] Token memiliki ability `transaction:scan`.
- [ ] Pemilik token adalah pembuat keranjang yang dituju.
- [ ] Scanner pada unit transaksi aktif.
- [ ] Barcode tersedia pada master item dan katalog outlet.
- [ ] Scan pertama menambah satu unit ke keranjang.
- [ ] Scan tidak membuat `stock_movements` sebelum checkout.
- [ ] Setelah checkout, firmware tidak terus memakai transaksi lama.

### Kontrak target

- [ ] Setiap ESP memakai token dan `device_id` berbeda.
- [ ] Transaksi memakai ID publik UUID/ULID yang unik.
- [ ] `scan_id` bertahan setelah restart perangkat.
- [ ] Retry memakai `scan_id` dan payload yang sama.
- [ ] Retry scan yang sama tidak menambah kuantitas kedua kali.
- [ ] `scan_id` sama dengan barcode berbeda menghasilkan `409`.
- [ ] ESP tidak dapat mengirim scan ke transaksi outlet lain.
- [ ] Token yang dicabut menghasilkan `401`.
- [ ] Gangguan jaringan menghasilkan retry terbatas.
- [ ] Firmware dapat menerima ID transaksi baru setelah checkout.

## Pembagian tanggung jawab

| Tim | Tanggung jawab |
| --- | --- |
| IoT | Membaca barcode, membentuk `scan_id`, menyimpan sequence, mengirim request, retry terbatas, dan menampilkan indikator |
| Backend | Token perangkat, validasi, otorisasi outlet, ID transaksi publik, idempotensi, transaksi database, dan kode respons |
| Frontend/POS | Membuka keranjang, pairing perangkat, menampilkan hasil scan, checkout, dan pairing ulang |
| Infrastruktur | Jaringan, DNS/IP, HTTPS, sertifikat, firewall, logging, dan monitoring |

Perubahan kontrak API harus disepakati bersama dan diuji melalui Postman serta perangkat fisik sebelum digunakan dalam demonstrasi atau UAT.
