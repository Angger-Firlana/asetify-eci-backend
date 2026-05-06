# Asetify ECI Backend

Backend API Asetify berbasis CodeIgniter 4 untuk inventaris aset IT, upload foto, export Excel, audit trail, dan workspace receipt.

## Dokumentasi

- Panduan penggunaan API lengkap: [`docs/api-usage.md`](docs/api-usage.md)
- Catatan perubahan:
  - [`docs/notes/2026-04-07-image-flow-update.md`](docs/notes/2026-04-07-image-flow-update.md)
  - [`docs/notes/2026-04-08-location-endpoint-update.md`](docs/notes/2026-04-08-location-endpoint-update.md)
  - [`docs/notes/2026-05-04-workspace-flow-update.md`](docs/notes/2026-05-04-workspace-flow-update.md)
  - [`docs/notes/2026-05-05-asset-excel-export.md`](docs/notes/2026-05-05-asset-excel-export.md)
  - [`docs/notes/2026-05-06-workspace-asset-location-detail-update.md`](docs/notes/2026-05-06-workspace-asset-location-detail-update.md)
  - [`docs/notes/2026-05-06-folder-feature-update.md`](docs/notes/2026-05-06-folder-feature-update.md)

## Fitur Inti

- check duplicate serial number
- upload foto bukti aset
- create dan update aset
- export data aset ke Excel
- history scan
- audit log perubahan aset
- workspace receipt untuk asset existing maupun asset baru
- folder/group aset dengan struktur parent-child

## Perubahan Penting Saat Ini

### Asset

- Asset sekarang memiliki field `current_location_detail`.
- Field ini dipakai untuk menyimpan titik fisik yang lebih presisi, misalnya `Rak kanan`, `Kasir FA laci 2`, atau `HO lemari A`.
- Field tersebut ikut muncul di create, update, detail, list, dan export Excel.

### Workspace

- Workspace item sekarang menyimpan draft data asset yang lebih lengkap:
  - `model_name`
  - `source_location_id`
  - `current_location_id`
  - `current_location_detail`
  - `asset_category_id`
  - `brand_id`
  - `condition_status`
- Untuk item yang belum ada di master asset, scan workspace sekarang harus menyimpan draft field asset yang dibutuhkan agar item bisa langsung diregister kemudian.
- Payload workspace memakai `current_location_id` agar konsisten dengan payload asset.
- `target_location_id` masih didukung sebagai alias lama, tetapi jika dikirim bersamaan dengan `current_location_id` nilainya harus sama.

### Folder

- Satu asset sekarang bisa masuk ke banyak folder.
- Satu folder bisa berisi banyak asset yang berbeda.
- Folder mendukung struktur hirarki lewat `parent_id`.
- Detail asset sekarang menyertakan daftar folder yang sedang terpasang.
- Backend menyediakan endpoint:
  - kelola folder
  - tree folder
  - assignment folder ke asset dari sisi asset
  - assignment asset ke folder dari sisi folder
  - list asset per folder
- Validasi folder mencegah duplicate folder dengan kombinasi `name + type + parent_id` yang sama.
- Pivot `asset_folders` mencegah relasi asset-folder double.

## Ringkasan Endpoint

### Auth

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

### Foto

- `POST /api/v1/uploads/photos`
- `GET /api/v1/assets/{assetId}/download-photo/{photoId}`
- `GET /api/v1/workspaces/items/{workspaceItemId}/download-photo/{photoId}`

### Asset

- `GET /api/v1/assets/check-sn`
- `POST /api/v1/assets`
- `GET /api/v1/assets`
- `GET /api/v1/assets/export`
- `GET /api/v1/assets/{assetId}`
- `PUT /api/v1/assets/{assetId}`
- `GET /api/v1/assets/{assetId}/photos`
- `POST /api/v1/assets/{assetId}/photos`
- `DELETE /api/v1/assets/{assetId}/photos/{photoId}`
- `GET /api/v1/assets/{assetId}/audit-logs`

### Workspace

- `GET /api/v1/workspaces`
- `POST /api/v1/workspaces`
- `GET /api/v1/workspaces/{workspaceId}`
- `POST /api/v1/workspaces/{workspaceId}/scan`
- `POST /api/v1/workspaces/{workspaceId}/items/{workspaceItemId}/register-asset`

### Folder

- `GET /api/v1/folders`
- `GET /api/v1/folders/tree`
- `POST /api/v1/folders`
- `GET /api/v1/folders/{folderId}`
- `PUT /api/v1/folders/{folderId}`
- `DELETE /api/v1/folders/{folderId}`
- `GET /api/v1/folders/{folderId}/assets`
- `POST /api/v1/folders/{folderId}/assets`
- `DELETE /api/v1/folders/{folderId}/assets/{assetId}`
- `GET /api/v1/assets/{assetId}/folders`
- `PUT /api/v1/assets/{assetId}/folders`
- `POST /api/v1/assets/{assetId}/folders`
- `DELETE /api/v1/assets/{assetId}/folders/{folderId}`

### Master Data

- `GET /api/v1/masters/brands`
- `POST /api/v1/masters/brands`
- `GET /api/v1/masters/models`
- `POST /api/v1/masters/models`
- `GET /api/v1/masters/asset-categories`
- `POST /api/v1/masters/asset-categories`
- `GET /api/v1/masters/types`
- `POST /api/v1/masters/types`
- `GET /api/v1/masters/locations`
- `POST /api/v1/masters/locations`

### Tracking dan Dashboard

- `POST /api/v1/scan-logs`
- `GET /api/v1/scan-logs`
- `GET /api/v1/audit-logs`
- `GET /api/v1/dashboard/summary`

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
- `app.forceGlobalSecureRequests = true` mengasumsikan traffic sudah HTTPS. Jika server ada di balik reverse proxy atau load balancer, konfigurasi trusted proxy juga harus benar.
- Runtime production menonaktifkan `DBDebug` dan memaksa cookie secure di environment production.

## Testing

Jika memakai MySQL untuk PHPUnit, siapkan database test terpisah sesuai `.env`:

```bash
php spark db:create "asetify-eci-be"
```

Jalankan test:

```bash
vendor\bin\phpunit tests\feature\Api
vendor\bin\phpunit tests\feature\CorsFilterTest.php
```

## Tabel Yang Dibuat

Migration saat ini mencakup:

- tabel auth dari CodeIgniter Shield
- `asset_categories`
- `brands`
- `locations`
- `assets`
- `asset_photos`
- `asset_photo_uploads`
- `asset_scan_logs`
- `asset_movements`
- `asset_audit_logs`
- `asset_models`
- `folders`
- `asset_folders`
- `asset_workspaces`
- `asset_workspace_items`
- `asset_workspace_item_photos`
- `asset_workspace_item_scans`

## Seeder Default

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
- Upload foto sementara disimpan di `writable/uploads/tmp`, lalu dipindah ke `writable/uploads/assets` saat asset berhasil dibuat atau saat foto baru ditambahkan ke asset existing.
- `asset_photos.file_size_bytes` dibatasi dengan check constraint `<= 1048576` sesuai requirement foto maksimal 1 MB.
- `asset_photo_uploads.file_size_bytes` juga dibatasi `<= 1048576`.
- `assets.serial_number` dibuat unik secara database.
- `asset_folders` memakai primary key gabungan `asset_id + folder_id` untuk mencegah relasi duplikat.
- Panduan request dan contoh payload yang lebih lengkap ada di [`docs/api-usage.md`](docs/api-usage.md).
