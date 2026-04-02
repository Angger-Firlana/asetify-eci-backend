<?php

namespace App\Controllers\Api\V1;

use App\Models\AssetCategoryModel;
use App\Models\BrandModel;
use App\Models\LocationModel;
use CodeIgniter\HTTP\ResponseInterface;

class MasterDataController extends BaseApiController
{
    public function assetCategories(): ResponseInterface
    {
        $search = trim((string) $this->request->getGet('search'));

        $builder = model(AssetCategoryModel::class)->active();

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
