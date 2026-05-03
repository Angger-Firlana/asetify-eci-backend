<?php

namespace App\Services;

use App\Models\AssetCategoryModel;
use App\Models\AssetAuditLogModel;
use App\Models\BrandModel;
use App\Models\AssetModel;
use App\Models\AssetMovementModel;
use App\Models\AssetScanLogModel;
use App\Models\AssetWorkspaceItemModel;
use App\Models\AssetWorkspaceItemScanModel;
use App\Models\AssetWorkspaceModel;
use App\Models\LocationModel;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class AssetWorkspaceService
{
    public function createWorkspace(array $payload, User $user): array
    {
        $workspaceModel = model(AssetWorkspaceModel::class);
        $now            = gmdate('Y-m-d H:i:s');

        $this->assertLocationExists((int) $payload['source_location_id'], 'source_location_id');
        $this->assertLocationExists((int) $payload['target_location_id'], 'target_location_id');

        if ((int) $payload['source_location_id'] === (int) $payload['target_location_id']) {
            throw new RuntimeException('source_location_id and target_location_id must be different.');
        }

        $status = $payload['status'] ?? 'active';

        if (! $workspaceModel->insert([
            'workspace_code'      => $this->generateWorkspaceCode(),
            'title'               => trim((string) $payload['title']),
            'source_location_id'  => (int) $payload['source_location_id'],
            'target_location_id'  => (int) $payload['target_location_id'],
            'status'              => $status,
            'notes'               => $payload['notes'] ?? null,
            'created_by'          => (int) $user->id,
            'closed_at'           => in_array($status, ['completed', 'cancelled'], true) ? $now : null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ])) {
            throw new RuntimeException('Failed to create workspace.');
        }

        $workspaceId = (int) $workspaceModel->getInsertID();

        return $workspaceModel->findDetail($workspaceId) ?? [];
    }

    public function scanIntoWorkspace(int $workspaceId, array $payload, User $user): array
    {
        $db                 = db_connect();
        $workspaceModel     = model(AssetWorkspaceModel::class);
        $workspaceItemModel = model(AssetWorkspaceItemModel::class);
        $workspaceScanModel = model(AssetWorkspaceItemScanModel::class);
        $assetModel         = model(AssetModel::class);
        $assetScanLogModel  = model(AssetScanLogModel::class);
        $now                = gmdate('Y-m-d H:i:s');
        $serialNumber       = strtoupper(trim((string) $payload['serial_number']));
        $workspace          = $workspaceModel->findDetail($workspaceId);

        if ($workspace === null) {
            throw new RuntimeException('Workspace not found.');
        }

        $this->assertWorkspaceCanReceive($workspace);

        if (isset($payload['source_location_id'])) {
            $this->assertLocationExists((int) $payload['source_location_id'], 'source_location_id');
        }

        if (isset($payload['target_location_id'])) {
            $this->assertLocationExists((int) $payload['target_location_id'], 'target_location_id');
        }

        if (isset($payload['asset_category_id'])) {
            $this->assertAssetCategoryExists((int) $payload['asset_category_id']);
        }

        if (isset($payload['brand_id'])) {
            $this->assertBrandExists((int) $payload['brand_id']);
        }

        $asset        = $assetModel->findActiveBySerialNumber($serialNumber);
        $existingItem = $workspaceItemModel->findByWorkspaceAndSerialNumber($workspaceId, $serialNumber);

        $db->transBegin();

        try {
            if ($asset !== null) {
                $this->syncMatchedAsset($asset, $workspace, $payload, (int) $user->id, $now);
            }

            $itemData = [
                'workspace_id'       => $workspaceId,
                'serial_number'      => $serialNumber,
                'asset_id'           => $asset['id'] ?? null,
                'match_status'       => $asset !== null ? 'matched' : 'not_found',
                'action_status'      => $asset !== null ? 'asset_updated' : 'ready_to_register',
                'asset_category_id'  => $asset['asset_category_id'] ?? (isset($payload['asset_category_id']) ? (int) $payload['asset_category_id'] : ($existingItem['asset_category_id'] ?? null)),
                'brand_id'           => $asset['brand_id'] ?? (isset($payload['brand_id']) ? (int) $payload['brand_id'] : ($existingItem['brand_id'] ?? null)),
                'model_name'         => $asset['model_name'] ?? ($payload['model_name'] ?? ($existingItem['model_name'] ?? null)),
                'source_location_id' => isset($payload['source_location_id']) ? (int) $payload['source_location_id'] : ($existingItem['source_location_id'] ?? (int) $workspace['source_location_id']),
                'target_location_id' => isset($payload['target_location_id']) ? (int) $payload['target_location_id'] : ($existingItem['target_location_id'] ?? (int) $workspace['target_location_id']),
                'condition_status'   => $payload['condition_status'] ?? ($asset['condition_status'] ?? ($existingItem['condition_status'] ?? null)),
                'notes'              => $payload['notes'] ?? ($existingItem['notes'] ?? null),
                'scanned_by'         => (int) $user->id,
                'scan_method'        => $payload['scan_method'],
                'last_scan_at'       => $now,
                'synced_at'          => $asset !== null ? $now : null,
                'updated_at'         => $now,
            ];

            if ($existingItem === null) {
                $itemData['created_at'] = $now;

                if (! $workspaceItemModel->insert($itemData)) {
                    throw new RuntimeException('Failed to save workspace item.');
                }

                $workspaceItemId = (int) $workspaceItemModel->getInsertID();
            } else {
                if (! $workspaceItemModel->update((int) $existingItem['id'], $itemData)) {
                    throw new RuntimeException('Failed to update workspace item.');
                }

                $workspaceItemId = (int) $existingItem['id'];
            }

            $workspaceScanRow = [
                'workspace_item_id' => $workspaceItemId,
                'workspace_id'      => $workspaceId,
                'serial_number'     => $serialNumber,
                'asset_id'          => $asset['id'] ?? null,
                'scanned_by'        => (int) $user->id,
                'scan_method'       => $payload['scan_method'],
                'result_status'     => $asset !== null ? 'matched' : 'not_found',
                'message'           => $asset !== null
                    ? 'Asset ditemukan di master aset dan dihubungkan ke workspace.'
                    : 'Asset belum terdaftar di master aset.',
                'device_info'       => $payload['device_info'] ?? null,
                'app_platform'      => $payload['app_platform'],
                'created_at'        => $now,
            ];

            if (! $workspaceScanModel->insert($workspaceScanRow)) {
                throw new RuntimeException('Failed to save workspace scan history.');
            }

            if (! $assetScanLogModel->insert([
                'serial_number' => $serialNumber,
                'asset_id'      => $asset['id'] ?? null,
                'scanned_by'    => (int) $user->id,
                'scan_method'   => $payload['scan_method'],
                'result_status' => $asset !== null ? 'success' : 'failed',
                'message'       => $workspaceScanRow['message'],
                'device_info'   => $payload['device_info'] ?? null,
                'app_platform'  => $payload['app_platform'],
                'created_at'    => $now,
            ])) {
                throw new RuntimeException('Failed to save global scan log.');
            }

            $workspaceModel->update($workspaceId, ['updated_at' => $now]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Workspace scan transaction failed.');
            }

            $db->transCommit();

            return $workspaceItemModel->findDetail($workspaceId, $workspaceItemId) ?? [];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function registerWorkspaceItemAsAsset(int $workspaceId, int $workspaceItemId, array $payload, User $user): array
    {
        $db                 = db_connect();
        $workspaceModel     = model(AssetWorkspaceModel::class);
        $workspaceItemModel = model(AssetWorkspaceItemModel::class);
        $workspaceScanModel = model(AssetWorkspaceItemScanModel::class);
        $now                = gmdate('Y-m-d H:i:s');
        $workspace          = $workspaceModel->findDetail($workspaceId);
        $workspaceItem      = $workspaceItemModel->find($workspaceItemId);

        if ($workspace === null) {
            throw new RuntimeException('Workspace not found.');
        }

        $this->assertWorkspaceCanReceive($workspace);

        if ($workspaceItem === null || (int) $workspaceItem['workspace_id'] !== $workspaceId) {
            throw new RuntimeException('Workspace item not found.');
        }

        if (! empty($workspaceItem['asset_id'])) {
            throw new RuntimeException('Workspace item is already linked to an asset.');
        }

        $assetPayload = [
            'serial_number'       => $workspaceItem['serial_number'],
            'asset_category_id'   => isset($payload['asset_category_id']) ? (int) $payload['asset_category_id'] : ($workspaceItem['asset_category_id'] ?? null),
            'brand_id'            => isset($payload['brand_id']) ? (int) $payload['brand_id'] : ($workspaceItem['brand_id'] ?? null),
            'model_name'          => $payload['model_name'] ?? ($workspaceItem['model_name'] ?? null),
            'source_location_id'  => isset($payload['source_location_id']) ? (int) $payload['source_location_id'] : ($workspaceItem['source_location_id'] ?? (int) $workspace['source_location_id']),
            'current_location_id' => isset($payload['current_location_id']) ? (int) $payload['current_location_id'] : ($workspaceItem['target_location_id'] ?? (int) $workspace['target_location_id']),
            'condition_status'    => $payload['condition_status'] ?? ($workspaceItem['condition_status'] ?? null),
            'notes'               => $payload['notes'] ?? ($workspaceItem['notes'] ?? null),
            'scan_method'         => $payload['scan_method'] ?? ($workspaceItem['scan_method'] ?? 'manual'),
            'app_platform'        => $payload['app_platform'] ?? 'web',
            'device_info'         => $payload['device_info'] ?? null,
            'photo_upload_ids'    => is_array($payload['photo_upload_ids'] ?? null)
                ? array_values(array_unique($payload['photo_upload_ids']))
                : [],
        ];

        $missingFields = [];
        foreach (['asset_category_id', 'brand_id', 'source_location_id', 'current_location_id', 'condition_status'] as $field) {
            if ($assetPayload[$field] === null || $assetPayload[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if ($missingFields !== []) {
            throw new RuntimeException('Workspace item is missing required asset fields: ' . implode(', ', $missingFields));
        }

        $db->transBegin();

        try {
            $asset = (new AssetService())->createAsset($assetPayload, (int) $user->id);

            if (! $workspaceItemModel->update($workspaceItemId, [
                'asset_id'           => (int) $asset['id'],
                'match_status'       => 'matched',
                'action_status'      => 'asset_registered',
                'asset_category_id'  => (int) $asset['asset_category_id'],
                'brand_id'           => (int) $asset['brand_id'],
                'model_name'         => $asset['model_name'] ?? null,
                'source_location_id' => (int) $asset['source_location_id'],
                'target_location_id' => (int) $asset['current_location_id'],
                'condition_status'   => $asset['condition_status'],
                'notes'              => $asset['notes'] ?? null,
                'scanned_by'         => (int) $user->id,
                'scan_method'        => $assetPayload['scan_method'],
                'synced_at'          => $now,
                'last_scan_at'       => $now,
                'updated_at'         => $now,
            ])) {
                throw new RuntimeException('Failed to link workspace item to created asset.');
            }

            if (! $workspaceScanModel->insert([
                'workspace_item_id' => $workspaceItemId,
                'workspace_id'      => $workspaceId,
                'serial_number'     => $workspaceItem['serial_number'],
                'asset_id'          => (int) $asset['id'],
                'scanned_by'        => (int) $user->id,
                'scan_method'       => $assetPayload['scan_method'],
                'result_status'     => 'asset_registered',
                'message'           => 'Workspace item dipromosikan ke master aset.',
                'device_info'       => $assetPayload['device_info'],
                'app_platform'      => $assetPayload['app_platform'],
                'created_at'        => $now,
            ])) {
                throw new RuntimeException('Failed to save workspace registration history.');
            }

            $workspaceModel->update($workspaceId, ['updated_at' => $now]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Workspace register transaction failed.');
            }

            $db->transCommit();

            return $workspaceItemModel->findDetail($workspaceId, $workspaceItemId) ?? [];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function syncMatchedAsset(array $asset, array $workspace, array $payload, int $userId, string $now): void
    {
        $assetModel      = model(AssetModel::class);
        $movementModel   = model(AssetMovementModel::class);
        $auditLogModel   = model(AssetAuditLogModel::class);
        $changes         = [];
        $targetLocation  = isset($payload['target_location_id']) ? (int) $payload['target_location_id'] : (int) $workspace['target_location_id'];

        if ((int) $asset['current_location_id'] !== $targetLocation) {
            $changes['current_location_id'] = [
                'old' => $asset['current_location_id'],
                'new' => $targetLocation,
            ];
        }

        if (! empty($payload['condition_status']) && $payload['condition_status'] !== ($asset['condition_status'] ?? null)) {
            $changes['condition_status'] = [
                'old' => $asset['condition_status'] ?? null,
                'new' => $payload['condition_status'],
            ];
        }

        if ($changes === []) {
            return;
        }

        $updateData = [
            'updated_by' => $userId,
            'updated_at' => $now,
        ];

        foreach ($changes as $field => $change) {
            $updateData[$field] = $change['new'];
        }

        if (! $assetModel->update((int) $asset['id'], $updateData)) {
            throw new RuntimeException('Failed to update matched asset from workspace.');
        }

        if (isset($changes['current_location_id'])) {
            $movementModel->insert([
                'asset_id'         => (int) $asset['id'],
                'from_location_id' => $changes['current_location_id']['old'],
                'to_location_id'   => $changes['current_location_id']['new'],
                'moved_by'         => $userId,
                'notes'            => 'Workspace receipt ' . $workspace['workspace_code'],
                'created_at'       => $now,
            ]);
        }

        foreach ($changes as $field => $change) {
            $auditLogModel->insert([
                'asset_id'      => (int) $asset['id'],
                'action'        => 'update',
                'changed_by'    => $userId,
                'change_source' => 'workspace_scan',
                'field_name'    => $field,
                'old_value'     => $change['old'] !== null ? (string) $change['old'] : null,
                'new_value'     => $change['new'] !== null ? (string) $change['new'] : null,
                'change_note'   => 'Asset updated from workspace receipt ' . $workspace['workspace_code'],
                'created_at'    => $now,
            ]);
        }
    }

    private function assertWorkspaceCanReceive(array $workspace): void
    {
        if (($workspace['status'] ?? null) !== 'active') {
            throw new RuntimeException('Workspace is not active.');
        }
    }

    private function assertLocationExists(int $locationId, string $fieldName): void
    {
        if (model(LocationModel::class)->find($locationId) === null) {
            throw new RuntimeException($fieldName . ' is invalid.');
        }
    }

    private function generateWorkspaceCode(): string
    {
        $workspaceModel = model(AssetWorkspaceModel::class);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'WS-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            if ($workspaceModel->builder()->where('workspace_code', $code)->countAllResults() === 0) {
                return $code;
            }
        }

        throw new RuntimeException('Failed to generate unique workspace code.');
    }

    private function assertAssetCategoryExists(int $assetCategoryId): void
    {
        if (model(AssetCategoryModel::class)->find($assetCategoryId) === null) {
            throw new RuntimeException('asset_category_id is invalid.');
        }
    }

    private function assertBrandExists(int $brandId): void
    {
        if (model(BrandModel::class)->find($brandId) === null) {
            throw new RuntimeException('brand_id is invalid.');
        }
    }
}
