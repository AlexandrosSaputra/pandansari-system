# Laporan Frontend Pandansari System

Tanggal: 8 Oktober 2026. Implementasi menggunakan backend yang tersedia, tanpa paket baru dan tanpa perubahan kode backend.

## 1. Route frontend

| Route | Permission | Keterangan |
| --- | --- | --- |
| `/login` | Publik | Form login; pengguna yang sudah masuk diarahkan ke dashboard |
| `/dashboard` | `dashboard.view` | Ringkasan sesuai role |
| `/users` | `user.view` | Daftar, tambah, edit pengguna |
| `/operational-units` | `operational-unit.view` | Daftar, tambah, edit unit |
| `/operational-units/:id` | `operational-unit.view` | Informasi unit; tab child memerlukan `item.view` |
| `/items` | `item.view` | Master item |
| `/rbac` | `rbac.view` | Role dan mapping permission |
| `/activity-logs` | `audit.view` | Audit dan detail metadata |
| `/inventory` | `inventory.view` | Saldo gudang/unit |
| `/inventory/movements` | `inventory.view` | Pergerakan stok |
| `/stock-transfers` | `transfer.view` | Daftar, buat, detail, respons transfer |
| `/pos` | `transaction.create` | POS untuk unit outlet; role Admin/Superadmin |
| `/ticketing` | `transaction.create` | Transaksi untuk unit ticketing; role Admin/Superadmin |
| `/transactions` | `transaction.view` | Riwayat dan detail; role Admin/Superadmin |

`/` mengarah ke dashboard. Route aplikasi yang tidak dikenal menampilkan 404. Seluruh route aplikasi berada di dalam guard autentikasi.

## 2. Sidebar per permission

- Ruang kerja: Dashboard (`dashboard.view`).
- Transaksi: POS outlet dan Ticketing (`transaction.create`), serta Riwayat (`transaction.view`). Admin hanya melihat ruang transaksi yang sesuai dengan tipe unitnya; Superadmin melihat keduanya. Gudang tidak ditampilkan karena backend membatasi transaksi ke Admin/Superadmin.
- Inventori: Stok dan Pergerakan (`inventory.view`), Transfer (`transfer.view`).
- Master data: Unit (`operational-unit.view`), Item (`item.view`), Pengguna (`user.view`).
- Administrasi: RBAC (`rbac.view`), Audit (`audit.view`).

Permission dibaca dari `/auth/me`. Tombol mutasi memeriksa permission aksi; mutasi master/RBAC juga mensyaratkan Superadmin sesuai middleware backend. Slug Gudang mengikuti backend: `warehouse`.

## 3. Auth flow

`GET /sanctum/csrf-cookie` → `POST /api/auth/login` → `GET /api/auth/me`. Axios memakai `withCredentials` dan `withXSRFToken`. User disimpan pada query `['auth', 'me']`. Logout memakai `POST /api/auth/logout`. Tidak ada token/password di localStorage atau sessionStorage.

Pergantian akun/logout membersihkan cache privat. Query autentikasi dipertahankan sebagai objek agar observer guard segera menerima user baru atau `null`. Respons mutasi keranjang dari sesi lama tidak boleh mengisi cache setelah sesi berubah.

## 4. Protected routes

`ProtectedRoute` memeriksa sesi, menampilkan skeleton ketika pemeriksaan awal, lalu mengarahkan tamu ke login. `PermissionGuard` memeriksa akses setiap route; penolakan menampilkan ForbiddenPage. Backend tetap validator akhir. Layout, Sidebar, dan Topbar bertahan saat isi route berubah.

## 5. Dashboard per role

- Admin: nama unit, akses POS atau Ticketing sesuai tipe unit, inventori unit, transfer menunggu, transaksi terakhir.
- Gudang: Gudang Utama, jenis produk inventori, transfer menunggu, stok masuk/keluar, dan akses pergerakan stok.
- Superadmin: ringkasan serta akses master, transaksi, inventori, transfer, audit, dan RBAC.

Jumlah berasal dari `meta.total` API yang relevan. Jumlah transaksi diberi label seluruh periode; tidak ada revenue/visitor/sales rekaan. Health check ditempatkan dalam bagian development yang dapat dibuka.

## 6. Pengguna

Pencarian, role, unit, status, pagination. Tambah/edit memakai RHF + Zod. Admin wajib memiliki unit; role lain mengirim unit `null`. Password edit opsional; password baru mengikuti minimal 12 karakter, huruf besar/kecil, angka, dan konfirmasi. Tidak ada delete. Penonaktifan memerlukan konfirmasi.

## 7. Unit operasional

Filter pencarian, jenis, status. Kode unit dibuat otomatis dengan dua huruf pertama dari nama dan empat angka acak ketika pengguna selesai mengisi nama; backend memvalidasi format dan keunikannya serta menyediakan fallback saat kode tidak dikirim. Form menampilkan kode sebagai field abu-abu baca-saja beserta placeholder dan petunjuk. Form juga mengelola nama/type/scanner/status. Ticketing mematikan scanner. Daftar terhubung ke halaman detail. Penonaktifan memakai konfirmasi.

## 8. Unit Item / Child

Tab informasi dan child. Kolom kode, nama, jenis, harga jual, stok minimum, status. Tambah child memilih master item dengan pencarian/pagination server. Edit tidak mengirim `item_id`, sesuai larangan request backend. Layanan memakai stok minimum nol.

## 9. Master item

Pencarian, jenis, status; code/barcode/name/type/uom/visitor_factor/status. Produk memiliki faktor pengunjung nol; layanan dapat mengubah faktor. Penonaktifan dikonfirmasi.

## 10. RBAC

Role di kiri, permission berkelompok menurut prefix di kanan. Mapping disimpan dengan `PUT /roles/:id/permissions`, berupa array slug. Role Superadmin read-only. Perubahan mapping dikonfirmasi dan me-refresh auth. Permission backend yang berkaitan dengan AI hanya tercantum dalam mapping; tidak ada halaman/fitur AI.

## 11. Log aktivitas

Filter actor/action/subject/date, pagination, detail metadata dalam modal dengan pretty JSON. Tabel tidak memuat JSON besar.

## 12. Inventori

Admin langsung melihat unit sendiri. Gudang/Superadmin mempunyai tab Gudang dan Unit. Saldo fisik, reserved, available, waktu pembaruan berasal dari API. Inventori unit menambah harga, minimum, dan badge Stok Rendah dari `low_stock`. Pilihan unit memerlukan akses daftar unit; lihat keterbatasan pada poin 29.

## 13. Stok masuk/keluar

Form produk aktif, jumlah positif, catatan. Stok keluar menampilkan saldo dan membatasi jumlah terhadap available yang ditampilkan. Backend tetap memvalidasi saldo terbaru. Keberhasilan me-refresh inventori gudang dan pergerakan.

## 14. Pergerakan stok

Kolom waktu/lokasi/item/arah/jenis/jumlah/pelaku/catatan/referensi. Filter lokasi, item, jenis, arah, pelaku, tanggal. Lokasi memakai ID karena belum ada endpoint direktori lokasi yang dapat digunakan seluruh role. Lookup item/pelaku berdasarkan nama digunakan bila permission daftar master tersedia; tanpa permission, filter menerima ID.

## 15. Transfer

Daftar, filter status/tujuan/pembuat/tanggal/nomor, pagination, detail, prefetch detail saat hover/focus. Form baris dinamis mengambil produk aktif tujuan dari endpoint inventori unit, kemudian memeriksa saldo gudang; pilihan duplikat dinonaktifkan dan divalidasi. Respons menerima memakai konfirmasi; penolakan wajib alasan. Aksi respons hanya untuk transfer menunggu dan Admin tujuan/Superadmin dengan permission. Cache transfer, gudang, inventori tujuan, dan pergerakan diperbarui.

## 16. POS dan Ticketing

POS dan Ticketing memiliki menu, route, judul halaman, serta pilihan unit terpisah, tetapi tetap memakai satu engine transaksi. Admin memakai unit sendiri tanpa mengirim `operational_unit_id` yang dilarang backend dan hanya melihat ruang transaksi sesuai tipe unitnya. Superadmin melihat kedua menu; pemilih pada setiap halaman hanya memuat unit dengan tipe yang sesuai. Ticketing memakai judul katalog layanan; katalog tetap mengikuti hasil backend. Halaman POS tidak menyediakan input barcode manual; scan barcode dikirim oleh perangkat ESP32 atau klien API seperti Postman melalui endpoint scan backend. Produk dengan available nol tidak dapat ditambahkan dari katalog.

## 17. Keranjang

Resolve/resume melalui `POST /transactions/cart`, dideduplikasi oleh query per user/unit; tidak dibuat setiap render. Baris katalog manual, quantity minus/input/plus, PATCH, DELETE. Total, subtotal, visitor_count, dan harga memakai respons server. Mutasi diserialkan per keranjang; tidak ada optimistic update. Transaksi PAID tidak dapat diubah.

## 18. Checkout

Tombol Konfirmasi Pembayaran membuka pesan pemeriksaan bukti pembayaran. Keberhasilan menampilkan nomor transaksi dan status PAID; tombol Transaksi Baru melakukan resolve berikutnya. Konflik 409 mempertahankan keranjang, memperbarui katalog/inventori, dan membaca ulang keranjang bila pengguna memiliki `transaction.view`. UI mengarahkan pengguna menyesuaikan stok/jumlah.

## 19. Riwayat transaksi

Filter status/unit/pembuat/pengonfirmasi/tanggal/nomor, pagination server, detail dalam modal. Harga historis memakai `unit_price` dan `subtotal` transaction item, bukan katalog saat ini.

## 20. Komponen reusable

Button, Input, Select, Textarea, Badge, Card, Modal, Drawer, ConfirmDialog, Table, ListContent, Pagination, EmptyState, ErrorState, Skeleton, Notice, FormField, SaveForm, PageHeader, FilterBar, ResourcePicker, EntityFilter. Feedback keberhasilan inline; tidak memasang library toast. Dialog memakai elemen native `dialog`, focus return, Escape, dan penguncian scroll.

## 21. Query/cache

Factory `keys.list(path, params)`, `keys.detail(path)`, `keys.cart(user, unit)`, `keys.auth`. Stale time umum 60 detik, garbage collection 5 menit. Pagination/filter disimpan di URL; ukuran default 15. Lookup master juga memakai pagination server. Background refetch mempertahankan data; placeholder dari unit/resource berbeda tidak dibawa ke unit lain. Invalidation dibatasi resource terkait, bukan seluruh aplikasi, kecuali pembersihan privat saat pergantian sesi.

## 22. Error handling

401 membersihkan sesi dan memicu guard login; kredensial login salah tidak menimbulkan loop. 403/404 menampilkan pesan yang sesuai. Tabel menyembunyikan cache setelah penolakan akses. 409 menampilkan pesan konflik/domain. 422 dipetakan ke field RHF bila tersedia. 419, 429, dan kegagalan jaringan mempunyai pesan terpusat. 500 tidak mengekspos stack trace/pesan internal.

## 23. Motion

Motion dimuat lewat LazyMotion; animasi masuk konten, dialog/drawer, dan baris keranjang sekitar 160–180 ms. Preferensi reduced motion dihormati. Tidak menganimasikan seluruh card/teks.

## 24. Responsive

Sidebar tetap pada desktop, drawer pada layar kecil. Tabel dapat di-scroll horizontal, form dua kolom turun menjadi satu, dialog dibatasi viewport, POS dua panel menjadi satu kolom. Pemeriksaan interaksi/visual browser belum dilakukan karena tidak ada browser yang tersedia pada sesi alat ini.

## 25. File baru

Path relatif terhadap root project:

```text
frontend/IMPLEMENTATION.md
frontend/scripts/verify-ui.mjs
frontend/src/components/common/navigation.ts
frontend/src/components/ui/DataTable.tsx
frontend/src/components/ui/Dialog.tsx
frontend/src/components/ui/EntityFilter.tsx
frontend/src/components/ui/Filters.tsx
frontend/src/components/ui/ResourcePicker.tsx
frontend/src/components/ui/SaveForm.tsx
frontend/src/components/ui/filterDefinitions.ts
frontend/src/components/ui/index.tsx
frontend/src/components/ui/primitives.tsx
frontend/src/features/audit/ActivityLogsPage.tsx
frontend/src/features/auth/LoginPage.tsx
frontend/src/features/auth/RouteGuard.tsx
frontend/src/features/auth/useAuth.ts
frontend/src/features/dashboard/components/RecentActivity.tsx
frontend/src/features/dashboard/components/SummaryCard.tsx
frontend/src/features/inventory/InventoryPage.tsx
frontend/src/features/inventory/MovementsPage.tsx
frontend/src/features/inventory/StockForm.tsx
frontend/src/features/inventory/UnitSelector.tsx
frontend/src/features/items/ItemForm.tsx
frontend/src/features/items/ItemsPage.tsx
frontend/src/features/operational-units/UnitDetailPage.tsx
frontend/src/features/operational-units/UnitForm.tsx
frontend/src/features/operational-units/UnitItemForm.tsx
frontend/src/features/operational-units/UnitsPage.tsx
frontend/src/features/rbac/RbacPage.tsx
frontend/src/features/rbac/permissionLabels.ts
frontend/src/features/transactions/CartLine.tsx
frontend/src/features/transactions/CartPanel.tsx
frontend/src/features/transactions/PosPage.tsx
frontend/src/features/transactions/TransactionDetail.tsx
frontend/src/features/transactions/TransactionsPage.tsx
frontend/src/features/transactions/usePosCart.ts
frontend/src/features/transfers/TransferDetail.tsx
frontend/src/features/transfers/TransferForm.tsx
frontend/src/features/transfers/TransfersPage.tsx
frontend/src/features/users/UserForm.tsx
frontend/src/features/users/UsersPage.tsx
frontend/src/hooks/useFilters.ts
frontend/src/lib/errors.ts
frontend/src/lib/motionFeatures.ts
frontend/src/lib/queries.ts
frontend/src/pages/ForbiddenPage.tsx
frontend/src/pages/RouteErrorPage.tsx
frontend/src/types/api.ts
frontend/src/utils/format.ts
```

## 26. File diubah

Path relatif terhadap root project:

```text
frontend/package.json
frontend/src/App.tsx
frontend/src/components/common/Sidebar.tsx
frontend/src/components/common/Topbar.tsx
frontend/src/features/dashboard/components/HealthStatusCard.tsx
frontend/src/features/dashboard/pages/DashboardPage.tsx
frontend/src/index.css
frontend/src/layouts/AppLayout.tsx
frontend/src/lib/api.ts
frontend/src/lib/queryClient.ts
frontend/src/router/index.tsx
```

Selain file tracked, `frontend/.env` lokal diperbaiki dari akhiran `/apie` menjadi `/api`. File ini diabaikan Git. Perubahan HealthStatusCard hanya perapian format. `package.json` menambahkan perintah test tanpa menambah dependency; lockfile tidak berubah.

## 27. Lint dan test

- `npm run lint`: lulus, tanpa error/peringatan.
- `npm test`: 31 pemeriksaan lulus menggunakan Node assert, Vite SSR, dan dependency yang sudah tersedia.
- Cakupan: guard/sidebar, batasan role Gudang, error sensitif, form, dashboard, harga historis, payload keranjang, konflik checkout, larangan mutasi PAID, urutan login, observer auth pada login/logout/401, pembersihan cache privat.
- Fixture dan adapter API hanya ada dalam test; test tidak mengakses/mengubah data backend.
- `git diff --check`: lulus; Git hanya memberi informasi konversi line ending Windows.

## 28. Build dan HTTP smoke check

- `npm run build`: lulus; TypeScript tanpa error; tidak ada peringatan chunk besar. Main JavaScript sekitar 461 KB sebelum gzip; route, form schema, dan fitur Motion terpisah.
- HTTP lokal aplikasi Pandansari: frontend 200; health 200; unauthenticated `/auth/me` 401; CSRF 204 dengan cookie; login menggunakan cookie/XSRF dan kredensial uji tidak valid menghasilkan 401, bukan 419.
- CORS mengizinkan origin frontend dengan credentials.
- Belum ada uji login sukses dengan akun nyata maupun E2E transaksi melalui browser. Hasil test adapter/SSR tidak diklaim sebagai interaksi browser.

## 29. Backend dan mismatch akses

Tidak ada file backend yang diubah, tidak ada seed/migrasi, dan permission akun nyata tidak diubah.

Role Gudang bawaan mempunyai `transfer.create` dan `inventory.view`, tetapi tidak mempunyai `operational-unit.view`. Tidak tersedia endpoint lookup unit terpisah. Akibatnya, pemilihan unit tujuan transfer dan inventori outlet Gudang memerlukan Superadmin mengaktifkan `operational-unit.view` melalui RBAC. UI menjelaskan kondisi ini dan tidak mengirim request daftar unit tanpa izin. Mismatch ini sudah dilaporkan sebelum implementasi; opsi perubahan backend tidak dijalankan tanpa keputusan lebih lanjut. Produk aktif tujuan diambil dari inventori unit sehingga tidak membutuhkan tambahan `item.view`.

Port 8000/5173 sedang digunakan proyek lain. Server verifikasi dijalankan terpisah pada backend 8001 dan frontend 5174, dengan override environment khusus proses. Konfigurasi backend di disk tetap sama.

Untuk menjalankan ulang mode verifikasi, gunakan dua terminal PowerShell:

```powershell
# Terminal backend, dari root project
Set-Location backend
$env:FRONTEND_URL='http://localhost:5174'
$env:SANCTUM_STATEFUL_DOMAINS='localhost:5174'
php artisan serve --host=127.0.0.1 --port=8001 --no-reload
```

```powershell
# Terminal frontend, dari root project
Set-Location frontend
$env:VITE_API_URL='http://localhost:8001/api'
node node_modules/vite/bin/vite.js --host 127.0.0.1 --port 5174 --strictPort
```

Buka `http://localhost:5174` agar origin/hostname cookie sesuai konfigurasi proses. Konfigurasi standar tetap memakai frontend 5173/backend 8000 ketika port tersebut tersedia. Set `VITE_API_URL` yang sesuai sebelum build untuk lingkungan tujuan.

## 30. Batas scope

Tidak melanjutkan ke AI, IoT/ESP32, scanner session, device management, WebSocket/MQTT, payment gateway, cetak struk, refund/return, password reset, edit profil, atau dark mode.
