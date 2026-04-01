<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetAuditLogModel;
use App\Models\AssetModel;
use App\Models\AssetScanLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class HistoryController extends BaseApiController
{
    public function scanLogs(): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $builder = model(AssetScanLogModel::class)->queryWithRelations();

        if ($user->inGroup('scanner')) {
            $builder->where('asset_scan_logs.scanned_by', (int) $user->id);
        }

        $this->applyHistoryFilters($builder, [
            'serial_number' => ['column' => 'asset_scan_logs.serial_number', 'normalize' => true],
            'scanned_by'    => ['column' => 'asset_scan_logs.scanned_by'],
            'result_status' => ['column' => 'asset_scan_logs.result_status'],
            'scan_method'   => ['column' => 'asset_scan_logs.scan_method'],
        ]);

        $dateFrom = $this->request->getGet('date_from');
        if ($dateFrom) {
            $builder->where('DATE(asset_scan_logs.created_at) >=', $dateFrom);
        }

        $dateTo = $this->request->getGet('date_to');
        if ($dateTo) {
            $builder->where('DATE(asset_scan_logs.created_at) <=', $dateTo);
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('asset_scan_logs.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->findAll();

        $items = array_map(function (array $item): array {
            $item['scanned_by_user'] = [
                'id'   => $item['scanned_by'] !== null ? (int) $item['scanned_by'] : null,
                'name' => $item['scanned_by_name'],
            ];
            unset($item['scanned_by_name']);

            return $item;
        }, $items);

        return $this->respondSuccess(
            'Scan logs fetched',
            $items,
            ResponseInterface::HTTP_OK,
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]
        );
    }

    public function assetAuditLogs(int $assetId): ResponseInterface
    {
        $asset = model(AssetModel::class)->find($assetId);
        if ($asset === null) {
            return $this->respondError('Asset not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $builder = model(AssetAuditLogModel::class)
            ->queryWithRelations()
            ->forAsset($assetId);

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('asset_audit_logs.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->findAll();

        $items = array_map([$this, 'formatAuditLogItem'], $items);

        return $this->respondSuccess(
            'Asset audit logs fetched',
            $items,
            ResponseInterface::HTTP_OK,
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]
        );
    }

    public function globalAuditLogs(): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if (! $user->inGroup('supervisor', 'admin')) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $builder = model(AssetAuditLogModel::class)->queryWithRelations();

        $this->applyHistoryFilters($builder, [
            'asset_id'    => ['column' => 'asset_audit_logs.asset_id'],
            'serial_number' => ['column' => 'assets.serial_number', 'normalize' => true],
            'changed_by'  => ['column' => 'asset_audit_logs.changed_by'],
            'action'      => ['column' => 'asset_audit_logs.action'],
            'field_name'  => ['column' => 'asset_audit_logs.field_name'],
        ]);

        $dateFrom = $this->request->getGet('date_from');
        if ($dateFrom) {
            $builder->where('DATE(asset_audit_logs.created_at) >=', $dateFrom);
        }

        $dateTo = $this->request->getGet('date_to');
        if ($dateTo) {
            $builder->where('DATE(asset_audit_logs.created_at) <=', $dateTo);
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('asset_audit_logs.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->findAll();

        $items = array_map([$this, 'formatAuditLogItem'], $items);

        return $this->respondSuccess(
            'Audit logs fetched',
            $items,
            ResponseInterface::HTTP_OK,
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]
        );
    }

    private function formatAuditLogItem(array $item): array
    {
        return [
            'id'            => (int) $item['id'],
            'asset_id'      => (int) $item['asset_id'],
            'serial_number' => $item['serial_number'],
            'field_name'    => $item['field_name'],
            'old_value'     => $item['old_value'],
            'new_value'     => $item['new_value'],
            'action'        => $item['action'],
            'change_source' => $item['change_source'],
            'change_note'   => $item['change_note'],
            'changed_by'    => [
                'id'   => $item['changed_by'] !== null ? (int) $item['changed_by'] : null,
                'name' => $item['changed_by_name'],
            ],
            'created_at'    => $item['created_at'],
        ];
    }

    private function applyHistoryFilters(object $builder, array $filters): void
    {
        foreach ($filters as $queryParam => $config) {
            $value = $this->request->getGet($queryParam);
            if ($value === null || $value === '') {
                continue;
            }

            $builder->where(
                $config['column'],
                ($config['normalize'] ?? false) ? $this->normalizeSerialNumber((string) $value) : $value
            );
        }
    }
}
