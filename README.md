# Asetify ECI Backend

Backend API Asetify berbasis CodeIgniter 4 untuk flow listing aset IT:

- check duplicate serial number
- upload foto bukti aset
- create dan update aset
- history scan
- audit log perubahan aset

## Ringkasan API Foto

Flow foto aset saat ini:

- `POST /api/v1/uploads/photos` dapat dipanggil tanpa token untuk upload file gambar sementara.
- `POST /api/v1/assets` tetap butuh bearer token, dan menerima `photo_upload_ids` array maupun `photo_upload_id` tunggal.
- `POST /api/v1/assets/{assetId}/photos` tetap butuh bearer token.
- `GET /api/v1/assets/{assetId}/download-photo/{photoId}` dapat dipanggil tanpa token untuk fetch gambar.

Catatan perilaku:

- Upload sementara disimpan di `writable/uploads/tmp`.
- Saat asset dibuat atau foto ditambahkan ke asset existing, file dipindahkan ke `writable/uploads/assets`.
- Link `photo_url` dan `download_url` sekarang aman dipakai langsung oleh frontend tanpa bearer token.
- Upload sementara hanya bisa dipakai sekali karena record upload akan ditandai `consumed_at`.

Skema database dan migration di repo ini mengikuti dokumen acuan pada folder parent:

- `../docs/database-design.md`
- `../docs/db-schema.sql`
- `../docs/api-spec.md`

## Requirements

- PHP 8.2+
- MySQL 8 atau MariaDB 10.11+
- Composer

## Setup Database

1. Copy file `env` menjadi `.env`.
2. Aktifkan konfigurasi database di `.env`.
3. Isi kredensial database lokal.
4. Jalankan create database bila database belum ada.
5. Jalankan migration.
6. Jalankan seeder master data awal.

Contoh konfigurasi `.env`:

```ini
CI_ENVIRONMENT = development

database.default.hostname = localhost
database.default.database = asetify_eci
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = 3306
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_general_ci
```

Command setup:

```bash
php spark db:create asetify_eci
php spark migrate --all
php spark db:seed DatabaseSeeder
```

Jika ingin langsung menyiapkan akun development untuk login API:

```bash
php spark db:seed DevelopmentUserSeeder
```

Jika repo sudah sempat dimigrate sebelum endpoint upload foto ditambahkan, jalankan lagi:

```bash
php spark migrate --all
```

## Production Deploy

Gunakan file `env.production.example` sebagai baseline config server live. Nilai `app.baseURL`, kredensial database, dan `encryption.key` wajib diganti sebelum aplikasi menerima traffic.

Checklist minimum:

1. Copy `env.production.example` menjadi `.env` di server production.
2. Pastikan document root web server mengarah ke folder `public/`, bukan root project.
3. Jalankan `composer install --no-dev --optimize-autoloader`.
4. Jalankan `php spark migrate --all`.
5. Jalankan `php spark db:seed DatabaseSeeder` bila master data belum ada.
6. Jalankan `php spark optimize`.
7. Pastikan folder `writable/` bisa ditulis oleh web server.

Catatan penting:

- Session file jangan diarahkan ke `null`. Biarkan `session.savePath` kosong agar tetap memakai `writable/session`.
- `app.forceGlobalSecureRequests = true` mengasumsikan traffic sudah HTTPS. Jika server ada di balik reverse proxy/load balancer, konfigurasi proxy trusted IP juga harus benar.
- Runtime production sekarang menonaktifkan `DBDebug` dan memaksa cookie secure di environment production.

## Testing

Feature test API sekarang sudah mencakup authorization untuk foto aset existing.

Jika memakai MySQL untuk PHPUnit, siapkan database test terpisah sesuai `.env`:

```bash
php spark db:create "asetify-eci-be"
```

Jalankan test:

```bash
vendor\bin\phpunit tests\feature\Api\AssetPhotoManagementTest.php
```

## Tabel Yang Dibuat

Migration saat ini mencakup:

- tabel auth dari CodeIgniter Shield
- `asset_categories`
- `brands`
- `locations`
- `assets`
- `asset_photos`
- `asset_scan_logs`
- `asset_movements`
- `asset_audit_logs`

Seeder awal mengisi contoh data untuk:

- kategori aset
- brand
- lokasi

Seeder development user menambahkan akun berikut:

- `admin` atau `admin@asetify.test` / `Password123!`
- `supervisor01` atau `supervisor@asetify.test` / `Password123!`
- `scanner01` atau `scanner@asetify.test` / `Password123!`

## Catatan

- Package auth yang dipakai adalah `CodeIgniter Shield` dengan role `scanner`, `supervisor`, dan `admin`.
- Upload foto sementara disimpan di `writable/uploads/tmp`, lalu dipindah ke `writable/uploads/assets` saat asset berhasil dibuat.
- `asset_photos.file_size_bytes` dibatasi dengan check constraint `<= 1048576` sesuai requirement foto maksimal 1 MB.
- `assets.serial_number` dibuat unik secara database.
- Endpoint foto publik:
  `POST /api/v1/uploads/photos` dan `GET /api/v1/assets/{assetId}/download-photo/{photoId}`.
- Fondasi API yang sudah tersedia saat ini:
  `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`, dan endpoint `GET /api/v1/masters/*`.
