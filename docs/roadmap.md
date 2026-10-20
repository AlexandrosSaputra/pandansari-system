# Roadmap Implementasi Capstone

Roadmap ini mengutamakan integritas stok terlebih dahulu. Fitur IoT dan AI ditambahkan setelah fondasi POS dan ledger tervalidasi.

## Fase 1 - Menuntaskan domain persediaan

Target:

- Menetapkan cara mencatat stok awal, penyesuaian manual, dan barang rusak tanpa modul stock opname.
- Memastikan setiap perubahan saldo stok memiliki mutasi yang dapat ditelusuri.
- Menyediakan rekonsiliasi internal saldo `inventory_balances` terhadap mutasi stok. Rekonsiliasi ini tidak membuktikan kesesuaian dengan stok fisik.
- Menetapkan aturan transfer, pembatalan, dan penolakan transfer.

Kriteria selesai:

- Skenario stok awal, stok masuk, transfer, sale, penyesuaian, dan barang rusak lulus rekonsiliasi.
- Tidak ada perubahan saldo tanpa catatan mutasi.

## Fase 2 - Laporan dan dashboard operasional

Target:

- Laporan penjualan per outlet, produk, dan periode.
- Ringkasan omzet, jumlah transaksi, produk terlaris, stok menipis, serta penjualan per hari/jam.
- Filter periode dan outlet sesuai hak akses pengguna.

Kriteria selesai:

- Nilai laporan dapat dicocokkan dengan transaksi dan mutasi sumbernya.
- Dashboard tidak menampilkan data dari outlet yang tidak boleh diakses pengguna.

## Fase 3 - Integrasi perangkat scanner

Target:

- Tabel dan halaman manajemen perangkat.
- Token perangkat yang dapat dicabut/diputar, pengikatan token ke `device_id`, dan endpoint scan khusus.
- Unique constraint serta respons idempoten berdasarkan `scan_id`.
- Status perangkat berdasarkan heartbeat atau `last_seen_at`.
- Pengiriman hasil scan ke POS aktif secara real-time.

Kriteria selesai:

- Scan Gudang membuat pencatatan stok sesuai aturan yang disetujui.
- Scan outlet menambah produk pada keranjang outlet yang tepat tanpa mengurangi stok sebelum checkout.
- Retry dengan `scan_id` yang sama tidak membuat item atau mutasi ganda.

## Fase 4 - Data dan forecasting AI

Target:

- Menetapkan sumber data riil dan kamus data training.
- Menyusun agregasi pengunjung harian dari transaksi ticketing.
- Membuat tabel hasil prakiraan dan rekomendasi terpisah dari stok operasional.
- Menguji baseline Moving Average terhadap model pohon yang dipilih.

Kriteria selesai:

- Pemilihan model dilakukan pada validation set berurutan waktu.
- Test set terakhir tidak dipakai untuk memilih model atau hiperparameter.
- Hasil memuat MAE, RMSE, WAPE, sumber data, horizon, dan periode evaluasi.

## Fase 5 - AI Insight dan penerimaan pengguna

Target:

- Menampilkan prakiraan pengunjung, permintaan produk, dan rekomendasi pengadaan di dashboard Super Admin.
- Menetapkan safety stock, horizon kebutuhan, serta keterbatasan rekomendasi.
- Menjalankan UAT pada alur Gudang, Outlet, Loket, dan Super Admin.

Kriteria selesai:

- Rekomendasi tidak mengubah stok atau membuat pembelian otomatis.
- Hasil rekomendasi cocok dengan rumus yang disepakati.
- Temuan UAT terdokumentasi dan memiliki keputusan tindak lanjut.

## Urutan kerja yang disarankan

```text
Ledger & rekonsiliasi
        -> laporan/dashboard operasional
        -> perangkat & scan API
        -> event real-time
        -> dataset dan eksperimen AI
        -> AI Insight dan UAT
```

Stock opname berada di luar lingkup website. Jangan memulai integrasi ESP32 sebelum POS dan ledger lulus pengujian rekonsiliasi internal. Jangan menampilkan rekomendasi AI sebagai dasar pembelian operasional jika hasilnya hanya berasal dari data simulasi.
