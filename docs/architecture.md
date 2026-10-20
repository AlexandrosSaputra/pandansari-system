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

Komponen ESP32 dan forecasting belum tersedia di repositori. Ketika dibangun, keduanya harus menambah integrasi di atas tanpa memindahkan aturan harga, stok, atau transaksi dari Laravel.

## Keputusan desain yang masih diperlukan

- Pilihan scanner USB HID atau UART/TTL serta kecocokannya dengan jenis ESP32.
- Format dan masa berlaku token perangkat.
- Penyimpanan unik untuk `scan_id` dan respons idempoten terhadap retry perangkat.
- Mekanisme WebSocket/broadcasting untuk mengirim hasil scan ke POS.
- Sumber dan kualitas data riil untuk forecasting.
- Horizon rekomendasi, lead time pemasok, dan safety stock. Stock opname berada di luar lingkup website.
