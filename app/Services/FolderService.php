<?php

namespace App\Services;

use App\Models\AssetFolderModel;
use App\Models\AssetModel;
use App\Models\FolderModel;
use RuntimeException;

class FolderService
{
    public function createFolder(array $payload): array
    {
        $folderModel = model(FolderModel::class);
        $now         = gmdate('Y-m-d H:i:s');
        $name        = $this->normalizeName((string) ($payload['name'] ?? ''));
        $type        = $this->normalizeType($payload['type'] ?? null);
        $parentId    = $this->normalizeParentId($payload['parent_id'] ?? null);

        $this->assertParentExists($parentId);
        $this->assertFolderUniqueness($name, $type, $parentId);

        if (! $folderModel->insert([
            'name'       => $name,
            'type'       => $type,
            'parent_id'  => $parentId,
            'created_at' => $now,
            'updated_at' => $now,
        ])) {
            throw new RuntimeException('Failed to create folder.');
        }

        return $folderModel->findWithRelations((int) $folderModel->getInsertID()) ?? [];
    }

    public function updateFolder(int $folderId, array $payload): array
    {
        $folderModel = model(FolderModel::class);
        $folder      = $folderModel->find($folderId);

        if ($folder === null) {
            throw new RuntimeException('Folder not found.');
        }

        $name     = array_key_exists('name', $payload) ? $this->normalizeName((string) $payload['name']) : (string) $folder['name'];
        $type     = array_key_exists('type', $payload) ? $this->normalizeType($payload['type']) : $this->normalizeType($folder['type'] ?? null);
        $parentId = array_key_exists('parent_id', $payload) ? $this->normalizeParentId($payload['parent_id']) : ($folder['parent_id'] !== null ? (int) $folder['parent_id'] : null);

        $this->assertParentExists($parentId);
        $this->assertNoFolderCycle($folderId, $parentId);
        $this->assertFolderUniqueness($name, $type, $parentId, $folderId);

        if (! $folderModel->update($folderId, [
            'name'       => $name,
            'type'       => $type,
            'parent_id'  => $parentId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('Failed to update folder.');
        }

        return $folderModel->findWithRelations($folderId) ?? [];
    }

    public function deleteFolder(int $folderId): void
    {
        $folderModel = model(FolderModel::class);
        if ($folderModel->find($folderId) === null) {
            throw new RuntimeException('Folder not found.');
        }

        if (! $folderModel->delete($folderId)) {
            throw new RuntimeException('Failed to delete folder.');
        }
    }

    public function syncAssetFolders(int $assetId, array $folderIds): array
    {
        return $this->replaceAssetFolders($assetId, $folderIds, true);
    }

    public function addFoldersToAsset(int $assetId, array $folderIds): array
    {
        return $this->replaceAssetFolders($assetId, $folderIds, false);
    }

    public function removeFolderFromAsset(int $assetId, int $folderId): array
    {
        $this->assertAssetExists($assetId);
        $this->assertFolderIdsExist([$folderId]);

        model(AssetFolderModel::class)->builder()
            ->where('asset_id', $assetId)
            ->where('folder_id', $folderId)
            ->delete();

        return $this->foldersForAsset($assetId);
    }

    public function addAssetsToFolder(int $folderId, array $assetIds): array
    {
        $this->assertFolderIdsExist([$folderId]);
        $assetIds = $this->normalizeIdList($assetIds);
        if ($assetIds === []) {
            throw new RuntimeException('At least one asset_id is required.');
        }

        foreach ($assetIds as $assetId) {
            $this->assertAssetExists($assetId);
        }

        $pivotModel = model(AssetFolderModel::class);
        $existing   = $pivotModel->builder()
            ->select('asset_id')
            ->where('folder_id', $folderId)
            ->whereIn('asset_id', $assetIds)
            ->get()
            ->getResultArray();

        $existingIds = array_map(static fn (array $row): int => (int) $row['asset_id'], $existing);
        $now         = gmdate('Y-m-d H:i:s');

        foreach ($assetIds as $assetId) {
            if (in_array($assetId, $existingIds, true)) {
                continue;
            }

            $pivotModel->insert([
                'asset_id'   => $assetId,
                'folder_id'  => $folderId,
                'created_at' => $now,
            ]);
        }

        return $this->folderDetail($folderId);
    }

    public function foldersForAsset(int $assetId): array
    {
        $this->assertAssetExists($assetId);

        return array_map([$this, 'formatAssetFolder'], model(AssetFolderModel::class)->findFoldersForAsset($assetId));
    }

    public function membershipsForAssets(array $assetIds, ?string $type = null): array
    {
        $assetIds = $this->normalizeIdList($assetIds);
        if ($assetIds === []) {
            throw new RuntimeException('At least one asset_id is required.');
        }

        $builder = model(AssetFolderModel::class)->builder();
        $builder->select([
            'asset_folders.asset_id',
            'asset_folders.folder_id',
        ]);
        $builder->join('folders', 'folders.id = asset_folders.folder_id');
        $builder->whereIn('asset_folders.asset_id', $assetIds);

        $type = $this->normalizeType($type);
        if ($type !== null) {
            $builder->where('folders.type', $type);
        }

        $rows = $builder->get()->getResultArray();

        $map = [];
        foreach ($assetIds as $assetId) {
            $map[(string) $assetId] = [];
        }

        foreach ($rows as $row) {
            $assetId  = (int) ($row['asset_id'] ?? 0);
            $folderId = (int) ($row['folder_id'] ?? 0);
            if ($assetId <= 0 || $folderId <= 0) {
                continue;
            }

            $key = (string) $assetId;
            if (! array_key_exists($key, $map)) {
                $map[$key] = [];
            }

            $map[$key][] = $folderId;
        }

        foreach ($map as $key => $folderIds) {
            $folderIds = array_values(array_unique(array_map('intval', $folderIds)));
            sort($folderIds);
            $map[$key] = $folderIds;
        }

        return $map;
    }

    public function listFolders(array $filters = []): array
    {
        $rows          = $this->queryFolders($filters);
        $folderIds     = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $assetCounts   = model(AssetFolderModel::class)->countAssetsByFolderIds($folderIds);
        $childrenCount = $this->countChildren($rows);

        return array_map(function (array $row) use ($assetCounts, $childrenCount): array {
            $id = (int) $row['id'];

            return $this->formatFolder($row, $assetCounts[$id] ?? 0, $childrenCount[$id] ?? 0);
        }, $rows);
    }

    public function folderTree(array $filters = []): array
    {
        $folders       = $this->listFolders($filters);
        $byParent      = [];
        $itemsById     = [];

        foreach ($folders as $folder) {
            $folder['children'] = [];
            $itemsById[$folder['id']] = $folder;
        }

        foreach ($itemsById as $folderId => $folder) {
            $parentId = $folder['parent_id'];
            $byParent[$parentId ?? 0][] = $folderId;
        }

        $buildNode = function (int $folderId) use (&$buildNode, &$itemsById, $byParent): array {
            $item = $itemsById[$folderId];
            foreach ($byParent[$folderId] ?? [] as $childId) {
                $item['children'][] = $buildNode($childId);
            }

            return $item;
        };

        $tree = [];
        foreach ($byParent[0] ?? [] as $rootId) {
            $tree[] = $buildNode($rootId);
        }

        return $tree;
    }

    public function folderDetail(int $folderId): array
    {
        $folder = model(FolderModel::class)->findWithRelations($folderId);
        if ($folder === null) {
            throw new RuntimeException('Folder not found.');
        }

        $assetCount   = model(AssetFolderModel::class)->countAssetsByFolderIds([$folderId])[$folderId] ?? 0;
        $childrenRows = model(FolderModel::class)->where('parent_id', $folderId)->findAll();
        $breadcrumbs  = $this->buildBreadcrumbs($folderId);

        return array_merge(
            $this->formatFolder($folder, $assetCount, count($childrenRows)),
            [
                'breadcrumbs' => $breadcrumbs,
            ]
        );
    }

    private function replaceAssetFolders(int $assetId, array $folderIds, bool $replaceAll): array
    {
        $this->assertAssetExists($assetId);

        $folderIds = $this->normalizeIdList($folderIds);
        $this->assertFolderIdsExist($folderIds);

        $pivotModel       = model(AssetFolderModel::class);
        $currentFolderIds = $pivotModel->findFolderIdsForAsset($assetId);
        $targetFolderIds  = $replaceAll
            ? $folderIds
            : array_values(array_unique(array_merge($currentFolderIds, $folderIds)));

        $toDelete = array_diff($currentFolderIds, $targetFolderIds);
        $toInsert = array_diff($targetFolderIds, $currentFolderIds);

        if ($toDelete !== []) {
            $pivotModel->builder()
                ->where('asset_id', $assetId)
                ->whereIn('folder_id', $toDelete)
                ->delete();
        }

        $now = gmdate('Y-m-d H:i:s');
        foreach ($toInsert as $folderId) {
            $pivotModel->insert([
                'asset_id'   => $assetId,
                'folder_id'  => $folderId,
                'created_at' => $now,
            ]);
        }

        return $this->foldersForAsset($assetId);
    }

    private function queryFolders(array $filters): array
    {
        $builder = model(FolderModel::class)->queryWithRelations();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $builder->groupStart()
                ->like('folders.name', $search)
                ->orLike('folders.type', $search)
                ->groupEnd();
        }

        $type = $this->normalizeType($filters['type'] ?? null);
        if ($type !== null) {
            $builder->where('folders.type', $type);
        }

        if (array_key_exists('parent_id', $filters)) {
            $parentId = $this->normalizeParentId($filters['parent_id']);
            if ($parentId === null) {
                $builder->where('folders.parent_id IS NULL', null, false);
            } else {
                $builder->where('folders.parent_id', $parentId);
            }
        }

        if (! empty($filters['asset_id'])) {
            $builder->join('asset_folders', 'asset_folders.folder_id = folders.id');
            $builder->where('asset_folders.asset_id', (int) $filters['asset_id']);
        }

        return $builder
            ->orderBy('folders.type', 'ASC')
            ->orderBy('folders.name', 'ASC')
            ->findAll();
    }

    private function assertFolderUniqueness(string $name, ?string $type, ?int $parentId, ?int $ignoreId = null): void
    {
        $folderModel = model(FolderModel::class);
        if ($parentId === null) {
            $folderModel->where('parent_id IS NULL', null, false);
        } else {
            $folderModel->where('parent_id', $parentId);
        }

        $rows = $folderModel->findAll();

        foreach ($rows as $row) {
            if ($ignoreId !== null && (int) $row['id'] === $ignoreId) {
                continue;
            }

            $sameName = mb_strtolower(trim((string) $row['name'])) === mb_strtolower($name);
            $sameType = mb_strtolower(trim((string) ($row['type'] ?? ''))) === mb_strtolower((string) ($type ?? ''));

            if ($sameName && $sameType) {
                throw new RuntimeException('Folder with the same name, type, and parent already exists.');
            }
        }
    }

    private function assertParentExists(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if (model(FolderModel::class)->find($parentId) === null) {
            throw new RuntimeException('parent_id is invalid.');
        }
    }

    private function assertNoFolderCycle(int $folderId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($folderId === $parentId) {
            throw new RuntimeException('Folder cannot be its own parent.');
        }

        if (in_array($parentId, $this->collectDescendantIds($folderId), true)) {
            throw new RuntimeException('Folder parent creates a circular hierarchy.');
        }
    }

    private function collectDescendantIds(int $folderId): array
    {
        $allFolders = model(FolderModel::class)->findAll();
        $children   = [];

        foreach ($allFolders as $folder) {
            $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
            if ($parentId === null) {
                continue;
            }

            $children[$parentId][] = (int) $folder['id'];
        }

        $result = [];
        $stack  = $children[$folderId] ?? [];

        while ($stack !== []) {
            $childId   = array_pop($stack);
            $result[]  = $childId;
            foreach ($children[$childId] ?? [] as $grandChildId) {
                $stack[] = $grandChildId;
            }
        }

        return array_values(array_unique($result));
    }

    private function assertAssetExists(int $assetId): void
    {
        if (model(AssetModel::class)->find($assetId) === null) {
            throw new RuntimeException('Asset not found.');
        }
    }

    private function assertFolderIdsExist(array $folderIds): void
    {
        if ($folderIds === []) {
            return;
        }

        $rows = model(FolderModel::class)->whereIn('id', $folderIds)->findAll();
        $foundIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);

        foreach ($folderIds as $folderId) {
            if (! in_array($folderId, $foundIds, true)) {
                throw new RuntimeException('One or more folder_ids are invalid.');
            }
        }
    }

    private function normalizeIdList(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Folder name is required.');
        }

        return $name;
    }

    private function normalizeType(mixed $type): ?string
    {
        $type = trim((string) $type);

        return $type !== '' ? $type : null;
    }

    private function normalizeParentId(mixed $parentId): ?int
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        $normalized = (int) $parentId;
        if ($normalized <= 0) {
            throw new RuntimeException('parent_id is invalid.');
        }

        return $normalized;
    }

    private function countChildren(array $folders): array
    {
        $counts = [];
        foreach ($folders as $folder) {
            $parentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : null;
            if ($parentId === null) {
                continue;
            }

            $counts[$parentId] = ($counts[$parentId] ?? 0) + 1;
        }

        return $counts;
    }

    private function buildBreadcrumbs(int $folderId): array
    {
        $folderModel = model(FolderModel::class);
        $path        = [];
        $currentId   = $folderId;

        while ($currentId > 0) {
            $folder = $folderModel->find($currentId);
            if ($folder === null) {
                break;
            }

            $path[] = [
                'id'   => (int) $folder['id'],
                'name' => $folder['name'],
                'type' => $folder['type'] ?? null,
            ];

            $currentId = $folder['parent_id'] !== null ? (int) $folder['parent_id'] : 0;
        }

        return array_reverse($path);
    }

    private function formatFolder(array $row, int $assetCount, int $childrenCount): array
    {
        return [
            'id'             => (int) $row['id'],
            'name'           => $row['name'],
            'type'           => $row['type'] ?? null,
            'parent_id'      => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'parent'         => $row['parent_id'] !== null ? [
                'id'   => (int) $row['parent_id'],
                'name' => $row['parent_name'] ?? null,
                'type' => $row['parent_type'] ?? null,
            ] : null,
            'asset_count'    => $assetCount,
            'children_count' => $childrenCount,
            'created_at'     => $row['created_at'] ?? null,
            'updated_at'     => $row['updated_at'] ?? null,
        ];
    }

    private function formatAssetFolder(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'type'       => $row['type'] ?? null,
            'parent_id'  => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'parent'     => $row['parent_id'] !== null ? [
                'id'   => (int) $row['parent_id'],
                'name' => $row['parent_name'] ?? null,
            ] : null,
            'assigned_at' => $row['assigned_at'] ?? null,
        ];
    }
}
