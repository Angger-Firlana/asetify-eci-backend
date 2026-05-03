<?php

use Tests\Support\ApiFeatureTestCase;

final class WorkspaceFlowTest extends ApiFeatureTestCase
{
    public function testWorkspaceScanForExistingAssetLinksAndUpdatesMasterAsset(): void
    {
        $scannerId        = $this->userId('scanner01');
        $storeLocationId  = $this->db->table('locations')->select('id')->where('code', 'store-bandung')->get()->getRow('id');
        $officeLocationId = $this->db->table('locations')->select('id')->where('code', 'office-jakarta')->get()->getRow('id');
        $serialNumber     = 'WS-EXIST-' . strtoupper(bin2hex(random_bytes(3)));
        $now              = gmdate('Y-m-d H:i:s');

        $this->db->table('assets')->insert([
            'serial_number'       => $serialNumber,
            'asset_category_id'   => $this->idFromTableByCode('asset_categories', 'laptop'),
            'brand_id'            => $this->idFromTableByCode('brands', 'dell'),
            'model_name'          => 'Latitude Workspace',
            'source_location_id'  => (int) $storeLocationId,
            'current_location_id' => (int) $storeLocationId,
            'condition_status'    => 'good',
            'notes'               => 'Before workspace scan',
            'created_by'          => $scannerId,
            'updated_by'          => $scannerId,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
        $assetId = (int) $this->db->insertID();

        $token = $this->bearerTokenFor('scanner01');

        $workspaceResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces', [
                'title' => 'Penerimaan Bandung ke Jakarta',
                'source_location_id' => (int) $storeLocationId,
                'target_location_id' => (int) $officeLocationId,
                'notes' => 'Fixture workspace',
            ]);

        $workspaceResponse->assertStatus(201);
        $workspaceJson = $this->parseJsonResponse($workspaceResponse);
        $workspaceId   = (int) $workspaceJson['data']['id'];

        $scanResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces/' . $workspaceId . '/scan', [
                'serial_number' => $serialNumber,
                'scan_method' => 'barcode',
                'app_platform' => 'web',
                'device_info' => 'PHPUnit',
            ]);

        $scanResponse->assertStatus(201);
        $scanJson = $this->parseJsonResponse($scanResponse);

        $this->assertTrue($scanJson['data']['exists_in_assets']);
        $this->assertSame('matched', $scanJson['data']['match_status']);
        $this->assertSame('asset_updated', $scanJson['data']['action_status']);
        $this->assertSame($assetId, $scanJson['data']['matched_asset']['id']);
        $this->assertSame('Kantor Jakarta', $scanJson['data']['matched_asset']['relations']['current_location']['name']);

        $this->seeInDatabase('asset_workspace_items', [
            'workspace_id' => $workspaceId,
            'serial_number' => $serialNumber,
            'asset_id' => $assetId,
            'match_status' => 'matched',
            'action_status' => 'asset_updated',
        ]);
        $this->seeInDatabase('asset_workspace_item_scans', [
            'workspace_id' => $workspaceId,
            'serial_number' => $serialNumber,
            'asset_id' => $assetId,
            'result_status' => 'matched',
        ]);
        $this->seeInDatabase('assets', [
            'id' => $assetId,
            'current_location_id' => (int) $officeLocationId,
        ]);
        $this->seeInDatabase('asset_movements', [
            'asset_id' => $assetId,
            'from_location_id' => (int) $storeLocationId,
            'to_location_id' => (int) $officeLocationId,
        ]);
        $this->seeInDatabase('asset_audit_logs', [
            'asset_id' => $assetId,
            'field_name' => 'current_location_id',
            'change_source' => 'workspace_scan',
        ]);
    }

    public function testWorkspaceScanForUnknownAssetCanBeRegisteredIntoMasterAsset(): void
    {
        $storeLocationId  = $this->db->table('locations')->select('id')->where('code', 'store-bandung')->get()->getRow('id');
        $officeLocationId = $this->db->table('locations')->select('id')->where('code', 'office-jakarta')->get()->getRow('id');
        $assetCategoryId  = $this->idFromTableByCode('asset_categories', 'laptop');
        $brandId          = $this->idFromTableByCode('brands', 'dell');
        $serialNumber     = 'WS-NEW-' . strtoupper(bin2hex(random_bytes(3)));
        $token            = $this->bearerTokenFor('scanner01');

        $workspaceResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces', [
                'title' => 'Penerimaan Barang Baru',
                'source_location_id' => (int) $storeLocationId,
                'target_location_id' => (int) $officeLocationId,
            ]);

        $workspaceResponse->assertStatus(201);
        $workspaceJson = $this->parseJsonResponse($workspaceResponse);
        $workspaceId   = (int) $workspaceJson['data']['id'];

        $scanResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces/' . $workspaceId . '/scan', [
                'serial_number' => $serialNumber,
                'scan_method' => 'barcode',
                'app_platform' => 'web',
                'device_info' => 'PHPUnit',
                'asset_category_id' => $assetCategoryId,
                'brand_id' => $brandId,
                'model_name' => 'Latitude New',
                'condition_status' => 'good',
                'notes' => 'Belum ada di master',
            ]);

        $scanResponse->assertStatus(201);
        $scanJson        = $this->parseJsonResponse($scanResponse);
        $workspaceItemId = (int) $scanJson['data']['id'];

        $this->assertFalse($scanJson['data']['exists_in_assets']);
        $this->assertSame('not_found', $scanJson['data']['match_status']);
        $this->assertSame('ready_to_register', $scanJson['data']['action_status']);

        $this->seeInDatabase('asset_workspace_items', [
            'id' => $workspaceItemId,
            'workspace_id' => $workspaceId,
            'serial_number' => $serialNumber,
            'asset_id' => null,
            'action_status' => 'ready_to_register',
        ]);

        $registerResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces/' . $workspaceId . '/items/' . $workspaceItemId . '/register-asset', [
                'app_platform' => 'web',
                'device_info' => 'PHPUnit',
            ]);

        $registerResponse->assertStatus(201);
        $registerJson = $this->parseJsonResponse($registerResponse);

        $this->assertTrue($registerJson['data']['exists_in_assets']);
        $this->assertSame('asset_registered', $registerJson['data']['action_status']);

        $assetId = (int) $this->grabFromDatabase('assets', 'id', ['serial_number' => $serialNumber]);

        $this->seeInDatabase('assets', [
            'id' => $assetId,
            'serial_number' => $serialNumber,
            'asset_category_id' => $assetCategoryId,
            'brand_id' => $brandId,
            'source_location_id' => (int) $storeLocationId,
            'current_location_id' => (int) $officeLocationId,
        ]);
        $this->seeInDatabase('asset_workspace_items', [
            'id' => $workspaceItemId,
            'asset_id' => $assetId,
            'match_status' => 'matched',
            'action_status' => 'asset_registered',
        ]);
        $this->seeInDatabase('asset_workspace_item_scans', [
            'workspace_item_id' => $workspaceItemId,
            'asset_id' => $assetId,
            'result_status' => 'asset_registered',
        ]);
    }

    public function testWorkspaceDetailIncludesWorkspaceItemPhotoUrl(): void
    {
        $token            = $this->bearerTokenFor('scanner01');
        $scannerId        = $this->userId('scanner01');
        $storeLocationId  = $this->db->table('locations')->select('id')->where('code', 'store-bandung')->get()->getRow('id');
        $officeLocationId = $this->db->table('locations')->select('id')->where('code', 'office-jakarta')->get()->getRow('id');
        $serialNumber     = 'WS-PHOTO-' . strtoupper(bin2hex(random_bytes(3)));

        $workspaceResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces', [
                'title' => 'Workspace Dengan Foto',
                'source_location_id' => (int) $storeLocationId,
                'target_location_id' => (int) $officeLocationId,
            ]);

        $workspaceResponse->assertStatus(201);
        $workspaceJson = $this->parseJsonResponse($workspaceResponse);
        $workspaceId   = (int) $workspaceJson['data']['id'];

        $scanResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/workspaces/' . $workspaceId . '/scan', [
                'serial_number' => $serialNumber,
                'scan_method' => 'barcode',
                'app_platform' => 'web',
            ]);

        $scanResponse->assertStatus(201);
        $scanJson        = $this->parseJsonResponse($scanResponse);
        $workspaceItemId = (int) $scanJson['data']['id'];

        $fixturePhotoDir  = WRITEPATH . 'uploads';
        $fixturePhotoPath = $fixturePhotoDir . '\\workspace-test-photo.jpg';
        if (! is_dir($fixturePhotoDir)) {
            mkdir($fixturePhotoDir, 0777, true);
        }

        file_put_contents($fixturePhotoPath, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8QDw8PDw8PDw8PDw8QDw8PFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAgMBIgACEQEDEQH/xAAVAAEBAAAAAAAAAAAAAAAAAAAABf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhADEAAAAdQf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJf/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAwEBPwFf/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAgEBPwFf/8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQAGPwJf/8QAFBABAAAAAAAAAAAAAAAAAAAAEP/aAAgBAQABPyFf/9k='));

        $this->db->table('asset_workspace_item_photos')->insert([
            'workspace_item_id' => $workspaceItemId,
            'file_name' => 'workspace-test-photo.jpg',
            'disk' => 'local',
            'file_path' => 'workspace-test-photo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => filesize($fixturePhotoPath),
            'width' => 2,
            'height' => 1,
            'sha256_checksum' => hash_file('sha256', $fixturePhotoPath),
            'is_primary' => 1,
            'uploaded_by' => $scannerId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $photoId = (int) $this->db->insertID();

        $showResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/workspaces/' . $workspaceId);

        $showResponse->assertStatus(200);
        $showJson = $this->parseJsonResponse($showResponse);

        $this->assertSame(
            site_url('api/v1/workspaces/items/' . $workspaceItemId . '/download-photo/' . $photoId),
            $showJson['data']['items'][0]['photo_url']
        );
    }

    private function idFromTableByCode(string $table, string $code): int
    {
        $row = $this->db->table($table)
            ->select('id')
            ->where('code', $code)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, sprintf('Missing seeded row in %s for code %s', $table, $code));

        return (int) $row['id'];
    }
}
