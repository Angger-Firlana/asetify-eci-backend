<?php

namespace App\Models;

use CodeIgniter\Model;

class BrandModel extends Model
{
    protected $table          = 'brands';
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
        return $this->where('is_active', 1);
    }
}
