# Rencana dan Hasil Verifikasi

## Bukti verifikasi saat ini

Pada pemeriksaan otomatis 23 Oktober 2026:

- Frontend lulus `npm run lint`.
- Frontend lulus `npm test` dengan 34 pemeriksaan SSR, termasuk regresi instruksi pemulihan akun terkunci, halaman pemulihan kata sandi, dan pergantian assignment aktif.
- Frontend lulus `npm run build`.
- Build frontend menampilkan peringatan ukuran chunk pada bundle utama/login; ini bukan kegagalan build, tetapi perlu diperhatikan sebelum deployment produksi.
- Koneksi MySQL pengembangan berhasil dan seluruh 20 migrasi berstatus `Ran`.
- Endpoint backend `/api/health` merespons HTTP 200 ketika server dijalankan.
- Backend lulus 126 test dengan 1.368 assertion pada database MySQL khusus pengujian.
- Autentikasi dan pemulihan kata sandi lulus 26 test dengan 168 assertion. Cakupan mencakup peringatan sisa percobaan, penguncian pada kesalahan ketiga, OTP request yang tidak langsung membuka akun, pembukaan kunci setelah reset berhasil, dan penghapusan hitungan gagal setelah login sukses.
- Fitur lupa password lulus 7 test dengan 40 assertion pada MySQL testing: penerima email khusus, respons username generik, OTP satu kali pakai, reset akun superadmin, audit log, kedaluwarsa 10 menit, batas tiga percobaan, dan jeda kirim ulang 60 detik.
- Test integrasi `CoreWorkflowApiTest` merangkai stok masuk Gudang, reservasi transfer, penerimaan outlet, input barcode ke keranjang, checkout, rekonsiliasi saldo terhadap ledger, riwayat transaksi, serta pembatasan role.
- Endpoint scan eksternal memiliki pemeriksaan feature untuk Bearer token ber-ability `transaction:scan`, pencocokan barcode pada katalog unit, kepemilikan keranjang, status scanner unit, validasi input, dan jaminan bahwa scan belum membuat mutasi stok.
- UAT browser resmi belum dicatat pada dokumen ini dan akan dilakukan langsung oleh pengguna.

Tidak ada klaim UAT dari pemeriksaan otomatis. Firmware/perangkat ESP32, pairing perangkat, idempotensi retry, WebSocket, stock opname, dan forecasting tetap berada di luar milestone ini.

## Status UAT browser

| Role | Skenario | Hasil | Bukti | Masalah | Rekomendasi |
| --- | --- | --- | --- | --- | --- |
| Superadmin, Gudang, Admin Outlet/Ticketing | Seluruh skenario browser milestone | Menunggu UAT pengguna | Belum ada bukti UAT resmi yang dicatat. | Belum dinilai. | Isi tabel ini dari hasil UAT pengguna; jangan menyimpulkan kelulusan dari pemeriksaan pengembangan. |

## Data demonstrasi

Database lokal awal belum memadai karena hanya memiliki akun Superadmin dan lokasi Gudang. `DemoDataSeeder` disediakan secara opt-in dan idempoten untuk menambahkan:

- akun Gudang serta Admin yang memiliki assignment Outlet dan Ticketing;
- unit Outlet dan Ticketing dengan kode otomatis berformat dua huruf dan empat angka beserta lokasi inventori;
- dua produk ber-barcode dan satu layanan ticketing;
- harga per unit serta stok Gudang yang cukup untuk transfer dan checkout.

Kata sandi wajib diberikan lewat `DEMO_USER_PASSWORD`, tidak disimpan di Git, dan seeder tidak dipanggil oleh `DatabaseSeeder`. Menjalankan ulang seeder tidak menggandakan master data dan hanya menaikkan saldo Gudang sampai target demo bila masih kurang.

Keputusan bisnis yang tetap terbuka adalah metode pembayaran serta aturan movement `ADJUSTMENT`/`DAMAGED`. Seeder tidak menetapkan keduanya.

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
