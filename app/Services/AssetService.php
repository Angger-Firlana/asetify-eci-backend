<?php

namespace App\Services;

use App\Models\AssetAuditLogModel;
use App\Models\AssetCategoryModel;
use App\Models\AssetModel;
use App\Models\AssetMovementModel;
use App\Models\AssetPhotoModel;
use App\Models\AssetPhotoUploadModel;
use App\Models\AssetScanLogModel;
use App\Models\BrandModel;
use App\Models\LocationModel;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class AssetService
{
    public function createAsset(array $payload, int $userId): array
    {
        $db               = db_connect();
        $assetModel       = model(AssetModel::class);
        $uploadModel      = model(AssetPhotoUploadModel::class);
        $photoModel       = model(AssetPhotoModel::class);
        $movementModel    = model(AssetMovementModel::class);
        $scanLogModel     = model(AssetScanLogModel::class);
        $auditLogModel    = model(AssetAuditLogModel::class);
        $photoUploadSvc   = new PhotoUploadService();
        $now              = gmdate('Y-m-d H:i:s');
        $serialNumber     = strtoupper(trim((string) $payload['serial_number']));
        $photoUploadIds   = array_values(array_unique($payload['photo_upload_ids']));
        $availableUploads = $uploadModel->findAvailableUploads($photoUploadIds, $userId);

        if ($assetModel->findActiveBySerialNumber($serialNumber) !== null) {
            throw new RuntimeException('Serial number already exists.');
        }

        if (count($availableUploads) !== count($photoUploadIds)) {
            throw new RuntimeException('One or more photo_upload_ids are invalid or already used.');
        }

        $this->assertForeignKeys($payload);

        $uploadsById = [];
        foreach ($availableUploads as $upload) {
            $uploadsById[$upload['upload_id']] = $upload;
        }

        $orderedUploads = [];
        foreach ($photoUploadIds as $uploadId) {
            $orderedUploads[] = $uploadsById[$uploadId];
        }

        $assetData = [
            'serial_number'       => $serialNumber,
            'asset_category_id'   => (int) $payload['asset_category_id'],
            'brand_id'            => (int) $payload['brand_id'],
            'model_name'          => $payload['model_name'] ?? null,
            'source_location_id'  => (int) $payload['source_location_id'],
            'current_location_id' => (int) $payload['current_location_id'],
            'condition_status'    => $payload['condition_status'],
            'notes'               => $payload['notes'] ?? null,
            'created_by'          => $userId,
            'updated_by'          => $userId,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        $copiedFiles = [];
        $tempFiles   = [];

        $db->transBegin();

        try {
            if (! $assetModel->insert($assetData)) {
                throw new RuntimeException('Failed to create asset.');
            }

            $assetId = (int) $assetModel->getInsertID();

            foreach ($orderedUploads as $index => $upload) {
                $source = $photoUploadSvc->absolutePath($upload['file_path']);
                if (! is_file($source)) {
                    throw new RuntimeException('Uploaded photo file is missing.');
                }

                $finalRelative = 'assets/' . gmdate('Y/m') . '/'
                    . sprintf('asset-%d-%02d.%s', $assetId, $index + 1, $upload['extension']);
                $finalAbsolute = $photoUploadSvc->absolutePath($finalRelative);

                $photoUploadSvc->ensureDirectory(dirname($finalAbsolute));

                if (! copy($source, $finalAbsolute)) {
                    throw new RuntimeException('Failed to persist photo file.');
                }

                $copiedFiles[] = $finalAbsolute;
                $tempFiles[]   = $source;

                if (! $photoModel->insert([
                    'asset_id'         => $assetId,
                    'file_name'        => basename($finalAbsolute),
                    'disk'             => 'local',
                    'file_path'        => $finalRelative,
                    'mime_type'        => $upload['mime_type'],
                    'extension'        => $upload['extension'],
                    'file_size_bytes'  => $upload['file_size_bytes'],
                    'width'            => $upload['width'],
                    'height'           => $upload['height'],
                    'sha256_checksum'  => $upload['sha256_checksum'],
                    'is_primary'       => $index === 0 ? 1 : 0,
                    'uploaded_by'      => $userId,
                    'created_at'       => $now,
                ])) {
                    throw new RuntimeException('Failed to attach photo to asset.');
                }

                $uploadModel->update($upload['id'], [
                    'asset_id'    => $assetId,
                    'file_path'   => $finalRelative,
                    'consumed_at' => $now,
                ]);
            }

            $movementModel->insert([
                'asset_id'         => $assetId,
                'from_location_id' => (int) $payload['source_location_id'],
                'to_location_id'   => (int) $payload['current_location_id'],
                'moved_by'         => $userId,
                'notes'            => 'Initial asset placement',
                'created_at'       => $now,
            ]);

            $scanLogModel->insert([
                'serial_number' => $serialNumber,
                'asset_id'      => $assetId,
                'scanned_by'    => $userId,
                'scan_method'   => $payload['scan_method'] ?? 'manual',
                'result_status' => 'success',
                'message'       => 'Asset created successfully',
                'device_info'   => $payload['device_info'] ?? null,
                'app_platform'  => $payload['app_platform'] ?? 'web',
                'created_at'    => $now,
            ]);

            foreach ($this->buildCreateAuditLogs($assetData, $assetId, $userId, $now) as $auditRow) {
                $auditLogModel->insert($auditRow);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Asset transaction failed.');
            }

            $db->transCommit();

            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }

            return $assetModel->findAssetDetail($assetId) ?? [];
        } catch (\Throwable $e) {
            $db->transRollback();

            foreach ($copiedFiles as $copiedFile) {
                if (is_file($copiedFile)) {
                    @unlink($copiedFile);
                }
            }

            throw $e;
        }
    }

    public function updateAsset(int $assetId, array $payload, User $user): array
    {
        $db               = db_connect();
        $assetModel       = model(AssetModel::class);
        $movementModel    = model(AssetMovementModel::class);
        $scanLogModel     = model(AssetScanLogModel::class);
        $auditLogModel    = model(AssetAuditLogModel::class);
        $authz            = new AssetAuthorizationService();
        $now              = gmdate('Y-m-d H:i:s');
        $currentAsset     = $assetModel->find($assetId);

        if ($currentAsset === null) {
            throw new RuntimeException('Asset not found.');
        }

        if (! $authz->canUpdateAsset($user)) {
            throw new RuntimeException('You are not allowed to update this asset.');
        }

        if (isset($payload['serial_number'])) {
            $payload['serial_number'] = strtoupper(trim((string) $payload['serial_number']));

            if ($payload['serial_number'] !== ($currentAsset['serial_number'] ?? '') && ! $authz->canEditSerialNumber($user)) {
                throw new RuntimeException('You are not allowed to edit serial_number.');
            }

            $duplicate = $assetModel->findActiveBySerialNumber($payload['serial_number']);
            if ($duplicate !== null && (int) $duplicate['id'] !== $assetId) {
                throw new RuntimeException('Serial number already exists.');
            }
        }

        $foreignKeyFields = array_intersect_key($payload, array_flip([
            'asset_category_id',
            'brand_id',
            'source_location_id',
            'current_location_id',
        ]));

        if ($foreignKeyFields !== []) {
            $validationPayload = array_merge($currentAsset, $payload);
            $this->assertForeignKeys($validationPayload);
        }

        $updatableFields = [
            'serial_number',
            'asset_category_id',
            'brand_id',
            'model_name',
            'source_location_id',
            'current_location_id',
            'condition_status',
            'notes',
        ];

        $authz->assertAllowedFields($user, array_intersect_key($payload, array_flip($updatableFields)));

        $changes = [];
        foreach ($updatableFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $newValue = $payload[$field];
            $oldValue = $currentAsset[$field] ?? null;

            if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        if ($changes === []) {
            return $assetModel->findAssetDetail($assetId) ?? [];
        }

        $updateData = ['updated_by' => (int) $user->id, 'updated_at' => $now];
        foreach ($changes as $field => $change) {
            $updateData[$field] = $change['new'];
        }

        $db->transBegin();

        try {
            if (! $assetModel->update($assetId, $updateData)) {
                throw new RuntimeException('Failed to update asset.');
            }

            if (array_key_exists('current_location_id', $changes)) {
                $movementModel->insert([
                    'asset_id'         => $assetId,
                    'from_location_id' => $changes['current_location_id']['old'],
                    'to_location_id'   => $changes['current_location_id']['new'],
                    'moved_by'         => (int) $user->id,
                    'notes'            => 'Asset location updated',
                    'created_at'       => $now,
                ]);
            }

            $scanLogModel->insert([
                'serial_number' => $updateData['serial_number'] ?? $currentAsset['serial_number'],
                'asset_id'      => $assetId,
                'scanned_by'    => (int) $user->id,
                'scan_method'   => $payload['scan_method'] ?? 'manual',
                'result_status' => 'success',
                'message'       => 'Asset updated successfully',
                'device_info'   => $payload['device_info'] ?? null,
                'app_platform'  => $payload['app_platform'] ?? 'web',
                'created_at'    => $now,
            ]);

            foreach ($this->buildUpdateAuditLogs($changes, $assetId, (int) $user->id, $now, $payload['change_source'] ?? 'manual_edit') as $auditRow) {
                $auditLogModel->insert($auditRow);
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Asset update transaction failed.');
            }

            $db->transCommit();

            return $assetModel->findAssetDetail($assetId) ?? [];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function addPhotosToAsset(int $assetId, array $photoUploadIds, User $user, string $changeSource = 'manual_edit'): array
    {
        $db               = db_connect();
        $assetModel       = model(AssetModel::class);
        $uploadModel      = model(AssetPhotoUploadModel::class);
        $photoModel       = model(AssetPhotoModel::class);
        $auditLogModel    = model(AssetAuditLogModel::class);
        $photoUploadSvc   = new PhotoUploadService();
        $authz            = new AssetAuthorizationService();
        $now              = gmdate('Y-m-d H:i:s');
        $asset            = $assetModel->find($assetId);
        $photoUploadIds   = array_values(array_unique(array_filter($photoUploadIds, static fn ($value): bool => trim((string) $value) !== '')));
        $availableUploads = $uploadModel->findAvailableUploads($photoUploadIds, (int) $user->id);

        if ($asset === null) {
            throw new RuntimeException('Asset not found.');
        }

        if (! $authz->canManageExistingPhotos($user)) {
            throw new RuntimeException('You are not allowed to manage existing photos.');
        }

        if ($photoUploadIds === []) {
            throw new RuntimeException('At least one photo_upload_id is required.');
        }

        if (count($availableUploads) !== count($photoUploadIds)) {
            throw new RuntimeException('One or more photo_upload_ids are invalid or already used.');
        }

        $uploadsById = [];
        foreach ($availableUploads as $upload) {
            $uploadsById[$upload['upload_id']] = $upload;
        }

        $orderedUploads = [];
        foreach ($photoUploadIds as $uploadId) {
            $orderedUploads[] = $uploadsById[$uploadId];
        }

        $copiedFiles = [];
        $tempFiles   = [];
        $addedPhotos = [];
        $isFirstPhoto = $photoModel->countForAsset($assetId) === 0;

        $db->transBegin();

        try {
            foreach ($orderedUploads as $index => $upload) {
                $source = $photoUploadSvc->absolutePath($upload['file_path']);
                if (! is_file($source)) {
                    throw new RuntimeException('Uploaded photo file is missing.');
                }

                $finalRelative = $this->buildFinalPhotoRelativePath($assetId, $upload['extension']);
                $finalAbsolute = $photoUploadSvc->absolutePath($finalRelative);

                $photoUploadSvc->ensureDirectory(dirname($finalAbsolute));

                if (! copy($source, $finalAbsolute)) {
                    throw new RuntimeException('Failed to persist photo file.');
                }

                $copiedFiles[] = $finalAbsolute;
                $tempFiles[]   = $source;

                $photoData = [
                    'asset_id'         => $assetId,
                    'file_name'        => basename($finalAbsolute),
                    'disk'             => 'local',
                    'file_path'        => $finalRelative,
                    'mime_type'        => $upload['mime_type'],
                    'extension'        => $upload['extension'],
                    'file_size_bytes'  => $upload['file_size_bytes'],
                    'width'            => $upload['width'],
                    'height'           => $upload['height'],
                    'sha256_checksum'  => $upload['sha256_checksum'],
                    'is_primary'       => $isFirstPhoto && $index === 0 ? 1 : 0,
                    'uploaded_by'      => (int) $user->id,
                    'created_at'       => $now,
                ];

                if (! $photoModel->insert($photoData)) {
                    throw new RuntimeException('Failed to attach photo to asset.');
                }

                $photoId = (int) $photoModel->getInsertID();

                $uploadModel->update($upload['id'], [
                    'asset_id'    => $assetId,
                    'file_path'   => $finalRelative,
                    'consumed_at' => $now,
                ]);

                $photo = $photoModel->find($photoId);
                if ($photo === null) {
                    throw new RuntimeException('Failed to load attached photo.');
                }

                $addedPhotos[] = $photo;
                $auditLogModel->insert($this->buildPhotoAuditLog(
                    action: 'photo_add',
                    assetId: $assetId,
                    userId: (int) $user->id,
                    createdAt: $now,
                    source: $changeSource,
                    oldValue: null,
                    newValue: $this->serializePhotoAuditValue($photo),
                    note: 'Photo added to asset'
                ));
            }

            $assetModel->update($assetId, [
                'updated_by' => (int) $user->id,
                'updated_at' => $now,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Asset photo transaction failed.');
            }

            $db->transCommit();

            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }

            return $addedPhotos;
        } catch (\Throwable $e) {
            $db->transRollback();

            foreach ($copiedFiles as $copiedFile) {
                if (is_file($copiedFile)) {
                    @unlink($copiedFile);
                }
            }

            throw $e;
        }
    }

    public function deleteAssetPhoto(int $assetId, int $photoId, User $user, string $changeSource = 'manual_edit'): array
    {
        $db            = db_connect();
        $assetModel    = model(AssetModel::class);
        $photoModel    = model(AssetPhotoModel::class);
        $auditLogModel = model(AssetAuditLogModel::class);
        $authz         = new AssetAuthorizationService();
        $now           = gmdate('Y-m-d H:i:s');
        $asset         = $assetModel->find($assetId);

        if ($asset === null) {
            throw new RuntimeException('Asset not found.');
        }

        if (! $authz->canManageExistingPhotos($user)) {
            throw new RuntimeException('You are not allowed to manage existing photos.');
        }

        $photo = $photoModel->findAssetPhoto($assetId, $photoId);
        if ($photo === null) {
            throw new RuntimeException('Photo not found.');
        }

        $allPhotos = $photoModel->findForAsset($assetId);
        if (count($allPhotos) <= 1) {
            throw new RuntimeException('Asset must keep at least one photo.');
        }

        $nextPrimary = null;
        if ((int) $photo['is_primary'] === 1) {
            foreach ($allPhotos as $candidate) {
                if ((int) $candidate['id'] === $photoId) {
                    continue;
                }

                $nextPrimary = $candidate;
                break;
            }
        }

        $absolutePath = (new PhotoUploadService())->absolutePath($photo['file_path']);

        $db->transBegin();

        try {
            if ((int) $photo['is_primary'] === 1 && $nextPrimary !== null) {
                $photoModel->assignPrimaryPhoto($assetId, (int) $nextPrimary['id']);
            }

            if (! $photoModel->delete($photoId)) {
                throw new RuntimeException('Failed to delete photo.');
            }

            $assetModel->update($assetId, [
                'updated_by' => (int) $user->id,
                'updated_at' => $now,
            ]);

            $auditLogModel->insert($this->buildPhotoAuditLog(
                action: 'photo_delete',
                assetId: $assetId,
                userId: (int) $user->id,
                createdAt: $now,
                source: $changeSource,
                oldValue: $this->serializePhotoAuditValue($photo),
                newValue: null,
                note: 'Photo deleted from asset'
            ));

            if ($db->transStatus() === false) {
                throw new RuntimeException('Asset photo delete transaction failed.');
            }

            $db->transCommit();

            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            return [
                'deleted_photo_id' => $photoId,
                'remaining_photos' => $photoModel->findForAsset($assetId),
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function assertForeignKeys(array $payload): void
    {
        $assetCategory = model(AssetCategoryModel::class)->find((int) $payload['asset_category_id']);
        if ($assetCategory === null) {
            throw new RuntimeException('asset_category_id is invalid.');
        }

        if (model(BrandModel::class)->find((int) $payload['brand_id']) === null) {
            throw new RuntimeException('brand_id is invalid.');
        }

        if (model(LocationModel::class)->find((int) $payload['source_location_id']) === null) {
            throw new RuntimeException('source_location_id is invalid.');
        }

        if (model(LocationModel::class)->find((int) $payload['current_location_id']) === null) {
            throw new RuntimeException('current_location_id is invalid.');
        }
    }

    private function buildCreateAuditLogs(array $assetData, int $assetId, int $userId, string $createdAt): array
    {
        $fields = [
            'serial_number',
            'asset_category_id',
            'brand_id',
            'model_name',
            'source_location_id',
            'current_location_id',
            'condition_status',
            'notes',
        ];

        $rows = [];

        foreach ($fields as $field) {
            $rows[] = [
                'asset_id'      => $assetId,
                'action'        => 'create',
                'changed_by'    => $userId,
                'change_source' => 'scan_flow',
                'field_name'    => $field,
                'old_value'     => null,
                'new_value'     => $assetData[$field] !== null ? (string) $assetData[$field] : null,
                'change_note'   => 'Initial asset creation',
                'created_at'    => $createdAt,
            ];
        }

        return $rows;
    }

    private function buildUpdateAuditLogs(array $changes, int $assetId, int $userId, string $createdAt, string $source): array
    {
        $rows = [];

        foreach ($changes as $field => $change) {
            $rows[] = [
                'asset_id'      => $assetId,
                'action'        => 'update',
                'changed_by'    => $userId,
                'change_source' => $source,
                'field_name'    => $field,
                'old_value'     => $change['old'] !== null ? (string) $change['old'] : null,
                'new_value'     => $change['new'] !== null ? (string) $change['new'] : null,
                'change_note'   => 'Asset updated',
                'created_at'    => $createdAt,
            ];
        }

        return $rows;
    }

    private function buildFinalPhotoRelativePath(int $assetId, string $extension): string
    {
        return 'assets/' . gmdate('Y/m') . '/'
            . sprintf('asset-%d-%s.%s', $assetId, bin2hex(random_bytes(6)), strtolower($extension));
    }

    private function buildPhotoAuditLog(
        string $action,
        int $assetId,
        int $userId,
        string $createdAt,
        string $source,
        ?string $oldValue,
        ?string $newValue,
        string $note
    ): array {
        return [
            'asset_id'      => $assetId,
            'action'        => $action,
            'changed_by'    => $userId,
            'change_source' => $source,
            'field_name'    => 'photo',
            'old_value'     => $oldValue,
            'new_value'     => $newValue,
            'change_note'   => $note,
            'created_at'    => $createdAt,
        ];
    }

    private function serializePhotoAuditValue(array $photo): string
    {
        return json_encode([
            'id' => (int) $photo['id'],
            'file_name' => $photo['file_name'],
            'file_path' => $photo['file_path'],
            'sha256_checksum' => $photo['sha256_checksum'],
            'is_primary' => (int) $photo['is_primary'] === 1,
        ], JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
