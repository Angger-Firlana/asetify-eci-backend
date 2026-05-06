# Catatan Penambahan Fitur Folder / Group Asset

Tanggal: 2026-05-06

## Yang Ditambahkan

- Tabel `folders` untuk menyimpan folder atau group asset dengan dukungan parent-child.
- Tabel pivot `asset_folders` untuk relasi many-to-many antara asset dan folder.
- Endpoint kelola folder, tree folder, assignment asset-folder, dan list asset per folder.
- Folder pada response detail asset.

## Yang Diubah

- Detail asset sekarang menyertakan field `folders`.
- Backend sekarang menyediakan endpoint sinkronisasi folder dari sisi asset agar UI multi-select lebih mudah dibuat.
- Backend juga menyediakan endpoint dari sisi folder untuk attach asset dan membaca asset di dalam satu folder.
- Validasi folder ditambah untuk mencegah:
  - duplicate folder dengan kombinasi `name + type + parent_id`
  - duplicate relasi `asset_id + folder_id`
  - cycle pada parent folder

## Dampak Perubahan

- Frontend bisa membangun fitur folder tree, chips folder pada detail asset, dan halaman asset per folder tanpa merangkai query manual.
- Pengelompokan asset sekarang fleksibel dan tidak mengubah struktur master asset yang sudah ada.

## File Yang Diubah

- `app/Config/AuthGroups.php`
- `app/Config/Routes.php`
- `app/Controllers/Api/V1/AssetController.php`
- `app/Controllers/Api/V1/FolderController.php`
- `app/Database/Migrations/2026-05-06-130000_CreateFolderTables.php`
- `app/Models/AssetFolderModel.php`
- `app/Models/FolderModel.php`
- `app/Services/FolderService.php`
- `tests/_support/ApiFeatureTestCase.php`
- `tests/feature/Api/FolderFlowTest.php`
- `README.md`
- `docs/api-usage.md`

## Verifikasi

- `vendor\bin\phpunit tests\feature\Api\FolderFlowTest.php`
- `vendor\bin\phpunit tests\feature\Api tests\feature\CorsFilterTest.php`
