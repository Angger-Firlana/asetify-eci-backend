<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetAuditLogModel extends Model
{
    protected $table          = 'asset_audit_logs';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'asset_id',
        'action',
        'changed_by',
        'change_source',
        'field_name',
        'old_value',
        'new_value',
        'change_note',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function forAsset(int $assetId): self
    {
        return $this->where('asset_audit_logs.asset_id', $assetId);
    }

    public function queryWithRelations(): self
    {
        return $this->select([
                'asset_audit_logs.id',
                'asset_audit_logs.asset_id',
                'assets.serial_number',
                'asset_audit_logs.action',
                'asset_audit_logs.changed_by',
                'users.username AS changed_by_name',
                'asset_audit_logs.change_source',
                'asset_audit_logs.field_name',
                'asset_audit_logs.old_value',
                'asset_audit_logs.new_value',
                'asset_audit_logs.change_note',
                'asset_audit_logs.created_at',
            ])
            ->join('assets', 'assets.id = asset_audit_logs.asset_id', 'left')
            ->join('users', 'users.id = asset_audit_logs.changed_by', 'left');
    }
}
