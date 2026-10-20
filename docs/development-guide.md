# Menjalankan Proyek untuk Pengembangan

Panduan ini untuk setup lokal pada checkout baru. Jalankan perintah dari direktori yang ditunjukkan. Nilai di `.env.example` adalah contoh lokal dan perlu disesuaikan dengan database serta port masing-masing anggota tim.

## Prasyarat

- PHP sesuai batas versi di `backend/composer.json` (`^8.3`) beserta ekstensi yang dibutuhkan Composer/Laravel. Aktifkan `pdo_mysql` untuk aplikasi dan `pdo_sqlite` untuk test otomatis yang memakai SQLite in-memory.
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

Jika perintah test menampilkan `could not find driver` untuk koneksi SQLite, aktifkan ekstensi `pdo_sqlite` pada PHP CLI yang digunakan. Pada instalasi Windows yang sudah menyediakan DLL tetapi belum mengaktifkannya, test dapat diperiksa sementara tanpa mengubah `php.ini`:

```powershell
php -d extension=php_pdo_sqlite.dll vendor/bin/phpunit
```

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
