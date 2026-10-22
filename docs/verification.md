# Rencana dan Hasil Verifikasi

## Bukti verifikasi saat ini

Pada pemeriksaan 21 Oktober 2026:

- Frontend lulus `npm run lint`.
- Frontend lulus `npm test` dengan 32 pemeriksaan SSR, termasuk regresi pergantian assignment aktif.
- Frontend lulus `npm run build`.
- Build frontend menampilkan peringatan ukuran chunk pada bundle utama/login; ini bukan kegagalan build, tetapi perlu diperhatikan sebelum deployment produksi.
- Koneksi MySQL pengembangan berhasil dan seluruh 20 migrasi berstatus `Ran`.
- Endpoint backend `/api/health` merespons HTTP 200 ketika server dijalankan.
- Backend lulus 116 test dengan 1.302 assertion menggunakan SQLite in-memory. PHP CLI lokal belum mengaktifkan `pdo_sqlite` secara permanen, sehingga verifikasi dijalankan dengan `php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit`.
- Test integrasi `CoreWorkflowApiTest` merangkai stok masuk Gudang, reservasi transfer, penerimaan outlet, input barcode ke keranjang, checkout, rekonsiliasi saldo terhadap ledger, riwayat transaksi, serta pembatasan role.
- UAT browser manual lulus untuk Superadmin, Gudang, Admin Outlet, dan Admin Ticketing. Alur Gudang → reservasi transfer → penerimaan Outlet → barcode → checkout → riwayat/mutasi berhasil dijalankan pada data demo lokal.

Pemeriksaan browser ini bersifat UAT manual, bukan suite browser end-to-end otomatis. Tidak ada bukti pada pemeriksaan ini bahwa perangkat ESP32, WebSocket, stock opname, atau forecasting berjalan karena semuanya berada di luar milestone ini.

## Hasil UAT browser

| Role | Skenario | Hasil | Bukti | Masalah | Rekomendasi |
| --- | --- | --- | --- | --- | --- |
| Semua | Login, pemilihan ruang kerja, dan logout | Lulus | Login setiap akun mengarah ke dashboard/selector yang benar; logout kembali ke `/login`. | Tidak ada. | Pertahankan test session dan route guard. |
| Semua | Kredensial salah | Lulus | Form login menampilkan penolakan yang dapat dipahami dan tidak membuat sesi. | Tidak ada. | Pertahankan pesan tanpa membocorkan detail akun. |
| Gudang | Menu dan halaman berbasis role | Lulus setelah perbaikan | Menu POS/pengguna tidak tampil; akses langsung `/users` menghasilkan halaman 403. | Permission baca unit sebelumnya tidak diberikan sehingga form transfer tidak memiliki pilihan tujuan. | Permission `operational-unit.view` kini diberikan hanya untuk kebutuhan baca; operasi tulis tetap 403. |
| Gudang | Stok masuk | Lulus | Air Mineral Demo naik dari 30 menjadi 35 dan movement `STOCK_IN` tercatat dengan catatan UAT. | Tidak ada. | Gunakan catatan yang jelas pada demo. |
| Gudang | Transfer dan reservasi | Lulus | Transfer `TRF-20261020-000001` dibuat untuk 8 air mineral dan 3 makanan ringan; sebelum diterima, Gudang menunjukkan stok fisik 35, reservasi 8, tersedia 27 untuk air mineral. | Tidak ada. | Tunjukkan tiga kolom saldo saat demo agar konsep reservasi terlihat. |
| Gudang | Transfer melebihi stok | Lulus | Input 999 ditolak oleh batas maksimum berdasarkan stok tersedia. | Tidak ada. | Backend tetap menjadi pengaman terakhir untuk kondisi balapan. |
| Admin Outlet | Penerimaan transfer | Lulus | Status berubah dari `Menunggu` menjadi `Diterima`; tombol respons hilang setelah diproses; saldo Outlet menjadi 8 air mineral dan 3 makanan ringan. | Tidak ada. | Gunakan transfer yang sama sebagai narasi demo. |
| Admin Outlet | Barcode tidak ditemukan | Lulus dengan catatan | Barcode `0000000000000` menampilkan penolakan item tidak ditemukan dan keranjang tetap kosong. | Pesan utama backend masih berbahasa Inggris dan tampil bersama penjelasan UI Indonesia. | Lokalisasi pesan API dapat menjadi peningkatan terpisah; respons saat ini tetap dapat dipahami. |
| Admin Outlet | Barcode valid dan checkout | Lulus | Barcode `8990000000001` masuk ke keranjang; transaksi `TRX-20261021-000001` dibayar senilai Rp24.000 untuk 3 item. | Tidak ada. | Jangan menambahkan metode pembayaran sebelum keputusan bisnis dibuat. |
| Admin Outlet | Checkout kosong/tidak valid dan stok kurang | Lulus | Tombol checkout kosong nonaktif; kuantitas 9 ditolak server dengan konflik stok; kuantitas 3 berhasil. | Pesan konflik utama masih berbahasa Inggris, tetapi penjelasan pemulihan berbahasa Indonesia tersedia. | Pertahankan keranjang saat konflik agar pengguna dapat memperbaiki kuantitas. |
| Admin Outlet | Riwayat, saldo, dan movement | Lulus | Riwayat menampilkan transaksi Rp24.000; stok air mineral turun 8 → 5; movement `SALE` keluar 3 tercatat. | Tidak ada. | Pertahankan rekonsiliasi otomatis ledger terhadap balance. |
| Admin Ticketing | Katalog layanan dan checkout | Lulus | Assignment Ticketing menampilkan layanan tanpa kebutuhan stok; `TRX-20261021-000002` dibayar Rp15.000 dengan jumlah pengunjung 1. | Tidak ada. | Metode pembayaran tetap keputusan bisnis terbuka. |
| Admin multi-unit | Pergantian assignment aktif | Lulus setelah perbaikan | Setelah Outlet → Ticketing pada halaman movement yang sama, data Outlet langsung hilang dan hasil Ticketing dimuat. | Sebelumnya query aktif tetap menampilkan cache assignment lama sampai navigasi. | Cache privat kini di-reset dan query layar aktif langsung di-refetch; regression check ditambahkan. |
| Superadmin | Dashboard dan master data | Lulus | Dashboard menampilkan 2 transaksi dibayar; halaman memuat 3 pengguna, 2 unit operasional, dan 3 item demo; menu RBAC/log tersedia. | Laporan bisnis lengkap belum tersedia. | Gunakan dashboard operasional yang ada; jangan mendemokan laporan yang belum diimplementasikan. |

## Data demonstrasi

Database lokal awal belum memadai karena hanya memiliki akun Superadmin dan lokasi Gudang. `DemoDataSeeder` disediakan secara opt-in dan idempoten untuk menambahkan:

- akun Gudang serta Admin yang memiliki assignment Outlet dan Ticketing;
- unit `OUT-DEMO` dan `TKT-DEMO` beserta lokasi inventori;
- dua produk ber-barcode dan satu layanan ticketing;
- harga per unit serta stok Gudang yang cukup untuk transfer dan checkout.

Kata sandi wajib diberikan lewat `DEMO_USER_PASSWORD`, tidak disimpan di Git, dan seeder tidak dipanggil oleh `DatabaseSeeder`. Menjalankan ulang seeder tidak menggandakan master data dan hanya menaikkan saldo Gudang sampai target demo bila masih kurang.

Keputusan bisnis yang tetap terbuka adalah metode pembayaran serta aturan movement `ADJUSTMENT`/`DAMAGED`. Seeder dan UAT tidak menetapkan keduanya.

## Skenario wajib backend

| Area | Skenario minimum | Hasil yang diharapkan |
| --- | --- | --- |
| Hak akses | Kasir outlet A membuka data outlet B | Ditolak oleh backend. |
| Checkout | Menjual produk dengan stok cukup | Transaksi `PAID`, stok berkurang, dan satu mutasi `SALE` tercatat. |
| Konflik stok | Dua checkout memakai stok terakhir | Hanya satu checkout berhasil; yang lain menerima konflik stok. |
| Transfer | Gudang membuat lalu outlet menerima transfer | Stok Gudang turun, stok outlet naik, dan pasangan mutasi transfer tercatat. |
| Penolakan transfer | Outlet menolak transfer | Reservasi Gudang dilepas tanpa memindahkan stok. |
| Rekonsiliasi internal | Menghitung saldo dari semua mutasi | Sama dengan saldo aplikasi pada setiap produk dan lokasi; tidak menguji stok fisik. |

## Skenario perangkat yang direncanakan

| Area | Skenario minimum | Hasil yang diharapkan |
| --- | --- | --- |
| Autentikasi perangkat | Token salah atau perangkat nonaktif | Request ditolak. |
| Idempotensi | `scan_id` yang sama dikirim ulang | Respons konsisten tanpa item atau mutasi baru. |
| Routing outlet | Scanner Baso mengirim barcode | Hanya keranjang POS Baso yang menerima item. |
| Jaringan | Wi-Fi terputus lalu tersambung | Perangkat retry sesuai kebijakan tanpa duplikasi. |
| Status perangkat | Heartbeat berhenti | Dashboard menandai perangkat offline setelah ambang waktu. |

## Skenario AI yang direncanakan

- Dataset mencatat sumber data, periode, produk, outlet, dan waktu agregasi.
- Pemisahan train, validation, dan test mempertahankan urutan waktu.
- Model baseline dan kandidat dievaluasi pada horizon yang sama.
- WAPE, MAE, dan RMSE dihitung dengan definisi yang konsisten serta level agregasi yang jelas.
- Bila memakai data simulasi, antarmuka dan laporan memberi label bahwa hasil tidak merepresentasikan akurasi operasional mitra.
