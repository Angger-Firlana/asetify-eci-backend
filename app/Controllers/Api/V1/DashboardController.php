<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetModel;
use App\Models\AssetScanLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class DashboardController extends BaseApiController
{
    public function summary(): ResponseInterface
    {
        $todayUtc     = gmdate('Y-m-d');

        $data = [
            'total_assets'           => model(AssetModel::class)->countAllResults(),
            'total_scans_today'      => model(AssetScanLogModel::class)->where('DATE(created_at)', $todayUtc)->countAllResults(),
            'total_duplicates_today' => model(AssetScanLogModel::class)->where('DATE(created_at)', $todayUtc)->where('result_status', 'duplicate')->countAllResults(),
            'total_condition_good'   => model(AssetModel::class)->where('condition_status', 'good')->countAllResults(),
            'total_condition_bad'    => model(AssetModel::class)->where('condition_status', 'bad')->countAllResults(),
        ];

        return $this->respondSuccess('Dashboard summary fetched', $data);
    }
}
