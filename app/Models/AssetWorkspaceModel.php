<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetWorkspaceModel extends Model
{
    protected $table          = 'asset_workspaces';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'workspace_code',
        'title',
        'source_location_id',
        'target_location_id',
        'status',
        'notes',
        'created_by',
        'closed_at',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function queryWithRelations(): self
    {
        return $this->select([
                'asset_workspaces.id',
                'asset_workspaces.workspace_code',
                'asset_workspaces.title',
                'asset_workspaces.source_location_id',
                'source_locations.name AS source_location_name',
                'asset_workspaces.target_location_id',
                'target_locations.name AS target_location_name',
                'asset_workspaces.status',
                'asset_workspaces.notes',
                'asset_workspaces.created_by',
                'users.username AS created_by_name',
                'asset_workspaces.closed_at',
                'asset_workspaces.created_at',
                'asset_workspaces.updated_at',
            ])
            ->join('locations source_locations', 'source_locations.id = asset_workspaces.source_location_id', 'left')
            ->join('locations target_locations', 'target_locations.id = asset_workspaces.target_location_id', 'left')
            ->join('users', 'users.id = asset_workspaces.created_by', 'left');
    }

    public function findDetail(int $workspaceId): ?array
    {
        return $this->queryWithRelations()
            ->where('asset_workspaces.id', $workspaceId)
            ->first();
    }
}
