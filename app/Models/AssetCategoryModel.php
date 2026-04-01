<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetCategoryModel extends Model
{
    protected $table          = 'asset_categories';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'asset_type_id',
        'code',
        'name',
        'is_active',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function active(): self
    {
        return $this->where('asset_categories.is_active', 1);
    }

    public function withType(): self
    {
        return $this
            ->select([
                'asset_categories.id',
                'asset_categories.asset_type_id',
                'asset_categories.code',
                'asset_categories.name',
                'asset_categories.is_active',
                'asset_types.name AS asset_type_name',
                'asset_types.code AS asset_type_code',
            ])
            ->join('asset_types', 'asset_types.id = asset_categories.asset_type_id', 'inner');
    }
}
