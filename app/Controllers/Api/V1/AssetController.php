<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetModel;
use App\Models\AssetMovementModel;
use App\Models\AssetPhotoModel;
use App\Services\AssetService;
use App\Services\AssetAuthorizationService;
use App\Services\PhotoUploadService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class AssetController extends BaseApiController
{
    public function checkSerialNumber(): ResponseInterface
    {
        $serialNumber = $this->normalizeSerialNumber($this->request->getGet('serial_number'));

        if ($serialNumber === '') {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['serial_number' => ['The serial_number field is required.']]
            );
        }

        $asset = model(AssetModel::class)->findDuplicateSummaryBySerialNumber($serialNumber);

        if ($asset === null) {
            return $this->respondSuccess(
                'Serial number is available',
                [
                    'exists'        => false,
                    'serial_number' => $serialNumber,
                ]
            );
        }

        $user = $this->currentTokenUser();

        return $this->respondSuccess(
            'Serial number already registered',
            [
                'exists'                     => true,
                'serial_number'              => $serialNumber,
                'can_edit'                   => $user?->inGroup('scanner', 'supervisor', 'admin') ?? false,
                'can_edit_serial_number'     => $user?->inGroup('supervisor', 'admin') ?? false,
                'can_manage_existing_photos' => $user?->inGroup('supervisor', 'admin') ?? false,
                'asset'                      => $asset,
            ]
        );
    }

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
            'serial_number'       => 'required|string|max_length[150]',
            'asset_type_id'       => 'required|integer',
            'asset_category_id'   => 'required|integer',
            'brand_id'            => 'required|integer',
            'model_name'          => 'permit_empty|string|max_length[150]',
            'source_location_id'  => 'required|integer',
            'current_location_id' => 'required|integer',
            'condition_status'    => 'required|in_list[good,bad]',
            'notes'               => 'permit_empty|string',
            'scan_method'         => 'permit_empty|in_list[barcode,manual]',
            'app_platform'        => 'permit_empty|in_list[web,android,ios]',
            'device_info'         => 'permit_empty|string|max_length[255]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        if (! isset($payload['photo_upload_ids']) || ! is_array($payload['photo_upload_ids']) || $payload['photo_upload_ids'] === []) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['photo_upload_ids' => ['At least one photo_upload_id is required.']]
            );
        }

        try {
            $asset = (new AssetService())->createAsset($payload, (int) $user->id);

            return $this->respondSuccess(
                'Asset created successfully',
                $asset,
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'serial number already exists')
                ? ResponseInterface::HTTP_CONFLICT
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        } catch (\Throwable $e) {
            return $this->respondError(
                'Failed to create asset',
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function index(): ResponseInterface
    {
        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $sortByWhitelist = [
            'created_at'       => 'assets.created_at',
            'serial_number'    => 'assets.serial_number',
            'brand'            => 'brands.name',
            'asset_category'   => 'asset_categories.name',
            'source_location'  => 'source_locations.name',
            'current_location' => 'current_locations.name',
            'condition_status' => 'assets.condition_status',
        ];

        $sortBy  = (string) ($this->request->getGet('sort_by') ?: 'created_at');
        $sortDir = strtolower((string) ($this->request->getGet('sort_dir') ?: 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sortCol = $sortByWhitelist[$sortBy] ?? 'assets.created_at';

        $builder = model(AssetModel::class)
            ->select([
                'assets.id',
                'assets.serial_number',
                'brands.name AS brand',
                'asset_categories.name AS asset_category',
                'source_locations.name AS source_location',
                'current_locations.name AS current_location',
                'assets.condition_status',
                'primary_photo.id AS photo_id',
            ])
            ->join('brands', 'brands.id = assets.brand_id', 'left')
            ->join('asset_categories', 'asset_categories.id = assets.asset_category_id', 'left')
            ->join('locations source_locations', 'source_locations.id = assets.source_location_id', 'left')
            ->join('locations current_locations', 'current_locations.id = assets.current_location_id', 'left')
            ->join('asset_photos primary_photo', 'primary_photo.asset_id = assets.id AND primary_photo.is_primary = 1', 'left');

        $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') {
            $builder->groupStart()
                ->like('assets.serial_number', $search)
                ->orLike('brands.name', $search)
                ->orLike('asset_categories.name', $search)
                ->orLike('source_locations.name', $search)
                ->orLike('current_locations.name', $search)
                ->orLike('assets.model_name', $search)
                ->groupEnd();
        }

        $this->applyExactFilter($builder, 'serial_number', 'assets.serial_number');
        $this->applyExactFilter($builder, 'asset_type_id', 'assets.asset_type_id');
        $this->applyExactFilter($builder, 'asset_category_id', 'assets.asset_category_id');
        $this->applyExactFilter($builder, 'brand_id', 'assets.brand_id');
        $this->applyExactFilter($builder, 'source_location_id', 'assets.source_location_id');
        $this->applyExactFilter($builder, 'current_location_id', 'assets.current_location_id');
        $this->applyExactFilter($builder, 'condition_status', 'assets.condition_status');
        $this->applyExactFilter($builder, 'created_by', 'assets.created_by');

        $dateFrom = $this->request->getGet('date_from');
        if ($dateFrom) {
            $builder->where('DATE(assets.created_at) >=', $dateFrom);
        }

        $dateTo = $this->request->getGet('date_to');
        if ($dateTo) {
            $builder->where('DATE(assets.created_at) <=', $dateTo);
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy($sortCol, $sortDir)
            ->limit($perPage, $offset)
            ->findAll();

        $items = array_map(function (array $item): array {
            $item['photo_url'] = $item['photo_id'] !== null
                ? site_url('api/v1/assets/' . $item['id'] . '/download-photo/' . $item['photo_id'])
                : null;
            unset($item['photo_id']);

            return $item;
        }, $items);

        return $this->respondSuccess(
            'Assets fetched',
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

    public function show(int $assetId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $authz = new AssetAuthorizationService();
        if (! $authz->canViewAsset($user)) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $asset = model(AssetModel::class)->findAssetDetail($assetId);
        if ($asset === null) {
            return $this->respondError('Asset not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $asset['photos'] = $this->decoratePhotos(model(AssetPhotoModel::class)->findForAsset($assetId), $assetId);
        $asset['movements'] = model(AssetMovementModel::class)->findForAsset($assetId);
        $asset['permissions'] = [
            'can_edit' => $authz->canUpdateAsset($user),
            'can_edit_serial_number' => $authz->canEditSerialNumber($user),
            'can_manage_existing_photos' => $authz->canManageExistingPhotos($user),
        ];

        return $this->respondSuccess('Asset fetched', $asset);
    }

    public function update(int $assetId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (isset($payload['serial_number'])) {
            $payload['serial_number'] = $this->normalizeSerialNumber((string) $payload['serial_number']);
        }

        $rules = [
            'serial_number'       => 'permit_empty|string|max_length[150]',
            'asset_type_id'       => 'permit_empty|integer',
            'asset_category_id'   => 'permit_empty|integer',
            'brand_id'            => 'permit_empty|integer',
            'model_name'          => 'permit_empty|string|max_length[150]',
            'source_location_id'  => 'permit_empty|integer',
            'current_location_id' => 'permit_empty|integer',
            'condition_status'    => 'permit_empty|in_list[good,bad]',
            'notes'               => 'permit_empty|string',
            'scan_method'         => 'permit_empty|in_list[barcode,manual]',
            'app_platform'        => 'permit_empty|in_list[web,android,ios]',
            'device_info'         => 'permit_empty|string|max_length[255]',
            'change_source'       => 'permit_empty|in_list[scan_flow,manual_edit,system]',
        ];

        if ($payload !== [] && ! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $asset = (new AssetService())->updateAsset($assetId, $payload, $user);

            return $this->respondSuccess('Asset updated successfully', $asset);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $lower   = strtolower($message);
            $status  = match (true) {
                str_contains($lower, 'not found') => ResponseInterface::HTTP_NOT_FOUND,
                str_contains($lower, 'not allowed'),
                str_contains($lower, 'forbidden fields') => ResponseInterface::HTTP_FORBIDDEN,
                str_contains($lower, 'serial number already exists') => ResponseInterface::HTTP_CONFLICT,
                default => ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
            };

            return $this->respondError($message, $status);
        } catch (\Throwable $e) {
            return $this->respondError(
                'Failed to update asset',
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function photos(int $assetId): ResponseInterface
    {
        $asset = model(AssetModel::class)->find($assetId);
        if ($asset === null) {
            return $this->respondError('Asset not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $photos = $this->decoratePhotos(model(AssetPhotoModel::class)->findForAsset($assetId), $assetId);

        return $this->respondSuccess('Asset photos fetched', $photos);
    }

    public function addPhotos(int $assetId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (isset($payload['photo_upload_id']) && ! isset($payload['photo_upload_ids'])) {
            $payload['photo_upload_ids'] = [(string) $payload['photo_upload_id']];
        }

        if (! isset($payload['photo_upload_ids']) || ! is_array($payload['photo_upload_ids']) || $payload['photo_upload_ids'] === []) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['photo_upload_ids' => ['At least one photo_upload_id is required.']]
            );
        }

        if (! $this->validateData($payload, [
            'change_source' => 'permit_empty|in_list[scan_flow,manual_edit,system]',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $photos = (new AssetService())->addPhotosToAsset(
                $assetId,
                $payload['photo_upload_ids'],
                $user,
                $payload['change_source'] ?? 'manual_edit'
            );

            return $this->respondSuccess(
                'Asset photo added successfully',
                $this->decoratePhotos($photos, $assetId),
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $lower   = strtolower($message);
            $status  = match (true) {
                str_contains($lower, 'not found') => ResponseInterface::HTTP_NOT_FOUND,
                str_contains($lower, 'not allowed') => ResponseInterface::HTTP_FORBIDDEN,
                default => ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
            };

            return $this->respondError($message, $status);
        } catch (\Throwable $e) {
            return $this->respondError(
                'Failed to add asset photo',
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function deletePhoto(int $assetId, int $photoId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (! $this->validateData($payload, [
            'change_source' => 'permit_empty|in_list[scan_flow,manual_edit,system]',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $result = (new AssetService())->deleteAssetPhoto(
                $assetId,
                $photoId,
                $user,
                $payload['change_source'] ?? 'manual_edit'
            );

            $result['remaining_photos'] = $this->decoratePhotos($result['remaining_photos'], $assetId);

            return $this->respondSuccess('Asset photo deleted successfully', $result);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $lower   = strtolower($message);
            $status  = match (true) {
                str_contains($lower, 'not found') => ResponseInterface::HTTP_NOT_FOUND,
                str_contains($lower, 'not allowed') => ResponseInterface::HTTP_FORBIDDEN,
                default => ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
            };

            return $this->respondError($message, $status);
        } catch (\Throwable $e) {
            return $this->respondError(
                'Failed to delete asset photo',
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function downloadPhoto(int $assetId, int $photoId): ResponseInterface
    {
        $photo = model(AssetPhotoModel::class)->findAssetPhoto($assetId, $photoId);
        if ($photo === null) {
            return $this->respondError('Photo not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $path = (new PhotoUploadService())->absolutePath($photo['file_path']);
        if (! is_file($path)) {
            return $this->respondError('Photo file not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->response->download($path, null)->setFileName($photo['file_name']);
    }

    private function applyExactFilter(AssetModel $builder, string $queryParam, string $column): void
    {
        $value = $this->request->getGet($queryParam);
        if ($value !== null && $value !== '') {
            $builder->where(
                $column,
                $queryParam === 'serial_number'
                    ? $this->normalizeSerialNumber((string) $value)
                    : $value
            );
        }
    }

    private function decoratePhotos(array $photos, int $assetId): array
    {
        return array_map(function (array $photo) use ($assetId): array {
            $photo['download_url'] = site_url('api/v1/assets/' . $assetId . '/download-photo/' . $photo['id']);

            return $photo;
        }, $photos);
    }
}
