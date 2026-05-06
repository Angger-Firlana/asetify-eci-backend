<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetWorkspaceItemModel extends Model
{
    protected $table          = 'asset_workspace_items';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'workspace_id',
        'serial_number',
        'asset_id',
        'match_status',
        'action_status',
        'asset_category_id',
        'brand_id',
        'model_name',
        'source_location_id',
        'target_location_id',
        'current_location_detail',
        'condition_status',
        'notes',
        'scanned_by',
        'scan_method',
        'last_scan_at',
        'synced_at',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function findByWorkspaceAndSerialNumber(int $workspaceId, string $serialNumber): ?array
    {
        return $this->where('workspace_id', $workspaceId)
            ->where('serial_number', $serialNumber)
            ->first();
    }

    public function queryWithRelations(): self
    {
        return $this->select([
                'asset_workspace_items.id',
                'asset_workspace_items.workspace_id',
                'asset_workspace_items.serial_number',
                'asset_workspace_items.asset_id',
                'asset_workspace_items.match_status',
                'asset_workspace_items.action_status',
                'asset_workspace_items.asset_category_id',
                'item_categories.name AS item_asset_category_name',
                'asset_workspace_items.brand_id',
                'item_brands.name AS item_brand_name',
                'asset_workspace_items.model_name',
                'asset_workspace_items.source_location_id',
                'item_source_locations.name AS item_source_location_name',
                'asset_workspace_items.target_location_id',
                'item_target_locations.name AS item_target_location_name',
                'asset_workspace_items.current_location_detail',
                'asset_workspace_items.condition_status',
                'asset_workspace_items.notes',
                'asset_workspace_items.scanned_by',
                'scan_users.username AS scanned_by_name',
                'asset_workspace_items.scan_method',
                'asset_workspace_items.last_scan_at',
                'asset_workspace_items.synced_at',
                'asset_workspace_items.created_at',
                'asset_workspace_items.updated_at',
                'assets.serial_number AS asset_serial_number',
                'assets.model_name AS asset_model_name',
                'assets.condition_status AS asset_condition_status',
                'assets.asset_category_id AS asset_asset_category_id',
                'asset_categories.name AS asset_asset_category_name',
                'assets.brand_id AS asset_brand_id',
                'brands.name AS asset_brand_name',
                'assets.source_location_id AS asset_source_location_id',
                'asset_source_locations.name AS asset_source_location_name',
                'assets.current_location_id AS asset_current_location_id',
                'assets.current_location_detail AS asset_current_location_detail',
                'asset_current_locations.name AS asset_current_location_name',
                'primary_workspace_photo.id AS workspace_photo_id',
                'primary_photo.id AS asset_photo_id',
            ])
            ->join('users scan_users', 'scan_users.id = asset_workspace_items.scanned_by', 'left')
            ->join('asset_categories item_categories', 'item_categories.id = asset_workspace_items.asset_category_id', 'left')
            ->join('brands item_brands', 'item_brands.id = asset_workspace_items.brand_id', 'left')
            ->join('locations item_source_locations', 'item_source_locations.id = asset_workspace_items.source_location_id', 'left')
            ->join('locations item_target_locations', 'item_target_locations.id = asset_workspace_items.target_location_id', 'left')
            ->join('asset_workspace_item_photos primary_workspace_photo', 'primary_workspace_photo.workspace_item_id = asset_workspace_items.id AND primary_workspace_photo.is_primary = 1', 'left')
            ->join('assets', 'assets.id = asset_workspace_items.asset_id', 'left')
            ->join('asset_categories', 'asset_categories.id = assets.asset_category_id', 'left')
            ->join('brands', 'brands.id = assets.brand_id', 'left')
            ->join('locations asset_source_locations', 'asset_source_locations.id = assets.source_location_id', 'left')
            ->join('locations asset_current_locations', 'asset_current_locations.id = assets.current_location_id', 'left')
            ->join('asset_photos primary_photo', 'primary_photo.asset_id = assets.id AND primary_photo.is_primary = 1', 'left');
    }

    public function findDetail(int $workspaceId, int $itemId): ?array
    {
        return $this->queryWithRelations()
            ->where('asset_workspace_items.workspace_id', $workspaceId)
            ->where('asset_workspace_items.id', $itemId)
            ->first();
    }

    public function findForWorkspace(int $workspaceId): array
    {
        return $this->queryWithRelations()
            ->where('asset_workspace_items.workspace_id', $workspaceId)
            ->orderBy('asset_workspace_items.last_scan_at', 'DESC')
            ->findAll();
    }
}
