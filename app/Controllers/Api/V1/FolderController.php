<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetModel;
use App\Services\AssetAuthorizationService;
use App\Services\FolderService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class FolderController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.read');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $folders = (new FolderService())->listFolders([
            'search' => $this->request->getGet('search'),
            'type' => $this->request->getGet('type'),
            'parent_id' => $this->request->getGet('parent_id'),
            'asset_id' => $this->request->getGet('asset_id'),
        ]);

        return $this->respondSuccess('Folders fetched', $folders);
    }

    public function tree(): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.read');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $folders = (new FolderService())->folderTree([
            'search' => $this->request->getGet('search'),
            'type' => $this->request->getGet('type'),
        ]);

        return $this->respondSuccess('Folder tree fetched', $folders);
    }

    public function show(int $folderId): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.read');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $folder = (new FolderService())->folderDetail($folderId);

            return $this->respondSuccess('Folder fetched', $folder);
        } catch (RuntimeException $e) {
            return $this->respondError($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    public function create(): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (! $this->validateData($payload, [
            'name' => 'required|string|max_length[100]',
            'type' => 'permit_empty|string|max_length[50]',
            'parent_id' => 'permit_empty|integer',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $folder = (new FolderService())->createFolder($payload);

            return $this->respondSuccess('Folder created successfully', $folder, ResponseInterface::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return $this->respondError($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function update(int $folderId): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (! is_array($payload)) {
            $payload = [];
        }

        if ($payload !== [] && ! $this->validateData($payload, [
            'name' => 'permit_empty|string|max_length[100]',
            'type' => 'permit_empty|string|max_length[50]',
            'parent_id' => 'permit_empty|integer',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $folder = (new FolderService())->updateFolder($folderId, $payload);

            return $this->respondSuccess('Folder updated successfully', $folder);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    public function delete(int $folderId): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            (new FolderService())->deleteFolder($folderId);

            return $this->respondSuccess('Folder deleted successfully', null);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    public function assets(int $folderId): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.read');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $folder = (new FolderService())->folderDetail($folderId);
        } catch (RuntimeException $e) {
            return $this->respondError($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $builder = model(AssetModel::class)->builder();
        $builder->select([
            'assets.id',
            'assets.serial_number',
            'assets.asset_category_id',
            'assets.brand_id',
            'assets.model_name',
            'assets.source_location_id',
            'assets.current_location_id',
            'assets.current_location_detail',
            'assets.condition_status',
            'brands.name AS brand_name',
            'asset_categories.name AS asset_category_name',
            'source_locations.name AS source_location_name',
            'current_locations.name AS current_location_name',
            'primary_photo.id AS photo_id',
        ]);
        $builder->join('asset_folders', 'asset_folders.asset_id = assets.id');
        $builder->join('brands', 'brands.id = assets.brand_id', 'left');
        $builder->join('asset_categories', 'asset_categories.id = assets.asset_category_id', 'left');
        $builder->join('locations source_locations', 'source_locations.id = assets.source_location_id', 'left');
        $builder->join('locations current_locations', 'current_locations.id = assets.current_location_id', 'left');
        $builder->join('asset_photos primary_photo', 'primary_photo.asset_id = assets.id AND primary_photo.is_primary = 1', 'left');
        $builder->where('asset_folders.folder_id', $folderId);

        $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') {
            $builder->groupStart()
                ->like('assets.serial_number', $search)
                ->orLike('assets.model_name', $search)
                ->orLike('assets.current_location_detail', $search)
                ->orLike('brands.name', $search)
                ->orLike('asset_categories.name', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->orderBy('assets.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        $items = array_map(function (array $item): array {
            $item['photo_url'] = $this->buildAssetPhotoUrl($item);
            unset($item['photo_id']);

            $item['relations'] = [
                'brand' => $this->relation($item['brand_id'] ?? null, $item['brand_name'] ?? null),
                'asset_category' => $this->relation($item['asset_category_id'] ?? null, $item['asset_category_name'] ?? null),
                'source_location' => $this->relation($item['source_location_id'] ?? null, $item['source_location_name'] ?? null),
                'current_location' => $this->relation($item['current_location_id'] ?? null, $item['current_location_name'] ?? null),
            ];

            return $item;
        }, $items);

        return $this->respondPaginated(
            'Folder assets fetched',
            $items,
            [
                'folder' => $folder,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            ResponseInterface::HTTP_OK
        );
    }

    public function attachAssets(int $folderId): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.assign');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $assetIds = $payload['asset_ids'] ?? (isset($payload['asset_id']) ? [$payload['asset_id']] : null);
        if (! is_array($assetIds)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['asset_ids' => ['The asset_ids field must be an array.']]
            );
        }

        try {
            (new FolderService())->addAssetsToFolder($folderId, $assetIds);

            return $this->respondSuccess(
                'Assets attached to folder successfully',
                [
                    'folder' => (new FolderService())->folderDetail($folderId),
                    'asset_ids' => array_values(array_unique(array_map('intval', $assetIds))),
                ],
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            $lower = strtolower($e->getMessage());
            $status = str_contains($lower, 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    public function detachAsset(int $folderId, int $assetId): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.assign');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            (new FolderService())->removeFolderFromAsset($assetId, $folderId);

            return $this->respondSuccess(
                'Asset removed from folder successfully',
                [
                    'folder_id' => $folderId,
                    'asset_id' => $assetId,
                ]
            );
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    public function assetFolders(int $assetId): ResponseInterface
    {
        $user = $this->requireAssetPermission($assetId, false);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        return $this->respondSuccess(
            'Asset folders fetched',
            (new FolderService())->foldersForAsset($assetId)
        );
    }

    public function memberships(): ResponseInterface
    {
        $user = $this->requireFolderPermission('folders.read');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $assetIds = $payload['asset_ids'] ?? null;
        if (! is_array($assetIds)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['asset_ids' => ['The asset_ids field is required and must be an array.']]
            );
        }

        if (count($assetIds) > 500) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['asset_ids' => ['Maximum 500 asset_ids per request.']]
            );
        }

        $type = array_key_exists('type', $payload) ? trim((string) $payload['type']) : null;
        if ($type === '') {
            $type = null;
        }

        try {
            $memberships = (new FolderService())->membershipsForAssets($assetIds, $type);

            return $this->respondSuccess('Folder memberships fetched', [
                'memberships' => $memberships,
            ]);
        } catch (RuntimeException $e) {
            return $this->respondError($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function syncAssetFolders(int $assetId): ResponseInterface
    {
        $user = $this->requireAssetPermission($assetId, true);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (! array_key_exists('folder_ids', $payload) || ! is_array($payload['folder_ids'])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['folder_ids' => ['The folder_ids field is required and must be an array.']]
            );
        }

        try {
            $folders = (new FolderService())->syncAssetFolders($assetId, $payload['folder_ids']);

            return $this->respondSuccess('Asset folders synced successfully', $folders);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    public function addAssetFolders(int $assetId): ResponseInterface
    {
        $user = $this->requireAssetPermission($assetId, true);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $folderIds = $payload['folder_ids'] ?? (isset($payload['folder_id']) ? [$payload['folder_id']] : null);
        if (! is_array($folderIds)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['folder_ids' => ['The folder_ids field must be an array.']]
            );
        }

        try {
            $folders = (new FolderService())->addFoldersToAsset($assetId, $folderIds);

            return $this->respondSuccess('Folder added to asset successfully', $folders, ResponseInterface::HTTP_CREATED);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    public function removeAssetFolder(int $assetId, int $folderId): ResponseInterface
    {
        $user = $this->requireAssetPermission($assetId, true);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        try {
            $folders = (new FolderService())->removeFolderFromAsset($assetId, $folderId);

            return $this->respondSuccess('Folder removed from asset successfully', $folders);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        }
    }

    private function requireFolderPermission(string $permission): User|ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if (! $this->userHasFolderPermission($user, $permission)) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        return $user;
    }

    private function requireAssetPermission(int $assetId, bool $forUpdate): User|ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $asset = model(AssetModel::class)->find($assetId);
        if ($asset === null) {
            return $this->respondError('Asset not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $authz = new AssetAuthorizationService();
        if ($forUpdate && ! $authz->canUpdateAsset($user)) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        if (! $forUpdate && ! $authz->canViewAsset($user)) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        if (
            ! $this->userHasFolderPermission($user, 'folders.read')
            && ! $this->userHasFolderPermission($user, 'folders.assign')
        ) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        if ($forUpdate && ! $this->userHasFolderPermission($user, 'folders.assign')) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        return $user;
    }

    private function userHasFolderPermission(User $user, string $permission): bool
    {
        if ($user->inGroup('admin')) {
            return true;
        }

        $allowedByGroup = match ($permission) {
            'folders.read'   => $user->inGroup('scanner', 'supervisor'),
            'folders.assign' => $user->inGroup('scanner', 'supervisor'),
            'folders.manage' => $user->inGroup('supervisor'),
            default          => false,
        };

        if ($allowedByGroup) {
            return true;
        }

        return $user->can($permission) || $user->can('folders.*');
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
        $assetId = isset($item['id']) ? (int) $item['id'] : null;
        $photoId = $item['photo_id'] ?? null;

        if ($assetId === null || $assetId <= 0 || $photoId === null || $photoId === '') {
            return null;
        }

        return site_url('api/v1/assets/' . $assetId . '/download-photo/' . $photoId);
    }
}
