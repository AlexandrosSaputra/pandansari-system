# Status Implementasi

## Ringkasan

Per 23 Oktober 2026, proyek berada pada sekitar **60% dari keseluruhan target proposal capstone**. Jika IoT ESP32 dan AI dikeluarkan dari perhitungan, inti website berada pada sekitar **75%**.

Angka ini adalah estimasi penilaian fitur, bukan metrik terukur atau perhitungan jumlah file. Bobot area belum disepakati tim, sehingga angka keseluruhan tidak boleh dipakai sebagai laporan kemajuan formal. Status di bawah dinilai dari implementasi dan test otomatis yang tersedia; UAT browser resmi menunggu pelaksanaan langsung oleh pengguna.

| Area proposal | Status | Estimasi | Catatan |
| --- | --- | ---: | --- |
| Login, RBAC, pengguna, dan outlet | Tersedia | 90% | Role, permission, assignment unit, route guard, profil mandiri, penggantian kata sandi, log aktivitas pribadi, panel notifikasi aktivitas akun, penguncian setelah tiga kata sandi salah, dan pemulihan melalui OTP email superadmin tersedia. |
| Produk dan lokasi | Tersedia | 85% | Item, barcode, harga per unit, lokasi inventori, dan unit operasional tersedia. Kode Master Item dibuat otomatis oleh sistem; halaman memiliki ringkasan katalog, filter, tabel bernomor, dan pagination responsif. Superadmin dapat mengelola item per unit melalui tampilan parent–child yang dikelompokkan berdasarkan outlet atau ticketing. |
| Persediaan dan ledger | Tersedia | 80% | Saldo, stok masuk/keluar, transfer, dan riwayat mutasi tersedia. `ADJUSTMENT` dan `DAMAGED` belum tersedia. Stock opname di luar lingkup. |
| POS dan ticketing | Tersedia | 80% | POS outlet dan Ticketing memiliki menu serta ruang transaksi terpisah dengan engine keranjang dan checkout yang sama; barcode manual dan riwayat transaksi tersedia. |
| Dashboard dan laporan | Sebagian | 40% | Dashboard operasional dasar tersedia; laporan bisnis lengkap dan AI Insight belum tersedia. |
| Kualitas dan kesiapan operasional | Sebagian | 75% | Backend dan frontend memiliki pemeriksaan otomatis serta alur API inti memiliki test integrasi. UAT browser pengguna dan suite browser E2E otomatis belum tersedia. |
| IoT dan manajemen perangkat | Sebagian | 20% | Endpoint scan berbasis barcode untuk demonstrasi tersedia dan mendukung token Sanctum terbatas, tetapi registrasi perangkat, pairing, idempotensi, heartbeat, dan event real-time belum tersedia. |
| Forecasting dan rekomendasi AI | Belum tersedia | 0% | Tidak ada dataset, model, tabel hasil, atau halaman AI Insight. |

## Alur yang telah memiliki implementasi

- Per 25 Oktober 2026, frontend memakai palet light-mode putih, Cool Jade, Spring Herb, Sky Aqua, mist, dan linen melalui token terpusat. Login beserta ilustrasi 3D, navigasi, formulir, tabel, ringkasan katalog/outlet, detail outlet, dan komponen bersama telah diselaraskan. Aksen ungu redup membedakan outlet/lokasi, hijau menandai status berhasil/aktif, amber menandai perhatian, dan merah tetap untuk hapus/error. Card dashboard diberi warna menurut konteks. Build, lint, 47 pemeriksaan frontend, serta perhitungan pasangan kontras berhasil; pemeriksaan visual browser belum selesai karena akses otomatis Chrome ditolak. Rincian ada di [palet warna light-mode](palet-warna.md).
- Kode menyediakan pengelolaan pengguna, unit, item, role, permission, dan audit log untuk Superadmin. Kode unit operasional terisi otomatis setelah nama selesai dimasukkan, memakai dua huruf pertama dari nama dan empat angka; backend memvalidasi format serta keunikannya, menyediakan fallback otomatis, dan kode tidak dapat diubah dari form. Migration data menormalkan kode lama tanpa mengubah kode yang sudah valid, sedangkan seeder demo mempertahankan format yang sama saat dijalankan ulang. Status aksi utama di seluruh aplikasi ditampilkan sebagai toast di pojok kanan atas; toast dapat ditutup manual dan hilang otomatis setelah lima detik agar informasi terlihat tanpa mengganggu alur kerja.
- Halaman Outlet menyediakan tampilan parent–child berbentuk accordion untuk menambah atau mengubah harga, stok minimum, serta status item setiap outlet atau ticketing. Halaman Master Item tetap menjadi katalog global.
- Email akun Superadmin awal dapat dikonfigurasi melalui `SUPERADMIN_EMAIL`; migrasi memperbarui akun lokal lama tanpa membuat akun kedua.
- Kode mengunci login per username setelah tiga kata sandi salah. Penguncian hanya dibuka setelah reset kata sandi dengan OTP berhasil, bukan saat OTP baru diminta.
- Kode menyediakan pemulihan kata sandi untuk seluruh role melalui OTP alfanumerik yang dikirim ke alamat email pemulihan khusus.
- Setiap pengguna terautentikasi dapat melihat profil dan assignment miliknya, memperbarui identitas akun, mengganti kata sandi setelah memverifikasi kata sandi saat ini, serta melihat log aktivitas pribadinya tanpa memperoleh akses ke log pengguna lain. Tombol notifikasi pada header membuka panel kanan berisi riwayat tersebut, termasuk waktu, nominal transaksi, dan ringkasan mutasi stok sesuai metadata aktivitas.
- Kode menyediakan stok masuk/keluar Gudang dan transfer ke outlet.
- Kode menyediakan ruang POS outlet dan Ticketing yang terpisah sesuai tipe unit, dengan engine keranjang dan checkout yang sama.
- Kode menyediakan endpoint scan dasar `POST /api/transactions/{transaction}/scans`. Klien mengirim teks barcode, backend mencocokkan item aktif pada katalog unit, lalu menambahkan satu unit ke keranjang tanpa mengubah stok. Token perangkat demonstrasi dapat dibatasi dengan ability Sanctum `transaction:scan`.
- Checkout mengunci saldo stok dan menolak penjualan yang melampaui stok tersedia.
- Transfer menahan stok Gudang ketika dibuat dan memindahkan saldo setelah diterima.
- Antarmuka menyediakan riwayat transaksi dan mutasi. Backend dapat dijalankan dengan database MySQL lokal dan endpoint health merespons; alur Gudang → transfer → Outlet → POS → checkout telah dicakup test integrasi dan menunggu UAT browser pengguna.
- Seeder demo opt-in menyediakan enam akun lapangan terpisah: Superadmin, Loket, Outlet 1, Outlet 2, Outlet 3, dan Gudang. Setiap petugas unit hanya memiliki satu assignment aktif sehingga tidak dapat berpindah atau membaca unit lain; pascalogin mereka langsung diarahkan ke POS, Ticketing, atau Inventori sesuai area kerja. Seeder juga menyediakan tiga parent outlet, satu parent `Loket Ticket`, katalog 29 produk beserta harga dari data penjualan historis, satu layanan tiket, serta stok awal Gudang tanpa menyimpan kata sandi demo di repositori. Setiap kombinasi outlet–item dibuat unik, sedangkan satu master item dapat dipakai oleh lebih dari satu outlet. Seeder mengonsolidasikan parent demo lama tanpa menghapus riwayat transaksi atau stok, dan perilaku login serta isolasi lintas unit memiliki regression check.

## Kesenjangan utama terhadap proposal

1. Belum ada tabel `devices`, penerbitan dan rotasi token perangkat melalui UI, `scan_id`, retry idempoten, pairing POS, status perangkat, atau event real-time. Endpoint scan dasar yang ada masih memerlukan ID keranjang dan token Sanctum milik pembuat keranjang.
2. Tidak ada `visitor_daily`, `forecast_results`, `stock_recommendations`, layanan Python, eksperimen model, atau dashboard rekomendasi.
3. Jenis mutasi saat ini adalah `STOCK_IN`, `STOCK_OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, dan `SALE`. Proposal juga memerlukan aturan untuk stok awal, penyesuaian, serta barang rusak.
4. Dashboard belum menampilkan omzet per outlet, produk terlaris, penjualan per jam/hari, prakiraan, dan rekomendasi pengadaan sebagaimana target proposal.
5. Bukti uji API end-to-end tersedia untuk alur inti, tetapi UAT browser pengguna dan suite browser end-to-end otomatis belum tersedia.

## Catatan kesesuaian istilah

Implementasi memakai role `superadmin`, `warehouse`, dan `admin`. Untuk kebutuhan proposal, `admin` berfungsi sebagai role petugas outlet/ticketing yang terikat pada satu unit operasional. Dokumentasi dan SKPL final perlu menyatakan pemetaan ini secara eksplisit agar tidak menimbulkan ambiguitas.
