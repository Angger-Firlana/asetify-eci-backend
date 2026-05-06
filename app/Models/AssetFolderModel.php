<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetFolderModel extends Model
{
    protected $table          = 'asset_folders';
    protected $primaryKey     = 'asset_id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'asset_id',
        'folder_id',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function findFoldersForAsset(int $assetId): array
    {
        return $this->builder()
            ->select([
                'folders.id',
                'folders.name',
                'folders.type',
                'folders.parent_id',
                'parent.name AS parent_name',
                'asset_folders.created_at AS assigned_at',
            ])
            ->join('folders', 'folders.id = asset_folders.folder_id')
            ->join('folders parent', 'parent.id = folders.parent_id', 'left')
            ->where('asset_folders.asset_id', $assetId)
            ->orderBy('folders.type', 'ASC')
            ->orderBy('folders.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function findFolderIdsForAsset(int $assetId): array
    {
        $rows = $this->builder()
            ->select('folder_id')
            ->where('asset_id', $assetId)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['folder_id'], $rows);
    }

    public function countAssetsByFolderIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $rows = $this->builder()
            ->select('folder_id, COUNT(*) AS total')
            ->whereIn('folder_id', $folderIds)
            ->groupBy('folder_id')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['folder_id']] = (int) $row['total'];
        }

        return $counts;
    }
}
