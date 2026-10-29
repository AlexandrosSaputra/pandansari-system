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

Seeder membuat role, permission, lokasi Gudang, dan akun awal pengembangan. Seluruh pasangan username/password akun bawaan dan demo didefinisikan di `backend/database/seeders/UserSeeder.php`; ganti kredensial awal sebelum memakai lingkungan bersama. Jangan menjalankan seeder pada database berisi data penting tanpa meninjau efeknya.

Untuk lingkungan demonstrasi lokal yang kosong, tersedia seeder opt-in yang menambahkan unit outlet dan ticketing, akun Gudang dan petugas, katalog, harga, serta stok awal. Kata sandi tidak disimpan di repositori dan wajib diberikan melalui environment saat menjalankan seeder:

```powershell
$env:DEMO_USER_PASSWORD = '<kata-sandi-demo-lokal>'
php artisan db:seed --class=DemoDataSeeder --no-interaction
Remove-Item Env:DEMO_USER_PASSWORD
```

`DemoDataSeeder` idempoten untuk data master dan hanya menambah stok Gudang hingga target demo jika saldonya masih kurang. Seeder tidak dipanggil oleh `DatabaseSeeder`, sehingga tidak berjalan tanpa perintah eksplisit. Akun yang tersedia setelah seeding adalah:

| Area kerja | Username | Akses |
| --- | --- | --- |
| Superadmin | `admin` | Seluruh sistem |
| Loket | `loket` | Hanya Loket Ticket |
| Outlet 1 | `outlet-1` | Hanya Outlet 1 |
| Outlet 2 | `outlet-2` | Hanya Outlet 2 |
| Outlet 3 | `outlet-3` | Hanya Outlet 3 |
| Gudang | `gudang` | Operasional Gudang |

Semua akun operasional selain Superadmin memakai kata sandi dari `DEMO_USER_PASSWORD`. Akun dengan satu area kerja langsung masuk ke halaman kerjanya setelah login: POS, Ticketing, atau Inventori. Kredensial Superadmin awal tetap didefinisikan di `UserSeeder.php` dan wajib diganti sebelum lingkungan dipakai bersama.

Setelah katalog demo tersedia, impor dataset forecasting mingguan yang sudah disertakan:

```powershell
php artisan forecast:import-sales
```

Perintah tersebut idempoten dan memvalidasi pemetaan outlet/SKU, kelengkapan minggu Senin–Minggu, duplikasi, serta kesesuaian `unit × harga = nilai penjualan`. Untuk penjelasan hasil, biarkan `FORECAST_EXPLANATION_PROVIDER=none` agar memakai fallback lokal, atau pilih `openai`/`ollama`. API key OpenAI hanya disimpan pada `backend/.env`; jangan menaruhnya di frontend atau repositori.

Setelah dataset awal tersedia, transaksi aplikasi berstatus `PAID` dapat disinkronkan menjadi histori mingguan:

```powershell
php artisan forecast:sync-sales
php artisan forecast:sync-sales --check-only
```

Sinkronisasi hanya mengelola minggu setelah data impor terakhir, mengagregasi snapshot `quantity` dan `subtotal` per outlet/SKU, serta mengisi observasi nol untuk produk tanpa penjualan. Mode `--check-only` mendeteksi minggu atau observasi yang hilang tanpa menulis data dan mengembalikan exit code gagal jika ditemukan celah. Jadwal `forecast:sync-sales` berjalan setiap hari pukul 00.15 zona `FORECAST_TIMEZONE` dan hanya memproses minggu yang sudah selesai. Pada pengembangan Windows, jalankan `php artisan schedule:work`; deployment perlu menjalankan `php artisan schedule:run` setiap menit melalui task scheduler/cron.

AI Insight menandai histori berumur paling lama 7 hari sebagai `fresh`, 8–28 hari sebagai `aging`, dan lebih dari 28 hari sebagai `stale`. Status `aging` dan `stale` memunculkan peringatan review manusia; prediksi tetap dapat dibuat agar tidak menghambat analisis, tetapi scheduler dan hasil `forecast:sync-sales --check-only` perlu diperiksa sebelum rekomendasi restock disetujui.

Atur `Safety stock`, `Lead time pemasok`, dan `Ukuran kemasan pemesanan` pada form item setiap outlet. Jumlah restock mentah memakai `prediksi + safety stock - stok tersedia`, kemudian dibulatkan ke atas sesuai ukuran kemasan. Tanggal pemesanan paling lambat adalah awal periode forecast dikurangi lead time; tanggal yang sudah terlewati ditandai sebagai terlambat. Default data lama adalah lead time 0 hari dan ukuran kemasan 1 unit.

Metode safety stock default adalah `MANUAL`, sehingga data lama tetap memakai stok minimum. Mode `CALCULATED` menghitung cadangan dengan `z-score × RMSE × √periode perlindungan`, memakai target service level 90%, 95%, 97,5%, atau 99%. Periode perlindungan mengikuti lead time dalam minggu dan tidak pernah kurang dari satu minggu. Nilai stok minimum tetap dipakai sebagai ambang indikator stok meskipun mode forecast terhitung aktif.

Eksperimen model pohon berada di `ai-service/` dan membutuhkan Python 3.10. Siapkan environment lalu jalankan test dan evaluasi:

```powershell
cd ai-service
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -e ".[dev]"
.\.venv\Scripts\python.exe -m pytest -q
.\.venv\Scripts\python.exe -m app.cli --dataset ..\backend\database\data\historical-sales.csv --output reports\latest-evaluation.json
.\.venv\Scripts\python.exe -m app.train --dataset ..\backend\database\data\historical-sales.csv --report reports\latest-evaluation.json --output artifacts\extra_trees_v1.pkl.gz
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

Endpoint `POST /experiments/tree-candidates/evaluate` memakai dataset canonical dan membandingkan tiga kandidat pohon pada validation set. Jika validation gate lulus, test set dibuka satu kali untuk membandingkan kandidat terpilih terhadap keempat baseline Laravel pada periode yang sama. `seasonal_average_3` membutuhkan 156 minggu sebelumnya, sehingga baru eligible pada test minggu ke-157–208; model ini tidak dipaksakan ke validation yang historinya belum cukup. Endpoint lama `POST /experiments/random-forest/evaluate` tetap tersedia sebagai alias deprecated. Perintah training menolak laporan bila validation gate atau test confirmation gagal dan menghasilkan artefak Extra Trees terkompresi. `GET /v1/models/current` menampilkan metadata model aktif, sedangkan `POST /v1/forecasts/weekly` menghasilkan prediksi mingguan.

Laravel tetap memakai model deret waktu lokal secara default. Untuk mengaktifkan adapter AI service setelah proses Python berjalan, atur konfigurasi berikut pada `backend/.env`:

```dotenv
FORECAST_PREDICTION_PROVIDER=ai_service
FORECAST_AI_SERVICE_URL=http://127.0.0.1:8001
FORECAST_AI_SERVICE_TIMEOUT=10
```

Gunakan `FORECAST_PREDICTION_PROVIDER=local` untuk menonaktifkan pemanggilan eksternal. Kegagalan koneksi, status HTTP gagal, jumlah prediksi yang tidak sesuai, atau payload yang tidak valid memicu fallback lokal. Setelah kegagalan pertama, produk berikutnya pada forecast run yang sama langsung memakai fallback agar timeout tidak berulang.

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
