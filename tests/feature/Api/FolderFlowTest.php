<?php

use Tests\Support\ApiFeatureTestCase;

final class FolderFlowTest extends ApiFeatureTestCase
{
    public function testAdminCanCreateFoldersAndFetchTree(): void
    {
        $token = $this->bearerTokenFor('admin');

        $rootResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders', [
                'name' => 'Lokasi',
                'type' => 'lokasi',
            ]);

        $rootResponse->assertStatus(201);
        $rootJson = $this->parseJsonResponse($rootResponse);
        $rootId   = (int) $rootJson['data']['id'];

        $childResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders', [
                'name' => 'HO',
                'type' => 'lokasi',
                'parent_id' => $rootId,
            ]);

        $childResponse->assertStatus(201);

        $treeResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/folders/tree');

        $treeResponse->assertStatus(200);
        $treeJson = $this->parseJsonResponse($treeResponse);

        $this->assertCount(1, $treeJson['data']);
        $this->assertSame('Lokasi', $treeJson['data'][0]['name']);
        $this->assertCount(1, $treeJson['data'][0]['children']);
        $this->assertSame('HO', $treeJson['data'][0]['children'][0]['name']);
    }

    public function testFolderCreateRejectsDuplicateNameTypeAndParent(): void
    {
        $token = $this->bearerTokenFor('admin');
        $root  = $this->createFolderFixture('Lokasi', 'lokasi');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders', [
                'name' => ' lokasi ',
                'type' => 'lokasi',
                'parent_id' => $root['id'],
            ]);

        $response->assertStatus(201);

        $duplicateResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders', [
                'name' => 'LOKASI',
                'type' => 'lokasi',
                'parent_id' => $root['id'],
            ]);

        $duplicateResponse->assertStatus(422);
        $json = $this->parseJsonResponse($duplicateResponse);
        $this->assertFalse($json['success']);
    }

    public function testScannerCanSyncAssetFoldersAndAssetDetailShowsThem(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $root      = $this->createFolderFixture('Lokasi', 'lokasi');
        $child     = $this->createFolderFixture('Gudang Kasir FA', 'lokasi', $root['id']);
        $token     = $this->bearerTokenFor('scanner01');

        $syncResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/assets/' . $asset['id'] . '/folders', [
                'folder_ids' => [$root['id'], $child['id'], $child['id']],
            ]);

        $syncResponse->assertStatus(200);
        $syncJson = $this->parseJsonResponse($syncResponse);

        $this->assertCount(2, $syncJson['data']);
        $this->seeNumRecords(2, 'asset_folders', ['asset_id' => $asset['id']]);

        $showResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/assets/' . $asset['id']);

        $showResponse->assertStatus(200);
        $showJson = $this->parseJsonResponse($showResponse);

        $folderNames = array_map(static fn (array $item): string => $item['name'], $showJson['data']['folders']);

        $this->assertContains('Lokasi', $folderNames);
        $this->assertContains('Gudang Kasir FA', $folderNames);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testFolderAssetsEndpointReturnsAssetsWithIdsAndNoDuplicatePivot(): void
    {
        $scannerId = $this->userId('scanner01');
        $assetOne  = $this->createExistingAssetWithPhotos($scannerId, 1);
        $assetTwo  = $this->createExistingAssetWithPhotos($scannerId, 1);
        $folder    = $this->createFolderFixture('Laptop Prioritas', 'kategori');
        $token     = $this->bearerTokenFor('scanner01');

        $attachResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders/' . $folder['id'] . '/assets', [
                'asset_ids' => [$assetOne['id'], $assetTwo['id'], $assetTwo['id']],
            ]);

        $attachResponse->assertStatus(201);
        $this->seeNumRecords(2, 'asset_folders', ['folder_id' => $folder['id']]);

        $listResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/folders/' . $folder['id'] . '/assets');

        $listResponse->assertStatus(200);
        $listJson = $this->parseJsonResponse($listResponse);

        $this->assertSame($folder['id'], $listJson['meta']['folder']['id']);
        $this->assertCount(2, $listJson['data']['items']);
        $this->assertArrayHasKey('current_location_detail', $listJson['data']['items'][0]);
        $this->assertArrayHasKey('relations', $listJson['data']['items'][0]);

        $assetIds = array_map(static fn (array $item): int => (int) $item['id'], $listJson['data']['items']);
        $this->assertContains($assetOne['id'], $assetIds);
        $this->assertContains($assetTwo['id'], $assetIds);

        $this->trackAssetPhotoFiles($assetOne['id']);
        $this->trackAssetPhotoFiles($assetTwo['id']);
    }

    public function testBatchMembershipEndpointReturnsFolderIdsPerAsset(): void
    {
        $scannerId = $this->userId('scanner01');
        $assetOne  = $this->createExistingAssetWithPhotos($scannerId, 1);
        $assetTwo  = $this->createExistingAssetWithPhotos($scannerId, 1);
        $folderA   = $this->createFolderFixture('Lokasi A', 'lokasi');
        $folderB   = $this->createFolderFixture('Lokasi B', 'lokasi');

        $token = $this->bearerTokenFor('admin');

        $attachResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders/' . $folderA['id'] . '/assets', [
                'asset_ids' => [$assetOne['id'], $assetTwo['id']],
            ]);
        $attachResponse->assertStatus(201);

        $attachSecondResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders/' . $folderB['id'] . '/assets', [
                'asset_ids' => [$assetOne['id']],
            ]);
        $attachSecondResponse->assertStatus(201);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/folders/memberships', [
                'asset_ids' => [$assetOne['id'], $assetTwo['id']],
            ]);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertArrayHasKey('memberships', $json['data']);
        $this->assertArrayHasKey((string) $assetOne['id'], $json['data']['memberships']);
        $this->assertArrayHasKey((string) $assetTwo['id'], $json['data']['memberships']);

        $this->assertContains($folderA['id'], $json['data']['memberships'][(string) $assetOne['id']]);
        $this->assertContains($folderB['id'], $json['data']['memberships'][(string) $assetOne['id']]);
        $this->assertContains($folderA['id'], $json['data']['memberships'][(string) $assetTwo['id']]);

        $this->trackAssetPhotoFiles($assetOne['id']);
        $this->trackAssetPhotoFiles($assetTwo['id']);
    }
}
