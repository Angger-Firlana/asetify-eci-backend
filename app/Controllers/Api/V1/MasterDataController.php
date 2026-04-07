<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetCategoryModel;
use App\Models\AssetModelMasterModel;
use App\Models\BrandModel;
use App\Models\LocationModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

class MasterDataController extends BaseApiController
{
    public function assetCategories(): ResponseInterface
    {
        $search = trim((string) $this->request->getGet('search'));
        $id     = (int) ($this->request->getGet('id') ?? 0);
        $name   = $this->normalizeMasterName((string) ($this->request->getGet('name') ?? ''));

        $builder = model(AssetCategoryModel::class)->active();

        if ($id > 0) {
            $builder->where('asset_categories.id', $id);
        }

        if ($name !== '') {
            $builder->where('asset_categories.name', $name);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('asset_categories.name', $search)
                ->orLike('asset_categories.code', $search)
                ->groupEnd();
        }

        $items = $builder
            ->orderBy('asset_categories.name', 'ASC')
            ->findAll();

        return $this->respondSuccess('Asset categories fetched', $items);
    }

    public function types(): ResponseInterface
    {
        return $this->assetCategories();
    }

    public function brands(): ResponseInterface
    {
        $search = trim((string) $this->request->getGet('search'));
        $id     = (int) ($this->request->getGet('id') ?? 0);
        $name   = $this->normalizeMasterName((string) ($this->request->getGet('name') ?? ''));

        $builder = model(BrandModel::class)->active();

        if ($id > 0) {
            $builder->where('id', $id);
        }

        if ($name !== '') {
            $builder->where('name', $name);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('name', $search)
                ->orLike('code', $search)
                ->groupEnd();
        }

        $items = $builder
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->respondSuccess('Brands fetched', $items);
    }

    public function models(): ResponseInterface
    {
        $search  = trim((string) $this->request->getGet('search'));
        $id      = (int) ($this->request->getGet('id') ?? 0);
        $name    = $this->normalizeMasterName((string) ($this->request->getGet('name') ?? ''));
        $brandId = (int) ($this->request->getGet('brand_id') ?? 0);

        $builder = model(AssetModelMasterModel::class)->activeWithBrand();

        if ($id > 0) {
            $builder->where('asset_models.id', $id);
        }

        if ($name !== '') {
            $builder->where('asset_models.name', $name);
        }

        if ($brandId > 0) {
            $builder->where('asset_models.brand_id', $brandId);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('asset_models.name', $search)
                ->orLike('asset_models.code', $search)
                ->orLike('brands.name', $search)
                ->groupEnd();
        }

        $items = $builder
            ->orderBy('brands.name', 'ASC')
            ->orderBy('asset_models.name', 'ASC')
            ->findAll();

        return $this->respondSuccess('Models fetched', $items);
    }

    public function locations(): ResponseInterface
    {
        $locationType = trim((string) $this->request->getGet('location_type'));
        $search       = trim((string) $this->request->getGet('search'));
        $id           = (int) ($this->request->getGet('id') ?? 0);
        $name         = $this->normalizeMasterName((string) ($this->request->getGet('name') ?? ''));

        $builder = model(LocationModel::class)->active();

        if ($id > 0) {
            $builder->where('id', $id);
        }

        if ($name !== '') {
            $builder->where('name', $name);
        }

        if ($locationType !== '') {
            $builder->where('location_type', $locationType);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('name', $search)
                ->orLike('code', $search)
                ->groupEnd();
        }

        $items = $builder
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->respondSuccess('Locations fetched', $items);
    }

    public function storeBrand(): ResponseInterface
    {
        $user = $this->requirePermission('masters.manage', 'masters.create-inline');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (! $this->validateData($payload, [
            'name' => 'required|string|max_length[100]',
            'code' => 'permit_empty|string|max_length[50]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        $model = model(BrandModel::class);
        $name  = $this->normalizeMasterName((string) $payload['name']);
        $code  = $this->normalizeMasterCode($payload['code'] ?? $name);

        if ($model->where('code', $code)->first() !== null) {
            return $this->respondError(
                'Brand code already exists.',
                ResponseInterface::HTTP_CONFLICT,
                ['code' => ['The generated or provided code is already in use.']]
            );
        }

        if ($model->where('name', $name)->first() !== null) {
            return $this->respondError(
                'Brand name already exists.',
                ResponseInterface::HTTP_CONFLICT,
                ['name' => ['The brand name already exists.']]
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'code' => $code,
            'name' => $name,
            'is_active' => (int) ($payload['is_active'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $model->insert($data);
        $brand = $model->find((int) $model->getInsertID());

        return $this->respondSuccess('Brand created successfully', $brand, ResponseInterface::HTTP_CREATED);
    }

    public function storeAssetCategory(): ResponseInterface
    {
        $user = $this->requirePermission('masters.manage', 'masters.create-inline');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (! $this->validateData($payload, [
            'name' => 'required|string|max_length[100]',
            'code' => 'permit_empty|string|max_length[50]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        $model = model(AssetCategoryModel::class);
        $name  = $this->normalizeMasterName((string) $payload['name']);
        $code  = $this->normalizeMasterCode($payload['code'] ?? $name);

        if ($model->where('code', $code)->first() !== null) {
            return $this->respondError(
                'Type code already exists.',
                ResponseInterface::HTTP_CONFLICT,
                ['code' => ['The generated or provided code is already in use.']]
            );
        }

        if ($model->where('name', $name)->first() !== null) {
            return $this->respondError(
                'Type name already exists.',
                ResponseInterface::HTTP_CONFLICT,
                ['name' => ['The type name already exists.']]
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'code' => $code,
            'name' => $name,
            'is_active' => (int) ($payload['is_active'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $model->insert($data);
        $category = $model->find((int) $model->getInsertID());

        return $this->respondSuccess('Type created successfully', $category, ResponseInterface::HTTP_CREATED);
    }

    public function storeType(): ResponseInterface
    {
        return $this->storeAssetCategory();
    }

    public function storeModel(): ResponseInterface
    {
        $user = $this->requirePermission('masters.manage');
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        if (! $this->validateData($payload, [
            'brand_id' => 'required|integer',
            'name' => 'required|string|max_length[150]',
            'code' => 'permit_empty|string|max_length[50]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        $brandId = (int) $payload['brand_id'];
        if (model(BrandModel::class)->find($brandId) === null) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['brand_id' => ['The brand_id is invalid.']]
            );
        }

        $model = model(AssetModelMasterModel::class);
        $name  = $this->normalizeMasterName((string) $payload['name']);
        $code  = $this->normalizeMasterCode($payload['code'] ?? $name);

        if ($model->where('brand_id', $brandId)->where('code', $code)->first() !== null) {
            return $this->respondError(
                'Model code already exists for this brand.',
                ResponseInterface::HTTP_CONFLICT,
                ['code' => ['The generated or provided code is already in use for this brand.']]
            );
        }

        if ($model->where('brand_id', $brandId)->where('name', $name)->first() !== null) {
            return $this->respondError(
                'Model name already exists for this brand.',
                ResponseInterface::HTTP_CONFLICT,
                ['name' => ['The model name already exists for this brand.']]
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'brand_id' => $brandId,
            'code' => $code,
            'name' => $name,
            'is_active' => (int) ($payload['is_active'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $model->insert($data);
        $created = model(AssetModelMasterModel::class)->findWithBrand((int) $model->getInsertID());

        return $this->respondSuccess('Model created successfully', $created, ResponseInterface::HTTP_CREATED);
    }

    private function requirePermission(string ...$permissions): User|ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return $user;
            }
        }

        if ($permissions === []) {
            return $user;
        }

        if (! $user->can($permissions[0])) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        return $user;
    }

    private function normalizeMasterCode(string $value): string
    {
        $code = strtolower(trim($value));
        $code = preg_replace('/[^a-z0-9]+/i', '-', $code) ?? '';
        $code = trim($code, '-');

        if ($code !== '') {
            return $code;
        }

        return 'item-' . strtolower(bin2hex(random_bytes(4)));
    }

    private function normalizeMasterName(string $value): string
    {
        return strtolower(trim($value));
    }
}
