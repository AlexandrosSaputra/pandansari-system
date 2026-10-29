# Arsitektur dan Alur Data

## Implementasi saat ini

Proyek menggunakan arsitektur web terpisah:

```text
React + TypeScript + Vite
        |
        | HTTP, cookie session, Sanctum CSRF
        v
Laravel REST API
        |
        v
Database relasional
```

Frontend menangani tampilan, formulir, cache query, dan guard rute. Laravel merupakan otoritas untuk autentikasi, hak akses, harga, validasi stok, transaksi, dan pencatatan audit.

## Modul yang sudah tersedia

| Area | Komponen utama |
| --- | --- |
| Akses | Sanctum session, login/logout, role, permission, dan pembatasan unit operasional |
| Master data | Item, barcode, unit operasional, item per unit, pengguna, role, dan permission |
| Inventori | Lokasi inventori, saldo stok, stok masuk/keluar gudang, mutasi, dan transfer |
| POS | Keranjang, input item manual atau barcode, checkout, riwayat transaksi, dan ticketing |
| Audit | Activity log serta riwayat mutasi stok |

## Invarian domain yang harus dipertahankan

1. Saldo stok tidak boleh menjadi negatif.
2. Checkout produk harus memperbarui transaksi, saldo stok, dan mutasi `SALE` dalam satu transaksi database.
3. Transfer yang masih menunggu hanya mereservasi stok Gudang. Stok baru berpindah ketika transfer diterima.
4. Penerimaan transfer harus menghasilkan pasangan mutasi `TRANSFER_OUT` di Gudang dan `TRANSFER_IN` di outlet.
5. Transaksi dengan status `PAID` tidak boleh diubah.
6. Role outlet hanya boleh melakukan transaksi pada outlet penugasannya.

## Model data inti saat ini

```text
users / roles / permissions
        |
operational_units ---- unit_items ---- items
        |                                  |
inventory_locations ---- inventory_balances|
        |                                  |
        +----------- stock_movements ------+

stock_transfers ---- stock_transfer_items
transactions ---- transaction_items
```

`inventory_balances` menyimpan saldo yang digunakan aplikasi pada saat transaksi, sedangkan `stock_movements` menyimpan jejak perubahan stok. Keduanya harus selalu konsisten.

## Arsitektur target capstone

```text
ESP32 + barcode scanner
        |
        | device token, device_id, scan_id
        v
Laravel scan API ---- event real-time ---- POS aktif
        |
        +---- database operasional ---- layanan forecasting Python
                                             |
                                             v
                                  forecast_results / stock_recommendations
                                             |
                                             v
                                  Dashboard Super Admin
```

Komponen ESP32 penuh belum tersedia. Forecasting operasional memakai tabel Laravel `historical_sales`, `forecast_runs`, dan `forecast_results`. CSV menyediakan histori awal; setelah batas impor, scheduler mengagregasi transaksi `PAID` menjadi observasi mingguan idempoten dan mengisi nol untuk produk tanpa penjualan. Mode pemeriksaan mendeteksi observasi atau minggu yang hilang. `ai-service/` membandingkan Random Forest, Extra Trees, dan Gradient Boosting dengan split train–validation–test. Validation gate memakai baseline yang memiliki histori cukup; setelah lolos, konfirmasi test terkunci membandingkan kandidat pohon terhadap `lag_1`, rata-rata bergerak 4 minggu, musiman 52 minggu, dan rata-rata musiman 3 tahun pada periode yang sama. Artefak hanya dapat dibuat bila kedua gate lulus. Extra Trees memenuhi syarat tersebut, dilatih ulang pada seluruh histori, lalu disimpan sebagai `extra_trees_v1`. Endpoint inference menerima histori mingguan per outlet/SKU dan menghasilkan proyeksi rekursif. Adapter Laravel bersifat opt-in dan otomatis kembali ke kandidat deret waktu lokal bila layanan gagal; provider, model, dan jumlah fallback dicatat dalam hasil run. Harga, stok, transaksi, safety stock, pembulatan restock, dan seluruh aturan domain tetap dihitung Laravel. OpenAI atau Ollama hanya menghasilkan penjelasan terstruktur atas angka yang sudah dihitung sistem.

## Keputusan desain yang masih diperlukan

- Pilihan scanner USB HID atau UART/TTL serta kecocokannya dengan jenis ESP32.
- Format dan masa berlaku token perangkat.
- Penyimpanan unik untuk `scan_id` dan respons idempoten terhadap retry perangkat.
- Mekanisme WebSocket/broadcasting untuk mengirim hasil scan ke POS.
- Lead time pemasok, service level, ukuran kemasan, dan kalibrasi safety stock. MVP memakai `minimum_stock` katalog unit sebagai safety stock.
- Kandidat model lanjutan serta fitur pengunjung, hari libur, promosi, dan kejadian kehabisan stok. Stock opname berada di luar lingkup website.
