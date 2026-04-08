# Catatan Penambahan Endpoint Location

Tanggal: 2026-04-08

## Yang Ditambahkan

- Endpoint baru `POST /api/v1/masters/locations` untuk membuat master data location.
- Validasi hanya memerlukan bearer token tanpa permission khusus (tidak seperti endpoint master data lainnya yang memerlukan role tertentu).
- Mendukung field `name` (wajib), `code`, `location_type`, `address`, dan `is_active` (opsional).

## Yang Dilakukan

- Menambah route `POST /api/v1/masters/locations` di `app/Config/Routes.php`.
- Menambah method `storeLocation()` di `MasterDataController.php` dengan validasi token saja.
- Mengimplementasikan validasi input, normalisasi name/code, pengecekan uniqueness, dan penyimpanan data.
- Memperbarui dokumentasi di `README.md` untuk menyertakan endpoint baru dan catatan permission.

## Dampak Perubahan

- User dengan token valid dapat membuat location baru tanpa memerlukan permission khusus.
- Konsistensi dengan flow frontend untuk searchable field yang memungkinkan inline creation.

## File Yang Diubah

- `app/Config/Routes.php`
- `app/Controllers/Api/V1/MasterDataController.php`
- `README.md`