# Keputusan dan Pertanyaan Terbuka

Catat keputusan teknis di sini ketika tim menyepakatinya. Jangan mengubah usulan menjadi aturan final hanya karena sudah tertulis di roadmap. Cantumkan tanggal, penanggung jawab, keputusan, dan dampak pada kode/test ketika suatu butir ditutup.

## Sudah disepakati

| Keputusan | Status | Dampak |
| --- | --- | --- |
| Dokumentasi dalam `docs/` ditujukan untuk tim pengembang. | Final, 21 Oktober 2026 | Fokus pada setup, kontrak alur, keputusan, dan verifikasi teknis; bukan format SKPL/DPPL formal. |
| Stock opname tidak menjadi fitur website. | Final, 21 Oktober 2026 | Tidak ada modul hitung fisik. Rekonsiliasi ledger hanya memeriksa konsistensi internal sistem. |
| Dataset forecasting memakai histori penjualan riil yang sudah dirapikan untuk capstone. | Final, 27 Oktober 2026 | Importer memakai 6.032 baris mingguan dari 5 September 2022 sampai 30 Agustus 2026 dan memetakan tiga kelompok penjualan ke Outlet 1–3. |
| Angka forecast dihitung deterministik; Ollama/OpenAI hanya menjelaskan hasil. | Final, 27 Oktober 2026 | Kegagalan provider tidak menggagalkan forecast dan rekomendasi tidak mengubah stok otomatis. |

## Perlu keputusan sebelum implementasi

| Butir | Yang harus diputuskan | Bergantung pada |
| --- | --- | --- |
| Stok awal dan koreksi | Cara migrasi stok awal serta siapa yang berwenang membuat `ADJUSTMENT`/`DAMAGED` tanpa stock opname. | Domain persediaan dan test rekonsiliasi |
| Metode pembayaran | Jenis pembayaran yang dicatat dan apakah bukti/nomor referensi diperlukan. | Checkout, laporan, UAT |
| Scanner | USB HID atau UART/TTL, tipe controller ESP32, jumlah dan lokasi perangkat. | Firmware, endpoint scan, pengadaan |
| Scan Gudang | Apakah satu scan langsung menambah satu unit atau masuk daftar penerimaan yang harus dikonfirmasi. | Desain API dan ledger `STOCK_IN` |
| Scan outlet | Cara memilih keranjang POS aktif bila ada lebih dari satu kasir pada satu outlet. | Idempotensi dan event real-time |
| Rekomendasi stok lanjutan | Lead time pemasok, service level, ukuran kemasan, dan apakah pengadaan dikonsolidasikan di Gudang. MVP menghitung per outlet dan memakai `minimum_stock`. | Kalibrasi rumus dan proses pengadaan |

Jika data historis yang memadai tidak tersedia, eksperimen pada data simulasi hanya membuktikan pipeline dan harus diberi label demikian pada hasil serta UI.
