# Rencana Verifikasi

## Bukti verifikasi saat ini

Pada pemeriksaan 21 Oktober 2026:

- Frontend lulus `npm run lint`.
- Frontend lulus `npm test` dengan 31 pemeriksaan SSR.
- Frontend lulus `npm run build`.
- Build frontend menampilkan peringatan ukuran chunk pada bundle utama/login; ini bukan kegagalan build, tetapi perlu diperhatikan sebelum deployment produksi.
- Test backend belum dapat dijalankan karena file `backend/vendor/autoload.php` tidak tersedia pada lingkungan pemeriksaan.

Pemeriksaan frontend saat ini belum menggantikan pengujian browser end-to-end. Tidak ada bukti pada pemeriksaan ini bahwa perangkat ESP32, WebSocket, atau forecasting berjalan karena ketiganya belum tersedia di repositori.

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
