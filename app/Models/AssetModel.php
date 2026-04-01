<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetModel extends Model
{
    protected $table          = 'assets';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields  = [
        'serial_number',
        'asset_type_id',
        'asset_category_id',
        'brand_id',
        'model_name',
        'source_location_id',
        'current_location_id',
        'condition_status',
        'notes',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $useTimestamps = false;

    public function findActiveBySerialNumber(string $serialNumber): ?array
    {
        return $this->where('serial_number', $serialNumber)->first();
    }

    public function findDuplicateSummaryBySerialNumber(string $serialNumber): ?array
    {
        return $this->select([
                'assets.id',
                'brands.name AS brand',
                'asset_categories.name AS asset_category',
                'locations.name AS current_location',
                'assets.condition_status',
            ])
            ->join('brands', 'brands.id = assets.brand_id', 'left')
            ->join('asset_categories', 'asset_categories.id = assets.asset_category_id', 'left')
            ->join('locations', 'locations.id = assets.current_location_id', 'left')
            ->where('assets.serial_number', $serialNumber)
            ->first();
    }

    public function findAssetDetail(int $assetId): ?array
    {
        return $this->select([
                'assets.id',
                'assets.serial_number',
                'assets.asset_type_id',
                'asset_types.name AS asset_type',
                'assets.asset_category_id',
                'asset_categories.name AS asset_category',
                'assets.brand_id',
                'brands.name AS brand',
                'assets.model_name',
                'assets.source_location_id',
                'source_locations.name AS source_location',
                'assets.current_location_id',
                'current_locations.name AS current_location',
                'assets.condition_status',
                'assets.notes',
                'assets.created_by',
                'assets.updated_by',
                'assets.created_at',
                'assets.updated_at',
            ])
            ->join('asset_types', 'asset_types.id = assets.asset_type_id', 'left')
            ->join('asset_categories', 'asset_categories.id = assets.asset_category_id', 'left')
            ->join('brands', 'brands.id = assets.brand_id', 'left')
            ->join('locations source_locations', 'source_locations.id = assets.source_location_id', 'left')
            ->join('locations current_locations', 'current_locations.id = assets.current_location_id', 'left')
            ->where('assets.id', $assetId)
            ->first();
    }
}
