# Catatan Penambahan Flow Workspace

Tanggal: 2026-05-04

## Yang Ditambahkan

- Flow backend baru untuk workspace penerimaan aset.
- Endpoint list, create, detail, scan, dan register asset dari workspace.
- Tabel baru untuk `asset_workspaces`, `asset_workspace_items`, `asset_workspace_item_photos`, dan `asset_workspace_item_scans`.
- Field `photo_url` pada response item workspace, beserta endpoint download foto item workspace yang bisa dipanggil tanpa token.

## Yang Dilakukan

- Menambah route workspace di `app/Config/Routes.php`.
- Menambah `WorkspaceController` untuk list, create, detail, scan, register asset, dan download foto item workspace.
- Menambah `AssetWorkspaceService` untuk orkestrasi create workspace, sinkronisasi asset existing, dan registrasi asset baru dari item workspace.
- Menambah model untuk workspace, item, photo, dan scan history workspace.
- Menambah migration pembuatan tabel workspace beserta foreign key dan index utamanya.
- Menambah feature test untuk scan asset existing ke workspace, scan asset baru lalu register ke master asset, dan memastikan `photo_url` item workspace muncul pada detail workspace.
- Memperbarui `README.md` untuk mendokumentasikan flow workspace dan endpoint foto publik tambahan.

## Dampak Perubahan

- Frontend bisa membangun flow penerimaan aset berbasis workspace tanpa menulis logika sinkronisasi asset sendiri.
- Asset yang sudah ada di master dapat langsung diperbarui lokasinya saat diterima lewat workspace scan.
- Asset yang belum ada di master bisa ditahan dulu sebagai item workspace lalu dipromosikan menjadi asset baru.
- Frontend bisa langsung memakai `photo_url` item workspace tanpa bearer token jika primary photo item workspace tersedia.

## File Yang Diubah

- `app/Config/Routes.php`
- `app/Controllers/Api/V1/WorkspaceController.php`
- `app/Database/Migrations/2026-05-04-120000_CreateAssetWorkspaceTables.php`
- `app/Models/AssetWorkspaceItemModel.php`
- `app/Models/AssetWorkspaceItemPhotoModel.php`
- `app/Models/AssetWorkspaceItemScanModel.php`
- `app/Models/AssetWorkspaceModel.php`
- `app/Services/AssetWorkspaceService.php`
- `tests/_support/ApiFeatureTestCase.php`
- `tests/feature/Api/WorkspaceFlowTest.php`
- `README.md`
