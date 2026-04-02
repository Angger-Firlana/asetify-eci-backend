<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetScanLogModel extends Model
{
    protected $table          = 'asset_scan_logs';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'serial_number',
        'asset_id',
        'scanned_by',
        'scan_method',
        'result_status',
        'message',
        'device_info',
        'app_platform',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function queryWithRelations(): self
    {
        return $this->select([
                'asset_scan_logs.id',
                'asset_scan_logs.serial_number',
                'asset_scan_logs.asset_id',
                'primary_photo.id AS photo_id',
                'asset_scan_logs.scanned_by',
                'users.username AS scanned_by_name',
                'asset_scan_logs.scan_method',
                'asset_scan_logs.result_status',
                'asset_scan_logs.message',
                'asset_scan_logs.device_info',
                'asset_scan_logs.app_platform',
                'asset_scan_logs.created_at',
            ])
            ->join('users', 'users.id = asset_scan_logs.scanned_by', 'left')
            ->join('asset_photos primary_photo', 'primary_photo.asset_id = asset_scan_logs.asset_id AND primary_photo.is_primary = 1', 'left');
    }
}
