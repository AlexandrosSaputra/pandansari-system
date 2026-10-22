# Status Implementasi

## Ringkasan

Per 21 Oktober 2026, proyek berada pada sekitar **60% dari keseluruhan target proposal capstone**. Jika IoT ESP32 dan AI dikeluarkan dari perhitungan, inti website berada pada sekitar **75%**.

Angka ini adalah estimasi penilaian fitur, bukan metrik terukur atau perhitungan jumlah file. Bobot area belum disepakati tim, sehingga angka keseluruhan tidak boleh dipakai sebagai laporan kemajuan formal. Alur browser inti yang disebutkan di bawah telah menjalani UAT manual lokal; area lain tetap dinilai dari implementasi dan test otomatis yang tersedia.

| Area proposal | Status | Estimasi | Catatan |
| --- | --- | ---: | --- |
| Login, RBAC, pengguna, dan outlet | Tersedia | 90% | Role, permission, assignment unit, dan route guard tersedia. |
| Produk dan lokasi | Tersedia | 85% | Item, barcode, harga per unit, lokasi inventori, dan unit operasional tersedia. |
| Persediaan dan ledger | Tersedia | 80% | Saldo, stok masuk/keluar, transfer, dan riwayat mutasi tersedia. `ADJUSTMENT` dan `DAMAGED` belum tersedia. Stock opname di luar lingkup. |
| POS dan ticketing | Tersedia | 80% | Keranjang, barcode manual, ticketing, checkout atomik, dan riwayat transaksi tersedia. |
| Dashboard dan laporan | Sebagian | 40% | Dashboard operasional dasar tersedia; laporan bisnis lengkap dan AI Insight belum tersedia. |
| Kualitas dan kesiapan operasional | Sebagian | 80% | Backend dan frontend lulus pemeriksaan otomatis; alur API inti memiliki test integrasi dan perjalanan tiga role telah lulus UAT browser manual. Suite browser E2E otomatis belum tersedia. |
| IoT dan manajemen perangkat | Belum tersedia | 10% | Input barcode di POS ada, tetapi bukan integrasi ESP32. |
| Forecasting dan rekomendasi AI | Belum tersedia | 0% | Tidak ada dataset, model, tabel hasil, atau halaman AI Insight. |

## Alur yang telah memiliki implementasi

- Kode menyediakan pengelolaan pengguna, unit, item, role, permission, dan audit log untuk Superadmin.
- Kode menyediakan stok masuk/keluar Gudang dan transfer ke outlet.
- Kode menyediakan POS outlet/ticketing, keranjang, dan checkout.
- Checkout mengunci saldo stok dan menolak penjualan yang melampaui stok tersedia.
- Transfer menahan stok Gudang ketika dibuat dan memindahkan saldo setelah diterima.
- Antarmuka menyediakan riwayat transaksi dan mutasi. Backend dapat dijalankan dengan database MySQL lokal dan endpoint health merespons; alur browser Gudang → transfer → Outlet → POS → checkout telah diverifikasi manual.
- Seeder demo opt-in menyediakan akun tiga role, unit outlet dan ticketing, katalog, harga, serta stok awal tanpa menyimpan kata sandi di repositori. Seeder dan peralihan assignment aktif memiliki regression check yang relevan.

## Kesenjangan utama terhadap proposal

1. Tidak ada `devices`, token perangkat, endpoint scan ESP32, `scan_id`, retry idempoten, status perangkat, atau event real-time.
2. Tidak ada `visitor_daily`, `forecast_results`, `stock_recommendations`, layanan Python, eksperimen model, atau dashboard rekomendasi.
3. Jenis mutasi saat ini adalah `STOCK_IN`, `STOCK_OUT`, `TRANSFER_IN`, `TRANSFER_OUT`, dan `SALE`. Proposal juga memerlukan aturan untuk stok awal, penyesuaian, serta barang rusak.
4. Dashboard belum menampilkan omzet per outlet, produk terlaris, penjualan per jam/hari, prakiraan, dan rekomendasi pengadaan sebagaimana target proposal.
5. Bukti uji API end-to-end dan UAT browser manual tersedia untuk alur inti, tetapi belum ada suite browser end-to-end otomatis.

## Catatan kesesuaian istilah

Implementasi memakai role `superadmin`, `warehouse`, dan `admin`. Untuk kebutuhan proposal, `admin` berfungsi sebagai role petugas outlet/ticketing yang terikat pada satu unit operasional. Dokumentasi dan SKPL final perlu menyatakan pemetaan ini secara eksplisit agar tidak menimbulkan ambiguitas.
