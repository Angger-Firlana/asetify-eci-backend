<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetPhotoUploadModel extends Model
{
    protected $table          = 'asset_photo_uploads';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'upload_id',
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
        'uploaded_by',
        'created_at',
        'expires_at',
        'consumed_at',
    ];
    protected $useTimestamps = false;

    public function findAvailableUploads(array $uploadIds, int $uploadedBy): array
    {
        if ($uploadIds === []) {
            return [];
        }

        return $this->whereIn('upload_id', $uploadIds)
            ->where('uploaded_by', $uploadedBy)
            ->where('consumed_at', null)
            ->findAll();
    }
}
