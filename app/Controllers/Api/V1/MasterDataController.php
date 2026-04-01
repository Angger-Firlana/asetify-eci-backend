<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetCategoryModel;
use App\Models\AssetTypeModel;
use App\Models\BrandModel;
use App\Models\LocationModel;
use CodeIgniter\HTTP\ResponseInterface;

class MasterDataController extends BaseApiController
{
    public function assetTypes(): ResponseInterface
    {
        $items = model(AssetTypeModel::class)
            ->active()
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->respondSuccess('Asset types fetched', $items);
    }

    public function assetCategories(): ResponseInterface
    {
        $assetTypeId = $this->request->getGet('asset_type_id');
        $search      = trim((string) $this->request->getGet('search'));

        $builder = model(AssetCategoryModel::class)
            ->active()
            ->withType();

        if ($assetTypeId !== null && $assetTypeId !== '') {
            $builder->where('asset_categories.asset_type_id', (int) $assetTypeId);
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

    public function brands(): ResponseInterface
    {
        $search = trim((string) $this->request->getGet('search'));

        $builder = model(BrandModel::class)->active();

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

    public function locations(): ResponseInterface
    {
        $locationType = trim((string) $this->request->getGet('location_type'));
        $search       = trim((string) $this->request->getGet('search'));

        $builder = model(LocationModel::class)->active();

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
}
