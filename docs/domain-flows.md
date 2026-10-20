# Kontrak Alur Inti

Dokumen ini merangkum perilaku yang sudah tampak pada kode agar perubahan frontend dan backend memakai asumsi yang sama. Rujukan utama tetap kode, terutama service dan test terkait.

## Istilah dan hak akses

| Istilah proposal | Nama di kode | Batas utama |
| --- | --- | --- |
| Super Admin | `superadmin` | Dapat memilih unit operasional dan mengelola modul pusat sesuai permission. |
| Stocking/Gudang | `warehouse` | Mengelola stok Gudang dan membuat transfer; tidak menjalankan POS. |
| Petugas Outlet/Loket | `admin` | Terikat pada satu unit operasional aktif saat menggunakan POS. |

Unit operasional bertipe `OUTLET` atau `TICKETING`. Role `admin` adalah nama teknis di kode; pada antarmuka dan dokumen bisnis, jelaskan sebagai petugas outlet/loket agar tidak tertukar dengan Super Admin. Permission saja tidak cukup untuk melewati pembatasan role dan unit pada backend.

## Penjualan

```text
Pilih unit -> buka/lanjutkan CART -> tambah/ubah/hapus item
           -> checkout -> PAID + mutasi SALE untuk produk
```

- Keranjang menerima produk melalui pemilihan manual atau barcode yang dikirim dari halaman POS. Input barcode ini belum terhubung ke perangkat ESP32.
- Harga item dan total dihitung di backend. `visitor_count` dihitung dari `quantity × visitor_factor` pada item layanan, bukan dari penghitung pengunjung terpisah.
- Stok produk tidak berkurang ketika item masuk keranjang. Pada checkout, backend memeriksa stok tersedia, mengubah transaksi menjadi `PAID`, mengurangi saldo outlet, dan menulis mutasi `SALE` dalam satu transaksi database.
- Transaksi `PAID` tidak dapat diedit. Status transaksi yang tersedia saat ini adalah `CART` dan `PAID`; refund/return belum tersedia.
- Role `admin` bertransaksi pada unit aktifnya. Superadmin harus memilih unit. Gudang tidak dapat bertransaksi walaupun permission transaksi dipetakan.

## Stok Gudang dan transfer

```text
STOCK_IN/OUT Gudang -> saldo dan mutasi
Transfer dibuat -> PENDING + stok Gudang direservasi
Transfer diterima -> ACCEPTED + saldo berpindah + TRANSFER_OUT/IN
Transfer ditolak -> REJECTED + reservasi dilepas
```

- `inventory_balances` menyimpan stok fisik tercatat dan jumlah tereservasi untuk menghitung stok tersedia. Perubahan `quantity_on_hand` yang sudah diimplementasikan menulis `stock_movements` pada transaksi database yang sama; reservasi transfer `PENDING` belum merupakan mutasi stok.
- Transfer `PENDING` belum mengurangi stok fisik Gudang atau menambah stok outlet. Jumlah tersebut menjadi stok tereservasi di Gudang.
- Penerimaan transfer menghasilkan `TRANSFER_OUT` dan `TRANSFER_IN` yang merujuk transfer yang sama.
- Jenis mutasi saat ini: `STOCK_IN`, `STOCK_OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, dan `SALE`. Stok awal, `ADJUSTMENT`, dan `DAMAGED` masih perlu desain tersendiri. Stock opname tidak masuk lingkup website.
- Rekonsiliasi dari ledger hanya menguji konsistensi catatan sistem. Tanpa stock opname, website tidak mengklaim kesesuaian saldo dengan stok fisik.

## Batas integrasi berikutnya

Scanner ESP32 kelak mengirim identitas perangkat, barcode, dan pengenal unik scan ke endpoint khusus. Scan outlet harus masuk ke keranjang outlet yang benar dan tidak mengurangi stok sebelum checkout. Retry dengan pengenal yang sama tidak boleh menambah item dua kali. Scan Gudang harus mengikuti alur stok masuk yang disepakati. Kontrak request, respons, dan autentikasi perangkat masih terbuka; endpoint tersebut belum ada pada `backend/routes/api.php`.

Hasil forecasting kelak disimpan terpisah dari transaksi dan saldo stok. Rekomendasi hanya tampil untuk Superadmin dan tidak membuat pembelian atau perubahan stok otomatis. Sumber data, horizon, dan perhitungan safety stock masih harus diputuskan.
