<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetWorkspaceItemScanModel extends Model
{
    protected $table          = 'asset_workspace_item_scans';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'workspace_item_id',
        'workspace_id',
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
}
