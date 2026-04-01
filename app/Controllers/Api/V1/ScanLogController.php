<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetModel;
use App\Models\AssetScanLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class ScanLogController extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $user = $this->currentTokenUser();

        if ($user === null) {
            return $this->respondError(
                'Unauthorized',
                ResponseInterface::HTTP_UNAUTHORIZED,
                ['token' => ['Invalid or missing access token.']]
            );
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (isset($payload['serial_number'])) {
            $payload['serial_number'] = $this->normalizeSerialNumber((string) $payload['serial_number']);
        }

        $rules = [
            'serial_number' => 'required|string|max_length[150]',
            'scan_method'   => 'required|in_list[barcode,manual]',
            'result_status' => 'required|in_list[success,duplicate,failed]',
            'message'       => 'permit_empty|string|max_length[255]',
            'device_info'   => 'permit_empty|string|max_length[255]',
            'app_platform'  => 'required|in_list[web,android,ios]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        $assetId = null;
        if (! empty($payload['asset_id'])) {
            $assetId = (int) $payload['asset_id'];
        } else {
            $asset = model(AssetModel::class)->findActiveBySerialNumber($payload['serial_number']);
            $assetId = $asset['id'] ?? null;
        }

        $data = [
            'serial_number' => $payload['serial_number'],
            'asset_id'      => $assetId,
            'scanned_by'    => (int) $user->id,
            'scan_method'   => $payload['scan_method'],
            'result_status' => $payload['result_status'],
            'message'       => $payload['message'] ?? null,
            'device_info'   => $payload['device_info'] ?? null,
            'app_platform'  => $payload['app_platform'],
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ];

        $scanLogModel = model(AssetScanLogModel::class);

        if (! $scanLogModel->insert($data)) {
            return $this->respondError(
                'Failed to save scan log',
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR,
                $scanLogModel->errors()
            );
        }

        $data['id'] = (int) $scanLogModel->getInsertID();

        return $this->respondSuccess(
            'Scan log saved',
            $data,
            ResponseInterface::HTTP_CREATED
        );
    }
}
