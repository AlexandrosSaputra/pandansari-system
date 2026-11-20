# Rencana dan Hasil Verifikasi

## Bukti verifikasi saat ini

Pada pemeriksaan otomatis 27 Oktober 2026:

- Frontend lulus `npm run lint`.
- Suite frontend memiliki satu kegagalan lama pada assertion auto-expand submenu POS dari route `/pos/:unitId`; lint dan build untuk perubahan AI Insight tetap lulus. Klaim 53 pemeriksaan lulus sebelumnya tidak lagi dipakai sebagai bukti saat ini sampai assertion tersebut diselaraskan dengan perilaku Sidebar.
- Frontend lulus `npm run build`.
- Build frontend menampilkan peringatan ukuran chunk pada bundle utama/login; ini bukan kegagalan build, tetapi perlu diperhatikan sebelum deployment produksi.
- Koneksi MySQL pengembangan berhasil dan seluruh 20 migrasi berstatus `Ran`.
- Endpoint backend `/api/health` merespons HTTP 200 ketika server dijalankan.
- Sebelum penambahan forecasting, backend pernah lulus 126 test dengan 1.368 assertion pada database MySQL khusus pengujian. Angka historis ini tidak dianggap sebagai hasil suite terbaru.
- Autentikasi dan pemulihan kata sandi lulus 26 test dengan 168 assertion. Cakupan mencakup peringatan sisa percobaan, penguncian pada kesalahan ketiga, OTP request yang tidak langsung membuka akun, pembukaan kunci setelah reset berhasil, dan penghapusan hitungan gagal setelah login sukses.
- Fitur lupa password lulus 7 test dengan 40 assertion pada MySQL testing: penerima email khusus, respons username generik, OTP satu kali pakai, reset akun superadmin, audit log, kedaluwarsa 10 menit, batas tiga percobaan, dan jeda kirim ulang 60 detik.
- Test integrasi `CoreWorkflowApiTest` merangkai stok masuk Gudang, reservasi transfer, penerimaan outlet, input barcode ke keranjang, checkout, rekonsiliasi saldo terhadap ledger, riwayat transaksi, serta pembatasan role.
- Endpoint scan eksternal memiliki pemeriksaan feature untuk Bearer token ber-ability `transaction:scan`, pencocokan barcode pada katalog unit, kepemilikan keranjang, status scanner unit, validasi input, dan jaminan bahwa scan item belum membuat mutasi stok. Barcode kontrol `PDS-PAY` juga memiliki pemeriksaan bahwa scan menjalankan layanan checkout, mengubah transaksi menjadi `PAID`, serta mencatat pelaku pembayaran dan log aktivitas.
- UAT browser resmi belum dicatat pada dokumen ini dan akan dilakukan langsung oleh pengguna.

Tidak ada klaim UAT dari pemeriksaan otomatis. Firmware/perangkat ESP32, pairing perangkat, idempotensi retry, WebSocket, stock opname, deployment/monitoring AI service, dan UAT prediksi dengan layanan nyata tetap berada di luar milestone ini. Seleksi kandidat, artefak Extra Trees, endpoint inference, serta adapter Laravel opt-in sudah tersedia.

Verifikasi terfokus terbaru pada MySQL testing lulus 17 test dengan 105 assertion. Cakupannya meliputi importer dataset 6.032 baris yang idempoten, safety stock, evaluasi kandidat deret waktu, proyeksi rekursif, kesegaran data, adapter structured output OpenAI/Ollama, pemakaian prediksi dan metrik Extra Trees setelah aktivasi eksplisit, fallback lokal setelah satu kegagalan layanan, normalisasi batas minggu ke zona waktu operasional, serta bukti bahwa pembuatan forecast tidak mengubah saldo atau mutasi stok. Build dan lint frontend dari tahap sebelumnya tetap berhasil.

Sinkronisasi histori transaksi lulus 1 test integrasi dengan 19 assertion pada MySQL testing. Test mencakup agregasi transaksi `PAID`, pengabaian `CART`, 29 seri selama tiga minggu, observasi nol, metadata sumber, idempotensi, deteksi satu observasi yang dihapus melalui `--check-only`, perbaikan otomatis, dan registrasi jadwal harian. Tidak ada migrasi baru dan sinkronisasi tidak mengubah transaksi maupun ledger stok.

Layanan Python lulus 8 test. Selain evaluasi tiga kandidat pohon dan perlindungan test set, test mencakup rumus `seasonal_average_3`, kesamaan periode dan jumlah observasi keempat baseline, penandaan model yang belum eligible pada validation, penolakan training bila validation gate atau test confirmation gagal, serialisasi artefak terkompresi, serta inference rekursif. Extra Trees terpilih dengan validation WAPE 21,0014% dibanding baseline 22,9476%. Pada test yang sama untuk empat baseline, `seasonal_52` menjadi referensi terbaik dengan WAPE 23,7271%, sedangkan Extra Trees menghasilkan 17,7555%; perbaikan relatif 25,1676% dan MAE juga lebih baik. Artefak schema v3 `extra_trees_v1` dilatih pada 4.524 feature row. Endpoint model/inference sebelumnya telah merespons HTTP 200; hasil ini merupakan verifikasi pengembangan, bukan UAT deployment.

Suite backend penuh terbaru menjalankan 153 test: 144 lulus dan 9 gagal. Kegagalan berada pada test lama di luar modul forecasting: satu assertion mengasumsikan nomor auto-increment item tertentu, sedangkan test keranjang/checkout/scan menambahkan produk tanpa saldo yang cukup lalu mengharapkan HTTP 200 walaupun aturan backend saat ini menolak stok tidak cukup dengan HTTP 409. Aturan stok tidak dilonggarkan untuk membuat test tersebut lulus; fixture dan assertion test lama perlu diselaraskan secara terpisah.

## Status UAT browser

| Role | Skenario | Hasil | Bukti | Masalah | Rekomendasi |
| --- | --- | --- | --- | --- | --- |
| Superadmin, Gudang, Admin Outlet/Ticketing | Seluruh skenario browser milestone | Menunggu UAT pengguna | Belum ada bukti UAT resmi yang dicatat. | Belum dinilai. | Isi tabel ini dari hasil UAT pengguna; jangan menyimpulkan kelulusan dari pemeriksaan pengembangan. |

## Data demonstrasi

Database lokal awal belum memadai karena hanya memiliki akun Superadmin dan lokasi Gudang. `DemoDataSeeder` disediakan secara opt-in dan idempoten untuk menambahkan:

- akun terpisah untuk Gudang, Loket, Outlet 1, Outlet 2, dan Outlet 3, masing-masing dengan tepat satu assignment sesuai area kerjanya;
- unit Outlet dan Ticketing dengan kode otomatis berformat dua huruf dan empat angka beserta lokasi inventori;
- 29 produk dan satu layanan ticketing;
- harga per unit serta stok Gudang yang cukup untuk transfer dan checkout.

Kata sandi akun operasional wajib diberikan lewat `DEMO_USER_PASSWORD`, tidak disimpan di Git, dan seeder tidak dipanggil oleh `DatabaseSeeder`. Menjalankan ulang seeder tidak menggandakan master data dan hanya menaikkan saldo Gudang sampai target demo bila masih kurang. Regression test memverifikasi bahwa keenam akun dapat login langsung ke konteksnya dan akun Outlet 1 ditolak saat mencoba membaca inventori atau memakai assignment milik Outlet 2.

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

## Skenario AI

- Dataset mencatat sumber data, periode, produk, outlet, dan waktu agregasi.
- Pemisahan train, validation, dan test mempertahankan urutan waktu.
- Model baseline dan kandidat dievaluasi pada horizon yang sama.
- WAPE, MAE, dan RMSE dihitung dengan definisi yang konsisten serta level agregasi yang jelas.
- Data yang digunakan merupakan histori penjualan riil yang sudah dirapikan untuk capstone; keterbatasan agregasi mingguan, harga konstan, dan tidak adanya observasi penjualan nol tetap harus dijelaskan.
- Pengujian berikutnya menjalankan UAT dengan AI service yang dideploy, memantau latensi/fallback, dan membandingkan prediksi berkala terhadap penjualan aktual baru tanpa memilih ulang model memakai test set lama.
