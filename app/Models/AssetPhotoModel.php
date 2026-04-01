<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetPhotoModel extends Model
{
    protected $table          = 'asset_photos';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'asset_id',
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

    public function findForAsset(int $assetId): array
    {
        return $this->where('asset_id', $assetId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function findAssetPhoto(int $assetId, int $photoId): ?array
    {
        return $this->where('asset_id', $assetId)
            ->where('id', $photoId)
            ->first();
    }

    public function countForAsset(int $assetId): int
    {
        return $this->builder()
            ->where('asset_id', $assetId)
            ->countAllResults();
    }

    public function assignPrimaryPhoto(int $assetId, int $photoId): void
    {
        $this->builder()
            ->where('asset_id', $assetId)
            ->update(['is_primary' => 0]);

        $this->builder()
            ->where('asset_id', $assetId)
            ->where('id', $photoId)
            ->update(['is_primary' => 1]);
    }
}
