<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetMovementModel extends Model
{
    protected $table          = 'asset_movements';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'asset_id',
        'from_location_id',
        'to_location_id',
        'moved_by',
        'notes',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function findForAsset(int $assetId): array
    {
        return $this->select([
                'asset_movements.id',
                'asset_movements.asset_id',
                'asset_movements.from_location_id',
                'from_locations.name AS from_location',
                'asset_movements.to_location_id',
                'to_locations.name AS to_location',
                'asset_movements.moved_by',
                'users.username AS moved_by_name',
                'asset_movements.notes',
                'asset_movements.created_at',
            ])
            ->join('locations from_locations', 'from_locations.id = asset_movements.from_location_id', 'left')
            ->join('locations to_locations', 'to_locations.id = asset_movements.to_location_id', 'left')
            ->join('users', 'users.id = asset_movements.moved_by', 'left')
            ->where('asset_movements.asset_id', $assetId)
            ->orderBy('asset_movements.created_at', 'DESC')
            ->findAll();
    }
}
