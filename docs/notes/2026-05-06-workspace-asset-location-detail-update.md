# Catatan Perubahan Workspace dan Detail Lokasi Asset

Tanggal: 2026-05-06

## Yang Ditambahkan

- Field baru `current_location_detail` pada asset.
- Field draft `current_location_detail` pada workspace item.
- Penyelarasan payload workspace agar bisa menerima `current_location_id` seperti payload asset.
- Dokumentasi penggunaan API yang lebih lengkap pada `docs/api-usage.md`.

## Yang Diubah

- Flow scan workspace untuk asset yang belum ada di master sekarang menyimpan draft field asset yang lebih lengkap.
- Flow register workspace item ke asset master sekarang ikut membawa `current_location_detail`.
- Flow sync workspace ke asset existing sekarang bisa memperbarui `current_location_detail`.
- Export Excel asset sekarang menyertakan kolom `Detail Lokasi Saat Ini`.
- Response workspace item sekarang menampilkan `model_name`, `current_location_detail`, dan relasi `current_location`.

## Dampak Perubahan

- Frontend bisa memakai form workspace yang lebih mendekati form asset final.
- Titik fisik asset di dalam satu lokasi besar bisa disimpan lebih presisi.
- Data yang discan di workspace lebih siap untuk langsung diregister menjadi asset master tanpa melengkapi ulang field penting.

## File Yang Diubah

- `app/Database/Migrations/2026-05-06-120000_AddLocationDetailsToAssetsAndWorkspaceItems.php`
- `app/Controllers/Api/V1/AssetController.php`
- `app/Controllers/Api/V1/WorkspaceController.php`
- `app/Models/AssetModel.php`
- `app/Models/AssetWorkspaceItemModel.php`
- `app/Services/AssetAuthorizationService.php`
- `app/Services/AssetExcelExportService.php`
- `app/Services/AssetService.php`
- `app/Services/AssetWorkspaceService.php`
- `tests/_support/ApiFeatureTestCase.php`
- `tests/feature/Api/AuthAndAssetWorkflowTest.php`
- `tests/feature/Api/WorkspaceFlowTest.php`
- `README.md`
- `docs/api-usage.md`

## Verifikasi

- `vendor\bin\phpunit tests\feature\Api tests\feature\CorsFilterTest.php`
