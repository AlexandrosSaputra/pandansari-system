# Menjalankan Proyek untuk Pengembangan

Panduan ini untuk setup lokal pada checkout baru. Jalankan perintah dari direktori yang ditunjukkan. Nilai di `.env.example` adalah contoh lokal dan perlu disesuaikan dengan database serta port masing-masing anggota tim.

## Prasyarat

- PHP sesuai batas versi di `backend/composer.json` (`^8.3`) beserta ekstensi yang dibutuhkan Composer/Laravel. Aktifkan `pdo_mysql` untuk aplikasi dan test otomatis.
- Composer, Node.js, npm, dan MySQL.
- Database MySQL yang dapat diakses dengan pengaturan `DB_*` pada `backend/.env`.

## Backend

Di PowerShell, masuk ke `backend/`, lalu jalankan:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate --no-interaction
```

Sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`. Buat database yang ditunjuk oleh `DB_DATABASE` sebelum migrasi. Pada database pengembangan yang memang disiapkan untuk proyek ini, jalankan:

```powershell
php artisan migrate --seed --no-interaction
php artisan serve --host=localhost --port=8000 --no-reload
```

Seeder membuat role, permission, lokasi Gudang, dan akun awal pengembangan. Detail akun awal ada di `backend/database/seeders/SuperadminUserSeeder.php`; ganti kredensial awal sebelum memakai lingkungan bersama. Jangan menjalankan seeder pada database berisi data penting tanpa meninjau efeknya.

Untuk lingkungan demonstrasi lokal yang kosong, tersedia seeder opt-in yang menambahkan unit outlet dan ticketing, akun Gudang dan petugas, katalog, harga, serta stok awal. Kata sandi tidak disimpan di repositori dan wajib diberikan melalui environment saat menjalankan seeder:

```powershell
$env:DEMO_USER_PASSWORD = '<kata-sandi-demo-lokal>'
php artisan db:seed --class=DemoDataSeeder --no-interaction
Remove-Item Env:DEMO_USER_PASSWORD
```

`DemoDataSeeder` idempoten untuk data master dan hanya menambah stok Gudang hingga target demo jika saldonya masih kurang. Seeder tidak dipanggil oleh `DatabaseSeeder`, sehingga tidak berjalan tanpa perintah eksplisit.

Untuk fitur lupa password, isi alamat penerima OTP khusus di `backend/.env` dan konfigurasi SMTP sesuai penyedia email yang digunakan:

```dotenv
SUPERADMIN_EMAIL=superadmin@example.com
PASSWORD_RESET_SUPERADMIN_EMAIL=admin-pemulihan@example.com
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=superadmin@example.com
MAIL_PASSWORD="password-aplikasi"
MAIL_FROM_ADDRESS="superadmin@example.com"
```

`SUPERADMIN_EMAIL` menentukan email akun Superadmin awal di database. Atur nilainya sebelum menjalankan `php artisan migrate` agar migrasi memperbarui akun lokal lama yang masih memakai `admin@pandansari.local`. `PASSWORD_RESET_SUPERADMIN_EMAIL` menentukan penerima OTP dan dapat memakai alamat yang berbeda.

Alamat email operasional tidak disimpan di kode. OTP berlaku selama 10 menit, hanya dapat dicoba tiga kali, dan permintaan ulang dibatasi satu kali setiap 60 detik untuk username yang sama.

Login dikunci per username setelah tiga kata sandi salah berturut-turut. Login sukses menghapus hitungan gagal. Jika sudah terkunci, permintaan OTP saja belum membuka akun; pengguna harus menyelesaikan reset kata sandi dengan OTP yang valid terlebih dahulu.

## Frontend

Buka terminal lain, masuk ke `frontend/`, lalu jalankan:

```powershell
Copy-Item .env.example .env
npm ci
npm run dev
```

Contoh konfigurasi menggunakan `VITE_API_URL=http://localhost:8000/api` dan frontend pada `http://localhost:5173`. Backend harus mengizinkan origin frontend melalui `FRONTEND_URL` dan `SANCTUM_STATEFUL_DOMAINS`. Gunakan nama host yang konsisten (`localhost` atau `127.0.0.1`) pada kedua aplikasi agar cookie sesi bekerja.

## Pemeriksaan dasar

```powershell
# Dari backend/
php artisan test --compact
```

Test backend wajib memakai database MySQL khusus. Konfigurasi default test menggunakan `pandansari_testing` dan `pandansari_rollback_testing`; buat kedua database tersebut sebelum menjalankan suite dan jangan arahkan test ke database pengembangan atau produksi.

```powershell
# Dari frontend/
npm run lint
npm test
npm run build
```

`npm test` berisi pemeriksaan SSR/adapter, sehingga tetap perlu uji interaksi browser dan alur end-to-end dengan database lokal. Jika `backend/vendor/autoload.php` belum ada, jalankan `composer install` sebelum test backend. Jangan menafsirkan gagalnya setup dependensi sebagai kegagalan domain aplikasi.

## Lokasi kode yang sering dicari

| Kebutuhan | Lokasi |
| --- | --- |
| Endpoint API | `backend/routes/api.php` |
| Aturan stok dan transfer | `backend/app/Services/` |
| Pembatasan akses per role/unit | `backend/app/Support/` dan `backend/app/Http/Middleware/` |
| Skema data | `backend/database/migrations/` |
| Test API/domain | `backend/tests/Feature/` |
| Rute halaman web | `frontend/src/router/index.tsx` |
| Fitur halaman | `frontend/src/features/` |
| Klien API dan query cache | `frontend/src/lib/` |

Untuk perubahan backend, baca pula `backend/AGENTS.md`. Laporan implementasi frontend sebelumnya berada di `frontend/IMPLEMENTATION.md`; dokumen itu adalah catatan tanggal 8 Oktober 2026, sehingga cocok sebagai riwayat, bukan pengganti pemeriksaan kode terbaru.
