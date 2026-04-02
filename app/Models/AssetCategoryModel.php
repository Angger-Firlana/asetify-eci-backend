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
}
