# Catatan Perubahan Flow Gambar

Tanggal: 2026-04-07

## Yang Diinginkan

- Gambar masih belum bisa tersimpan dengan baik di flow yang dipakai frontend.
- Fetch gambar diinginkan bisa dilakukan tanpa token.
- Fetch gambar juga tidak perlu validasi role.
- Perlu dokumentasi yang mencatat perubahan terakhir dan kebutuhan yang diminta.

## Yang Terakhir Dilakukan

- Membuka endpoint `GET /api/v1/assets/{assetId}/download-photo/{photoId}` agar bisa diakses tanpa token.
- Mengubah response download foto menjadi inline image response agar URL gambar bisa dipakai langsung oleh frontend.
- Membuka endpoint `POST /api/v1/uploads/photos` agar upload sementara bisa dilakukan tanpa token.
- Menambah dukungan payload `photo_upload_id` tunggal pada `POST /api/v1/assets`, selain `photo_upload_ids`.
- Melonggarkan lookup upload sementara agar tidak lagi bergantung pada `uploaded_by`, selama upload belum `consumed`.
- Menambah test untuk create asset dengan `photo_upload_id` tunggal.
- Menambah test untuk memastikan endpoint download foto bisa diakses publik.
- Menjalankan test berikut:
  - `php vendor\bin\phpunit tests\feature\Api\AuthAndAssetWorkflowTest.php`
  - `php vendor\bin\phpunit tests\feature\Api\AssetPhotoManagementTest.php`

## Dampak Perubahan

- Frontend bisa langsung memakai `photo_url` atau `download_url` tanpa menyisipkan bearer token.
- Flow create asset lebih toleran terhadap frontend yang mengirim satu `photo_upload_id` saja.
- Upload sementara yang dibuat tanpa sesi login tetap bisa dipakai saat create asset atau add photo, selama belum dipakai sebelumnya.

## File Yang Diubah

- `app/Config/Routes.php`
- `app/Controllers/Api/V1/AssetController.php`
- `app/Controllers/Api/V1/UploadController.php`
- `app/Models/AssetPhotoUploadModel.php`
- `tests/feature/Api/AuthAndAssetWorkflowTest.php`
- `README.md`

## Catatan Lanjutan

- Jika setelah perubahan ini frontend masih gagal menyimpan gambar, langkah berikutnya adalah capture payload request aktual dari frontend saat upload dan saat create asset untuk memastikan `upload_id` yang diterima backend sama dengan yang dikirim di request create.
