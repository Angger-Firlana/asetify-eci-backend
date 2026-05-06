<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetWorkspaceItemModel;
use App\Models\AssetWorkspaceItemPhotoModel;
use App\Models\AssetWorkspaceModel;
use App\Services\AssetWorkspaceService;
use App\Services\PhotoUploadService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class WorkspaceController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $builder = model(AssetWorkspaceModel::class)->queryWithRelations();

        if ($user->inGroup('scanner')) {
            $builder->where('asset_workspaces.created_by', (int) $user->id);
        }

        $status = $this->request->getGet('status');
        if ($status !== null && $status !== '') {
            $builder->where('asset_workspaces.status', $status);
        }

        $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') {
            $builder->groupStart()
                ->like('asset_workspaces.workspace_code', $search)
                ->orLike('asset_workspaces.title', $search)
                ->orLike('source_locations.name', $search)
                ->orLike('target_locations.name', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('asset_workspaces.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->findAll();

        return $this->respondSuccess(
            'Workspaces fetched',
            array_map([$this, 'formatWorkspace'], $items),
            ResponseInterface::HTTP_OK,
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ]
        );
    }

    public function create(): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $rules = [
            'title'              => 'required|string|max_length[150]',
            'source_location_id' => 'required|integer',
            'target_location_id' => 'required|integer',
            'status'             => 'permit_empty|in_list[draft,active,completed,cancelled]',
            'notes'              => 'permit_empty|string',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $workspace = (new AssetWorkspaceService())->createWorkspace($payload, $user);

            return $this->respondSuccess(
                'Workspace created successfully',
                $this->formatWorkspace($workspace),
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            return $this->respondError($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return $this->respondError('Failed to create workspace', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(int $workspaceId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $workspace = model(AssetWorkspaceModel::class)->findDetail($workspaceId);
        if ($workspace === null) {
            return $this->respondError('Workspace not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        if ($user->inGroup('scanner') && (int) $workspace['created_by'] !== (int) $user->id) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $items = model(AssetWorkspaceItemModel::class)->findForWorkspace($workspaceId);

        return $this->respondSuccess(
            'Workspace fetched',
            $this->formatWorkspaceDetail($workspace, $items)
        );
    }

    public function scan(int $workspaceId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $normalizedPayload = $this->normalizeWorkspaceAssetPayload($payload);
        if ($normalizedPayload instanceof ResponseInterface) {
            return $normalizedPayload;
        }
        $payload = $normalizedPayload;

        if (isset($payload['serial_number'])) {
            $payload['serial_number'] = $this->normalizeSerialNumber((string) $payload['serial_number']);
        }

        $rules = [
            'serial_number'      => 'required|string|max_length[150]',
            'scan_method'        => 'required|in_list[barcode,manual]',
            'app_platform'       => 'required|in_list[web,android,ios]',
            'device_info'        => 'permit_empty|string|max_length[255]',
            'asset_category_id'  => 'permit_empty|integer',
            'brand_id'           => 'permit_empty|integer',
            'model_name'         => 'permit_empty|string|max_length[150]',
            'source_location_id' => 'permit_empty|integer',
            'current_location_id'=> 'permit_empty|integer',
            'target_location_id' => 'permit_empty|integer',
            'current_location_detail' => 'permit_empty|string|max_length[255]',
            'condition_status'   => 'permit_empty|in_list[good,bad]',
            'notes'              => 'permit_empty|string',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $item = (new AssetWorkspaceService())->scanIntoWorkspace($workspaceId, $payload, $user);

            return $this->respondSuccess(
                'Workspace item scanned successfully',
                $this->formatWorkspaceItem($item),
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            $lower  = strtolower($e->getMessage());
            $status = str_contains($lower, 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        } catch (\Throwable $e) {
            return $this->respondError('Failed to scan workspace item', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function registerAsset(int $workspaceId, int $workspaceItemId): ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $normalizedPayload = $this->normalizeWorkspaceAssetPayload($payload);
        if ($normalizedPayload instanceof ResponseInterface) {
            return $normalizedPayload;
        }
        $payload = $normalizedPayload;

        $rules = [
            'asset_category_id'   => 'permit_empty|integer',
            'brand_id'            => 'permit_empty|integer',
            'model_name'          => 'permit_empty|string|max_length[150]',
            'source_location_id'  => 'permit_empty|integer',
            'current_location_id' => 'permit_empty|integer',
            'target_location_id'  => 'permit_empty|integer',
            'current_location_detail' => 'permit_empty|string|max_length[255]',
            'condition_status'    => 'permit_empty|in_list[good,bad]',
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

        if (isset($payload['photo_upload_ids']) && ! is_array($payload['photo_upload_ids'])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['photo_upload_ids' => ['The photo_upload_ids field must be an array.']]
            );
        }

        try {
            $item = (new AssetWorkspaceService())->registerWorkspaceItemAsAsset($workspaceId, $workspaceItemId, $payload, $user);

            return $this->respondSuccess(
                'Workspace item registered into asset master successfully',
                $this->formatWorkspaceItem($item),
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            $lower  = strtolower($e->getMessage());
            $status = match (true) {
                str_contains($lower, 'not found') => ResponseInterface::HTTP_NOT_FOUND,
                str_contains($lower, 'already') => ResponseInterface::HTTP_CONFLICT,
                default => ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
            };

            return $this->respondError($e->getMessage(), $status);
        } catch (\Throwable $e) {
            return $this->respondError(
                'Failed to register workspace item into asset master',
                ResponseInterface::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function downloadPhoto(int $workspaceItemId, int $photoId): ResponseInterface
    {
        $photo = model(AssetWorkspaceItemPhotoModel::class)->findWorkspaceItemPhoto($workspaceItemId, $photoId);
        if ($photo === null) {
            return $this->respondError('Photo not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $path = (new PhotoUploadService())->absolutePath($photo['file_path']);
        if (! is_file($path)) {
            return $this->respondError('Photo file not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return $this->respondError('Failed to read photo file', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->response
            ->setContentType($photo['mime_type'] ?: 'application/octet-stream')
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('Content-Disposition', 'inline; filename="' . addslashes($photo['file_name']) . '"')
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody($contents);
    }

    private function formatWorkspace(array $workspace): array
    {
        return [
            'id'             => (int) $workspace['id'],
            'workspace_code' => $workspace['workspace_code'],
            'title'          => $workspace['title'],
            'status'         => $workspace['status'],
            'notes'          => $workspace['notes'] ?? null,
            'created_by'     => [
                'id'   => $workspace['created_by'] !== null ? (int) $workspace['created_by'] : null,
                'name' => $workspace['created_by_name'] ?? null,
            ],
            'source_location' => [
                'id'   => isset($workspace['source_location_id']) ? (int) $workspace['source_location_id'] : null,
                'name' => $workspace['source_location_name'] ?? null,
            ],
            'target_location' => [
                'id'   => isset($workspace['target_location_id']) ? (int) $workspace['target_location_id'] : null,
                'name' => $workspace['target_location_name'] ?? null,
            ],
            'closed_at'      => $workspace['closed_at'] ?? null,
            'created_at'     => $workspace['created_at'] ?? null,
            'updated_at'     => $workspace['updated_at'] ?? null,
        ];
    }

    private function formatWorkspaceDetail(array $workspace, array $items): array
    {
        $formattedItems = array_map([$this, 'formatWorkspaceItem'], $items);

        return array_merge($this->formatWorkspace($workspace), [
            'summary' => [
                'total_items'             => count($formattedItems),
                'matched_items'           => count(array_filter($formattedItems, static fn (array $item): bool => $item['exists_in_assets'])),
                'pending_registration'    => count(array_filter($formattedItems, static fn (array $item): bool => $item['action_status'] === 'ready_to_register')),
                'registered_from_workspace' => count(array_filter($formattedItems, static fn (array $item): bool => $item['action_status'] === 'asset_registered')),
            ],
            'items' => $formattedItems,
        ]);
    }

    private function formatWorkspaceItem(array $item): array
    {
        $assetId = $item['asset_id'] ?? null;

        return [
            'id'                => (int) $item['id'],
            'workspace_id'      => (int) $item['workspace_id'],
            'serial_number'     => $item['serial_number'],
            'model_name'        => $item['model_name'] ?? null,
            'photo_url'         => $this->buildWorkspaceItemPhotoUrl($item),
            'exists_in_assets'  => $assetId !== null,
            'match_status'      => $item['match_status'],
            'action_status'     => $item['action_status'],
            'current_location_detail' => $item['current_location_detail'] ?? null,
            'scan_method'       => $item['scan_method'],
            'last_scan_at'      => $item['last_scan_at'] ?? null,
            'synced_at'         => $item['synced_at'] ?? null,
            'notes'             => $item['notes'] ?? null,
            'condition_status'  => $item['condition_status'] ?? null,
            'scanned_by_user'   => [
                'id'   => $item['scanned_by'] !== null ? (int) $item['scanned_by'] : null,
                'name' => $item['scanned_by_name'] ?? null,
            ],
            'workspace_relations' => [
                'asset_category' => $this->relation($item['asset_category_id'] ?? null, $item['item_asset_category_name'] ?? null),
                'brand'          => $this->relation($item['brand_id'] ?? null, $item['item_brand_name'] ?? null),
                'source_location'=> $this->relation($item['source_location_id'] ?? null, $item['item_source_location_name'] ?? null),
                'current_location'=> $this->relation($item['target_location_id'] ?? null, $item['item_target_location_name'] ?? null),
                'target_location'=> $this->relation($item['target_location_id'] ?? null, $item['item_target_location_name'] ?? null),
            ],
            'matched_asset' => $assetId === null ? null : [
                'id'               => (int) $assetId,
                'serial_number'    => $item['asset_serial_number'] ?? null,
                'model_name'       => $item['asset_model_name'] ?? null,
                'condition_status' => $item['asset_condition_status'] ?? null,
                'current_location_detail' => $item['asset_current_location_detail'] ?? null,
                'photo_url'        => $this->buildAssetPhotoUrl($item),
                'relations'        => [
                    'asset_category'  => $this->relation($item['asset_asset_category_id'] ?? null, $item['asset_asset_category_name'] ?? null),
                    'brand'           => $this->relation($item['asset_brand_id'] ?? null, $item['asset_brand_name'] ?? null),
                    'source_location' => $this->relation($item['asset_source_location_id'] ?? null, $item['asset_source_location_name'] ?? null),
                    'current_location'=> $this->relation($item['asset_current_location_id'] ?? null, $item['asset_current_location_name'] ?? null),
                ],
            ],
            'created_at' => $item['created_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
        ];
    }

    private function relation(mixed $id, ?string $name): ?array
    {
        if ($id === null && $name === null) {
            return null;
        }

        return [
            'id' => $id !== null ? (int) $id : null,
            'name' => $name,
        ];
    }

    private function buildAssetPhotoUrl(array $item): ?string
    {
        $assetId = isset($item['asset_id']) ? (int) $item['asset_id'] : null;
        $photoId = $item['asset_photo_id'] ?? null;

        if ($assetId === null || $assetId <= 0 || $photoId === null || $photoId === '') {
            return null;
        }

        return site_url('api/v1/assets/' . $assetId . '/download-photo/' . $photoId);
    }

    private function buildWorkspaceItemPhotoUrl(array $item): ?string
    {
        $workspaceItemId = isset($item['id']) ? (int) $item['id'] : null;
        $photoId         = $item['workspace_photo_id'] ?? null;

        if ($workspaceItemId === null || $workspaceItemId <= 0 || $photoId === null || $photoId === '') {
            return null;
        }

        return site_url('api/v1/workspaces/items/' . $workspaceItemId . '/download-photo/' . $photoId);
    }

    private function normalizeWorkspaceAssetPayload(array $payload): array|ResponseInterface
    {
        $hasTargetLocation = array_key_exists('target_location_id', $payload) && $payload['target_location_id'] !== null && $payload['target_location_id'] !== '';
        $hasCurrentLocation = array_key_exists('current_location_id', $payload) && $payload['current_location_id'] !== null && $payload['current_location_id'] !== '';

        if ($hasTargetLocation && $hasCurrentLocation && (string) $payload['target_location_id'] !== (string) $payload['current_location_id']) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['current_location_id' => ['current_location_id and target_location_id must match when both are provided.']]
            );
        }

        if ($hasCurrentLocation && ! $hasTargetLocation) {
            $payload['target_location_id'] = $payload['current_location_id'];
        }

        if ($hasTargetLocation && ! $hasCurrentLocation) {
            $payload['current_location_id'] = $payload['target_location_id'];
        }

        return $payload;
    }
}
