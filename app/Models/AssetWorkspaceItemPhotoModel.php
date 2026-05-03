<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetWorkspaceItemPhotoModel extends Model
{
    protected $table          = 'asset_workspace_item_photos';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'workspace_item_id',
        'file_name',
        'disk',
        'file_path',
        'mime_type',
        'extension',
        'file_size_bytes',
        'width',
        'height',
        'sha256_checksum',
        'is_primary',
        'uploaded_by',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function findForWorkspaceItem(int $workspaceItemId): array
    {
        return $this->where('workspace_item_id', $workspaceItemId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function findWorkspaceItemPhoto(int $workspaceItemId, int $photoId): ?array
    {
        return $this->where('workspace_item_id', $workspaceItemId)
            ->where('id', $photoId)
            ->first();
    }

    public function countForWorkspaceItem(int $workspaceItemId): int
    {
        return $this->builder()
            ->where('workspace_item_id', $workspaceItemId)
            ->countAllResults();
    }
}
