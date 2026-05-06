# Panduan API Asetify ECI Backend

Dokumen ini menjelaskan alur penggunaan API backend Asetify ECI dari login sampai workspace receipt.

## Ringkasan Dasar

- Base path API: `/api/v1`
- Auth: `Bearer <access_token>`
- Format request body utama: JSON
- Upload foto sementara: `multipart/form-data`
- Response standar:

```json
{
  "success": true,
  "message": "Operation success",
  "data": {},
  "meta": {}
}
```

Jika gagal validasi atau authorization:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": [
      "Pesan error"
    ]
  }
}
```

## Role dan Akses

- `scanner`
  Scanner lapangan. Bisa login, list/detail asset, create asset, update field non-sensitif, create inline master tertentu, scan workspace, dan register asset dari workspace.
- `supervisor`
  Semua akses scanner ditambah edit field sensitif seperti `serial_number`, melihat audit log global, serta menambah/menghapus foto asset existing.
- `admin`
  Semua akses asset dan master data.

## Field Asset Yang Penting

Field inti asset saat ini:

- `serial_number`
- `asset_category_id`
- `brand_id`
- `model_name`
- `source_location_id`
- `current_location_id`
- `current_location_detail`
- `condition_status`
- `notes`

Catatan:

- `current_location_id` menyimpan lokasi utama, misalnya `Gudang Pusat`.
- `current_location_detail` menyimpan titik detail bebas, misalnya `Rak kanan`, `Laci kasir FA`, atau `HO lantai 2 lemari A`.

## 1. Auth

### Login

Endpoint:

```http
POST /api/v1/auth/login
Content-Type: application/json
```

Body:

```json
{
  "identity": "scanner01",
  "password": "Password123!"
}
```

Response sukses:

```json
{
  "success": true,
  "message": "Login success",
  "data": {
    "access_token": "....",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 3,
      "name": "scanner01",
      "role": "scanner"
    }
  }
}
```

### Profile aktif

```http
GET /api/v1/auth/me
Authorization: Bearer <token>
```

### Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer <token>
```

## 2. Upload Foto Sementara

Endpoint ini publik dan dipakai sebelum create asset atau add photo ke asset existing.

```http
POST /api/v1/uploads/photos
Content-Type: multipart/form-data
```

Form-data:

- `photo`: file gambar

Catatan:

- Maksimum ukuran file `1 MB`
- Hanya MIME `image/*`
- Upload sementara hanya bisa dipakai satu kali

Response:

```json
{
  "success": true,
  "message": "Photo uploaded",
  "data": {
    "upload_id": "upl_xxx",
    "file_name": "upl_xxx.png",
    "file_path": "tmp/2026/05/upl_xxx.png",
    "file_size_bytes": 12345
  }
}
```

## 3. Asset

### Check duplicate serial number

```http
GET /api/v1/assets/check-sn?serial_number=SN-001
Authorization: Bearer <token>
```

Dipakai untuk cek apakah serial number sudah ada, sekaligus membaca permission edit user saat ini.

### Create asset

```http
POST /api/v1/assets
Authorization: Bearer <token>
Content-Type: application/json
```

Body:

```json
{
  "serial_number": "SN-ECI-001",
  "asset_category_id": 2,
  "brand_id": 1,
  "model_name": "Latitude 5440",
  "source_location_id": 2,
  "current_location_id": 3,
  "current_location_detail": "Rak kanan dekat meja admin",
  "condition_status": "good",
  "notes": "Asset baru hasil penerimaan",
  "scan_method": "barcode",
  "app_platform": "web",
  "device_info": "Chrome Windows",
  "photo_upload_ids": [
    "upl_xxx",
    "upl_yyy"
  ]
}
```

Catatan:

- Boleh kirim `photo_upload_id` tunggal, backend akan mengubahnya menjadi array internal.
- Setelah asset dibuat, backend otomatis:
  - memindahkan foto dari folder temporary ke asset final
  - membuat movement awal
  - membuat scan log sukses

### List asset

```http
GET /api/v1/assets?page=1&per_page=20&search=dell
Authorization: Bearer <token>
```

Filter yang didukung:

- `serial_number`
- `search`
- `asset_category_id`
- `brand_id`
- `source_location_id`
- `current_location_id`
- `condition_status`
- `created_by`
- `date_from`
- `date_to`
- `sort_by`
- `sort_dir`

Search akan mencari:

- `serial_number`
- `brand`
- `asset_category`
- `source_location`
- `current_location`
- `model_name`
- `current_location_detail`

### Detail asset

```http
GET /api/v1/assets/{assetId}
Authorization: Bearer <token>
```

Response detail asset sekarang menyertakan:

- relasi brand/category/location
- `current_location_detail`
- daftar foto
- movement history
- permission flags per user

### Update asset

```http
PUT /api/v1/assets/{assetId}
Authorization: Bearer <token>
Content-Type: application/json
```

Contoh:

```json
{
  "current_location_id": 4,
  "current_location_detail": "Laci kasir FA",
  "notes": "Dipindah setelah stock opname",
  "scan_method": "manual",
  "app_platform": "web",
  "device_info": "Chrome Windows",
  "change_source": "manual_edit"
}
```

Catatan:

- `scanner` tidak boleh mengubah `serial_number`
- perubahan `current_location_id` membuat movement baru
- perubahan field apapun yang valid membuat audit log update

### List foto asset

```http
GET /api/v1/assets/{assetId}/photos
Authorization: Bearer <token>
```

### Tambah foto ke asset existing

```http
POST /api/v1/assets/{assetId}/photos
Authorization: Bearer <token>
Content-Type: application/json
```

Body:

```json
{
  "photo_upload_ids": ["upl_xxx"],
  "change_source": "manual_edit"
}
```

Catatan:

- Hanya `supervisor` dan `admin`

### Hapus foto asset

```http
DELETE /api/v1/assets/{assetId}/photos/{photoId}
Authorization: Bearer <token>
```

Catatan:

- Asset harus tetap punya minimal satu foto
- Jika foto primary dihapus, backend akan memilih primary baru

### Download foto asset

Endpoint ini publik:

```http
GET /api/v1/assets/{assetId}/download-photo/{photoId}
```

## 4. Export Asset ke Excel

```http
GET /api/v1/assets/export?include_images=true&brand_id=1&current_location_id=3
Authorization: Bearer <token>
```

Perilaku:

- mengembalikan file `.xlsx`
- filter sama dengan list asset
- jika `include_images=true`, primary photo akan di-embed ke sheet
- file export sekarang juga menyertakan kolom `Detail Lokasi Saat Ini`

## 5. Workspace Receipt

Flow workspace dipakai untuk penerimaan asset dari lokasi asal ke lokasi saat ini/tujuan.

### Konsep workspace

- Workspace punya konteks `source_location_id` dan `target_location_id`
- Saat scan item di dalam workspace, item boleh:
  - memakai default workspace
  - atau override per item memakai field asset seperti `source_location_id` dan `current_location_id`

### Buat workspace

```http
POST /api/v1/workspaces
Authorization: Bearer <token>
Content-Type: application/json
```

Body:

```json
{
  "title": "Penerimaan Gudang ke HO",
  "source_location_id": 2,
  "target_location_id": 3,
  "status": "active",
  "notes": "Penerimaan batch Mei"
}
```

Catatan:

- `source_location_id` dan `target_location_id` wajib berbeda
- workspace hanya bisa menerima scan jika status `active`

### List workspace

```http
GET /api/v1/workspaces?page=1&per_page=20&status=active&search=mei
Authorization: Bearer <token>
```

Catatan:

- user `scanner` hanya melihat workspace miliknya sendiri

### Detail workspace

```http
GET /api/v1/workspaces/{workspaceId}
Authorization: Bearer <token>
```

Response item workspace sekarang menampilkan field draft asset yang penting:

- `serial_number`
- `model_name`
- `current_location_detail`
- relasi `asset_category`
- relasi `brand`
- relasi `source_location`
- relasi `current_location`
- `matched_asset` jika item sudah terhubung ke asset master

### Scan ke workspace

```http
POST /api/v1/workspaces/{workspaceId}/scan
Authorization: Bearer <token>
Content-Type: application/json
```

Body yang direkomendasikan:

```json
{
  "serial_number": "WS-ECI-001",
  "scan_method": "barcode",
  "app_platform": "web",
  "device_info": "Chrome Windows",
  "asset_category_id": 2,
  "brand_id": 1,
  "model_name": "Latitude 5440",
  "source_location_id": 2,
  "current_location_id": 3,
  "current_location_detail": "Rak kanan HO",
  "condition_status": "good",
  "notes": "Diterima dalam kondisi baik"
}
```

Catatan penting:

- `current_location_id` adalah field utama yang dipakai untuk menyamakan payload workspace dengan payload asset.
- `target_location_id` masih diterima sebagai alias lama, tetapi nilainya harus sama jika dikirim bersamaan dengan `current_location_id`.
- Jika serial number belum ada di master asset, backend sekarang mengharuskan draft field berikut tersimpan di workspace item:
  - `asset_category_id`
  - `brand_id`
  - `model_name`
  - `source_location_id`
  - `current_location_id`
  - `condition_status`
- `current_location_detail` opsional tetapi direkomendasikan supaya titik fisik asset lebih presisi.

Perilaku scan:

- Jika asset sudah ada di master:
  - item workspace menjadi `matched`
  - `action_status` menjadi `asset_updated`
  - backend menyinkronkan `current_location_id`
  - backend juga menyinkronkan `current_location_detail` dan `condition_status` jika dikirim
- Jika asset belum ada di master:
  - item workspace menjadi `not_found`
  - `action_status` menjadi `ready_to_register`
  - semua draft field disimpan di workspace item untuk registrasi berikutnya

### Register workspace item menjadi asset master

```http
POST /api/v1/workspaces/{workspaceId}/items/{workspaceItemId}/register-asset
Authorization: Bearer <token>
Content-Type: application/json
```

Body minimal:

```json
{
  "app_platform": "web",
  "device_info": "Chrome Windows"
}
```

Jika ingin override field yang tersimpan di workspace item:

```json
{
  "model_name": "Latitude 5450",
  "current_location_detail": "Rak kiri HO",
  "notes": "Disusun ulang saat registrasi",
  "app_platform": "web",
  "device_info": "Chrome Windows"
}
```

Catatan:

- register akan gagal jika draft asset di workspace item masih kurang
- field `model_name` sekarang ikut diperlakukan sebagai bagian draft yang harus tersedia saat register asset baru

### Download foto workspace item

Endpoint ini publik:

```http
GET /api/v1/workspaces/items/{workspaceItemId}/download-photo/{photoId}
```

## 6. Master Data

Endpoint baca master:

- `GET /api/v1/masters/brands`
- `GET /api/v1/masters/models`
- `GET /api/v1/masters/asset-categories`
- `GET /api/v1/masters/types`
- `GET /api/v1/masters/locations`

Semua endpoint GET mendukung kombinasi filter berikut tergantung jenis master:

- `search`
- `id`
- `name`

Tambahan untuk model:

- `brand_id`

Tambahan untuk location:

- `location_type`

### Create brand

```http
POST /api/v1/masters/brands
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "name": "Asus"
}
```

### Create model

```http
POST /api/v1/masters/models
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "brand_id": 1,
  "name": "Latitude 5450"
}
```

### Create asset category / type

```http
POST /api/v1/masters/asset-categories
Authorization: Bearer <token>
Content-Type: application/json
```

atau

```http
POST /api/v1/masters/types
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "name": "Monitor"
}
```

### Create location

```http
POST /api/v1/masters/locations
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "name": "Gudang Kasir FA",
  "code": "gudang-kasir-fa",
  "location_type": "warehouse",
  "address": "Lantai 1",
  "is_active": 1
}
```

## 7. History dan Dashboard

### Create global scan log

```http
POST /api/v1/scan-logs
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "serial_number": "SN-ECI-001",
  "scan_method": "barcode",
  "result_status": "duplicate",
  "message": "Serial sudah terdaftar",
  "device_info": "Android 14",
  "app_platform": "android"
}
```

### List scan log

```http
GET /api/v1/scan-logs?serial_number=SN-ECI-001&date_from=2026-05-01&date_to=2026-05-31
Authorization: Bearer <token>
```

### Audit log per asset

```http
GET /api/v1/assets/{assetId}/audit-logs
Authorization: Bearer <token>
```

### Audit log global

```http
GET /api/v1/audit-logs
Authorization: Bearer <token>
```

Catatan:

- hanya `supervisor` dan `admin`

### Dashboard summary

```http
GET /api/v1/dashboard/summary
Authorization: Bearer <token>
```

## 8. Rekomendasi Flow Frontend

### Flow create asset biasa

1. Upload foto ke `POST /uploads/photos`
2. Simpan semua `upload_id`
3. Cek serial number via `GET /assets/check-sn`
4. Jika belum ada, kirim `POST /assets`
5. Simpan `photo_url`, `relations`, dan `current_location_detail` dari response

### Flow workspace untuk asset baru

1. Buat workspace
2. Scan item ke workspace sambil kirim draft asset lengkap
3. Ambil `workspaceItemId`
4. Saat user konfirmasi, panggil `register-asset`
5. Ambil asset master yang sudah terbentuk dari `matched_asset`

### Flow workspace untuk asset existing

1. Buat workspace
2. Scan serial asset existing
3. Jika perlu, kirim `current_location_id`, `current_location_detail`, dan `condition_status`
4. Backend akan update asset master otomatis dan tetap menyimpan jejak di workspace item

## 9. Verifikasi Lokal

Command yang relevan:

```bash
php spark migrate --all
php spark db:seed DatabaseSeeder
php spark db:seed DevelopmentUserSeeder
vendor\bin\phpunit tests\feature\Api
vendor\bin\phpunit tests\feature\CorsFilterTest.php
```
