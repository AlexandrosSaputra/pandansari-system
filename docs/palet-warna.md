# Palet warna light-mode

Palet diterapkan pada 25 Oktober 2026. Sumber token ada di `frontend/src/theme.css`, dipakai oleh CSS komponen, utility Tailwind, dan ilustrasi Three.js pada login.

## Alasan kombinasi

Cool Jade (`#03695E`) menjadi pusat identitas hijau. Spring Herb (`#6DAA5F`) memberi aksen hijau yang lebih hangat, sedangkan Linen White (`#E7E9D8`) menghubungkan aksen tersebut dengan area putih. Sky Aqua (`#239DCA`) dan mist (`#A5C6DB`) digunakan untuk informasi dan dekorasi. Ungu redup (`#6D5C92`) membedakan lokasi/outlet. Aksen ini ditempatkan pada card dan penanda konteks agar identitas putih-hijau tetap dominan.

Kelima warna referensi tidak dipakai dengan porsi sama. Kartu, tabel, modal, sidebar, dan topbar tetap putih. Canvas memakai turunan linen yang lebih terang (`#F6F7F1`) agar halaman operasional tidak terlihat terlalu krem. Teks memakai netral gelap kehijauan. Kecocokan ini adalah keputusan desain berdasarkan hierarki dan keterbacaan, bukan hasil pengujian preferensi pengguna.

## Pemetaan implementasi

| Peran | Warna/token | Penggunaan |
| --- | --- | --- |
| Area kerja | `--color-surface` / `#FFFFFF` | Kartu, form, modal, tabel, navigasi. |
| Canvas | `--color-canvas` / `#F6F7F1` | Latar aplikasi dan halaman detail outlet. |
| Aksi utama | `--color-primary` / `#03695E` | Tombol utama, tautan, fokus keyboard, checkbox. |
| Hover utama | `--color-primary-hover` / `#02564D` | Tombol yang sedang ditunjuk. |
| Pilihan aktif | `--color-primary-soft` / `#E5F2EF` | Navigasi terpilih dan pilihan item, dengan teks jade. |
| Herb | `#6DAA5F`, Mantis `#5DBB63`, tinta `#365E2E`, latar `#EFF5EB` | Ringkasan item aktif/teralokasi dan dekorasi login. |
| Aqua | `#239DCA`, tinta `#126482`, latar `#E8F4F9` | Ringkasan informasi, badge layanan, dan notifikasi informasi. |
| Mist | `#A5C6DB` | Border informasi dan aksen ilustrasi. |
| Lokasi | `#6D5C92`, latar `#F3F0F8` | Outlet, lokasi, dan ikon transfer. |
| Linen | `#E7E9D8`, tinta `#535D38`, latar `#F3F4E9` | Permukaan netral yang hangat dan aksen dekoratif. |
| Berhasil/aktif | `#398457`, tinta `#27643E`, latar `#E9F5EB` | Status aktif, transaksi berhasil, notifikasi sukses. |
| Teks | `#172D29`, `#3F554E`, `#5E7067` | Teks utama, sekunder, petunjuk, placeholder. |
| Border | `#DCE3D8` / `#7C8F82` | Pemisah kartu / batas kontrol input yang lebih tegas. |
| Kesalahan | `#B91C1C` pada `#FEF2F2` | Error dan aksi berbahaya. |
| Peringatan | `#92400E` pada `#FFFBEB` | Peringatan operasional. |

Ringkasan Master Item memetakan katalog ke jade, aktif ke hijau, kategori ke aqua, dan cakupan outlet ke ungu. Ringkasan Outlet memakai ungu untuk lokasi, jade untuk master item, hijau untuk item teralokasi, dan aqua untuk tarif tiket. Dashboard memakai jade untuk inventori, amber untuk transfer yang menunggu, serta hijau untuk transaksi dibayar. Semua card mempertahankan label dan angka; warna membantu pemindaian, tetapi bukan satu-satunya petunjuk.

Tombol utama tetap jade. Edit dan Reset filter memakai jade/netral; hapus memakai merah. Amber menandakan perhatian, merah untuk hapus, penolakan, atau error, sedangkan status nonaktif memakai netral. Sebutan "Cakupan outlet" menggantikan "Outlet terhubung" pada katalog karena card tersebut belum mengambil status koneksi dari backend.

## Kontras yang diverifikasi

Perhitungan luminansi relatif sRGB dilakukan pada pasangan token solid. Target proyek untuk pasangan yang diperiksa adalah sedikitnya 4,5:1 untuk teks kecil dan 3:1 untuk border kontrol terhadap permukaannya.

| Pasangan | Rasio |
| --- | ---: |
| Putih pada tombol jade | 6,59:1 |
| Putih pada hover tombol | 8,60:1 |
| Jade pada navigasi aktif | 5,74:1 |
| Jade pada tint ringkasan | 4,97:1 |
| Tinta herb pada latar herb | 6,77:1 |
| Tinta aqua pada latar informasi | 5,91:1 |
| Tinta berhasil pada latar berhasil | 6,29:1 |
| Ungu lokasi pada latar lokasi | 5,18:1 |
| Tinta linen pada latar linen | 6,33:1 |
| Teks muted pada canvas | 4,89:1 |
| Teks muted pada permukaan subtle | 4,70:1 |
| Border kontrol pada putih | 3,44:1 |
| Border kontrol pada canvas | 3,19:1 |
| Teks error pada latar error | 5,91:1 |
| Teks peringatan pada latar peringatan | 6,84:1 |

Warna herb asli dengan putih hanya menghasilkan sekitar 2,78:1, sedangkan aqua asli dengan putih sekitar 3,11:1. Keduanya tidak digunakan sebagai tombol bertulisan putih kecil. Varian tinta yang lebih gelap dipakai untuk label dan ikon informasi; warna asli tetap hadir sebagai aksen dekoratif.

Rasio di atas tidak membuktikan aksesibilitas seluruh halaman. Opasitas, disabled state, gradien, pencahayaan WebGL, dan hasil komposisi browser perlu dinilai pada tampilan akhir.

## Pemeliharaan dan verifikasi

- Utility `emerald-*`, `slate-*`, dan `blue-*` yang sudah ada diarahkan ke token melalui `@theme inline`. Komponen lama dan CSS khusus dengan demikian memakai sumber warna yang sama. Nama utility dipertahankan untuk membatasi perubahan markup.
- CSS khusus login dan detail outlet memakai token, termasuk warna material dan pencahayaan ilustrasi login. Layout dan alur transaksi tidak diubah oleh pekerjaan palet ini.
- `npm run build`, `npm run lint`, dan `npm test` berhasil; suite frontend berisi 47 pemeriksaan SSR/alur klien dengan fixture lokal. Pemeriksaan CSS hasil build tidak menemukan referensi token warna yang hilang.
- Verifikasi visual desktop/mobile dan interaksi browser belum selesai: akses computer-use ke Google Chrome ditolak oleh persetujuan otomatis. UAT perlu memeriksa login, dashboard, Master Item, Outlet, detail outlet, POS, modal, hover, dan fokus keyboard.
