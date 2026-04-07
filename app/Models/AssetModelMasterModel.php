<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetModelMasterModel extends Model
{
    protected $table          = 'asset_models';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'brand_id',
        'code',
        'name',
        'is_active',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function activeWithBrand(): self
    {
        return $this->select([
            'asset_models.id',
            'asset_models.brand_id',
            'asset_models.code',
            'asset_models.name',
            'asset_models.is_active',
            'asset_models.created_at',
            'asset_models.updated_at',
            'brands.name AS brand_name',
        ])
            ->join('brands', 'brands.id = asset_models.brand_id', 'left')
            ->where('asset_models.is_active', 1);
    }

    public function findWithBrand(int $id): ?array
    {
        return $this->select([
            'asset_models.id',
            'asset_models.brand_id',
            'asset_models.code',
            'asset_models.name',
            'asset_models.is_active',
            'asset_models.created_at',
            'asset_models.updated_at',
            'brands.name AS brand_name',
        ])
            ->join('brands', 'brands.id = asset_models.brand_id', 'left')
            ->where('asset_models.id', $id)
            ->first();
    }
}
