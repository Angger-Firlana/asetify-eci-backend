# Asetify ECI Backend

Backend API Asetify berbasis CodeIgniter 4 untuk flow listing aset IT:

- check duplicate serial number
- upload foto bukti aset
- create dan update aset
- history scan
- audit log perubahan aset

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
php spark migrate
php spark db:seed DatabaseSeeder
```

## Tabel Yang Dibuat

Migration saat ini mencakup:

- `asset_types`
- `asset_categories`
- `brands`
- `locations`
- `assets`
- `asset_photos`
- `asset_scan_logs`
- `asset_movements`
- `asset_audit_logs`

Seeder awal mengisi contoh data untuk:

- tipe aset
- kategori aset
- brand
- lokasi

## Catatan

- Kolom seperti `created_by`, `updated_by`, `uploaded_by`, `scanned_by`, `moved_by`, dan `changed_by` sudah disiapkan untuk integrasi auth, tetapi foreign key ke tabel user belum dipasang karena package auth `CodeIgniter Shield` belum diinstal di repo ini.
- `asset_photos.file_size_bytes` dibatasi dengan check constraint `<= 1048576` sesuai requirement foto maksimal 1 MB.
- `assets.serial_number` dibuat unik secara database.
