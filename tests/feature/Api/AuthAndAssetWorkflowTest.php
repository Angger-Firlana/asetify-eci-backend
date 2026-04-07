<?php

use App\Models\AssetPhotoModel;
use Tests\Support\ApiFeatureTestCase;

final class AuthAndAssetWorkflowTest extends ApiFeatureTestCase
{
    public function testLoginReturnsBearerTokenAndRole(): void
    {
        $response = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'identity' => 'scanner01',
                'password' => 'Password123!',
            ]);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame('Bearer', $json['data']['token_type']);
        $this->assertSame('scanner', $json['data']['user']['role']);
        $this->assertNotEmpty($json['data']['access_token']);
    }

    public function testCheckSerialNumberReturnsExistingAssetPermissionsForScanner(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $token     = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/assets/check-sn?serial_number=' . $asset['serial_number']);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['data']['exists']);
        $this->assertTrue($json['data']['can_edit']);
        $this->assertFalse($json['data']['can_edit_serial_number']);
        $this->assertFalse($json['data']['can_manage_existing_photos']);
        $this->assertSame($asset['serial_number'], $json['data']['serial_number']);
        $this->assertSame('Dell', $json['data']['asset']['relations']['brand']['name']);
        $this->assertSame('Laptop', $json['data']['asset']['relations']['asset_category']['name']);
        $this->assertSame('Kantor Jakarta', $json['data']['asset']['relations']['current_location']['name']);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testCheckSerialNumberReturnsNullPhotoUrlWhenDuplicateAssetHasNoPhoto(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 0);
        $token     = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/assets/check-sn?serial_number=' . $asset['serial_number']);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['data']['exists']);
        $this->assertArrayHasKey('photo_url', $json['data']['asset']);
        $this->assertNull($json['data']['asset']['photo_url']);
    }

    public function testCreateAssetCreatesPhotoAndScanLogWithoutCreateAuditTrail(): void
    {
        $scannerId = $this->userId('scanner01');
        $upload    = $this->createTemporaryUpload($scannerId);
        $token     = $this->bearerTokenFor('scanner01');
        $serial    = 'SN-CREATE-' . bin2hex(random_bytes(3));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/assets', $this->baseCreateAssetPayload($serial, [$upload['upload_id']]));

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->seeInDatabase('assets', ['serial_number' => $serial]);

        $assetId = (int) $this->grabFromDatabase('assets', 'id', ['serial_number' => $serial]);

        $this->seeNumRecords(1, 'asset_photos', ['asset_id' => $assetId]);
        $this->seeInDatabase('asset_scan_logs', [
            'asset_id' => $assetId,
            'serial_number' => $serial,
            'result_status' => 'success',
        ]);
        $this->seeInDatabase('asset_movements', [
            'asset_id' => $assetId,
            'moved_by' => $scannerId,
        ]);
        $this->seeInDatabase('asset_photo_uploads', [
            'upload_id' => $upload['upload_id'],
            'asset_id' => $assetId,
        ]);
        $this->seeNumRecords(0, 'asset_audit_logs', [
            'asset_id' => $assetId,
            'action' => 'create',
        ]);

        $this->trackAssetPhotoFiles($assetId);
    }

    public function testCreateAssetAcceptsSinglePhotoUploadIdPayload(): void
    {
        $scannerId = $this->userId('scanner01');
        $upload    = $this->createTemporaryUpload($scannerId);
        $token     = $this->bearerTokenFor('scanner01');
        $serial    = 'SN-SINGLE-' . bin2hex(random_bytes(3));
        $payload   = $this->baseCreateAssetPayload($serial, []);
        unset($payload['photo_upload_ids']);
        $payload['photo_upload_id'] = $upload['upload_id'];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/assets', $payload);

        $response->assertStatus(201);
        $assetId = (int) $this->grabFromDatabase('assets', 'id', ['serial_number' => $serial]);

        $this->seeNumRecords(1, 'asset_photos', ['asset_id' => $assetId]);
        $this->seeInDatabase('asset_photo_uploads', [
            'upload_id' => $upload['upload_id'],
            'asset_id' => $assetId,
        ]);

        $this->trackAssetPhotoFiles($assetId);
    }

    public function testDownloadPhotoIsPublicAndServedInline(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $photo     = model(AssetPhotoModel::class)->findForAsset($asset['id'])[0];

        $response = $this->get('api/v1/assets/' . $asset['id'] . '/download-photo/' . $photo['id']);

        $response->assertStatus(200);
        $this->assertNotSame('', (string) $response->response()->getBody());
        $this->assertStringStartsWith("\x89PNG", (string) $response->response()->getBody());

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testScannerCannotEditSerialNumberOfExistingAsset(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $token     = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/assets/' . $asset['id'], [
                'serial_number' => 'SN-CHANGED-' . bin2hex(random_bytes(2)),
                'notes' => 'Should not update',
            ]);

        $response->assertStatus(403);
        $json = $this->parseJsonResponse($response);

        $this->assertFalse($json['success']);
        $this->seeInDatabase('assets', [
            'id' => $asset['id'],
            'serial_number' => $asset['serial_number'],
        ]);
        $this->dontSeeInDatabase('asset_audit_logs', [
            'asset_id' => $asset['id'],
            'field_name' => 'serial_number',
            'action' => 'update',
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testScannerCanUpdateAllowedFieldsAndAuditIsRecorded(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $token     = $this->bearerTokenFor('scanner01');
        $newLocationId = $this->db->table('locations')->select('id')->where('code', 'store-bandung')->get()->getRow('id');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/assets/' . $asset['id'], [
                'notes' => 'Scanner updated note',
                'current_location_id' => (int) $newLocationId,
                'scan_method' => 'manual',
                'app_platform' => 'web',
                'device_info' => 'PHPUnit',
            ]);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame('Dell', $json['data']['relations']['brand']['name']);
        $this->assertSame('Laptop', $json['data']['relations']['asset_category']['name']);
        $this->assertSame('Gudang Pusat', $json['data']['relations']['source_location']['name']);
        $this->assertSame('Toko Bandung', $json['data']['relations']['current_location']['name']);
        $this->seeInDatabase('assets', [
            'id' => $asset['id'],
            'notes' => 'Scanner updated note',
            'current_location_id' => (int) $newLocationId,
        ]);
        $this->seeInDatabase('asset_movements', [
            'asset_id' => $asset['id'],
            'moved_by' => $scannerId,
            'to_location_id' => (int) $newLocationId,
        ]);
        $this->seeInDatabase('asset_audit_logs', [
            'asset_id' => $asset['id'],
            'field_name' => 'notes',
            'action' => 'update',
            'changed_by' => $scannerId,
        ]);
        $this->seeInDatabase('asset_scan_logs', [
            'asset_id' => $asset['id'],
            'result_status' => 'success',
            'scanned_by' => $scannerId,
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testAssetListIncludesNamedForeignRelations(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $token     = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/assets?serial_number=' . $asset['serial_number']);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertCount(1, $json['data']);
        $this->assertSame('Dell', $json['data'][0]['relations']['brand']['name']);
        $this->assertSame('Laptop', $json['data'][0]['relations']['asset_category']['name']);
        $this->assertSame('Gudang Pusat', $json['data'][0]['relations']['source_location']['name']);
        $this->assertSame('Kantor Jakarta', $json['data'][0]['relations']['current_location']['name']);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testDuplicateScanLogCanBeRecordedForExistingAsset(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $token     = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/scan-logs', [
                'serial_number' => $asset['serial_number'],
                'scan_method' => 'barcode',
                'result_status' => 'duplicate',
                'message' => 'Duplicate detected from test',
                'app_platform' => 'web',
                'device_info' => 'PHPUnit',
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame($asset['serial_number'], $json['data']['serial_number']);
        $this->assertSame((int) $asset['id'], (int) $json['data']['asset_id']);
        $this->seeInDatabase('asset_scan_logs', [
            'asset_id' => $asset['id'],
            'serial_number' => $asset['serial_number'],
            'result_status' => 'duplicate',
            'scanned_by' => $scannerId,
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testScanLogsReturnNullPhotoUrlWhenAssetHasNoPhoto(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 0);
        $token     = $this->bearerTokenFor('scanner01');

        $this->db->table('asset_scan_logs')->insert([
            'serial_number' => $asset['serial_number'],
            'asset_id'      => $asset['id'],
            'scanned_by'    => $scannerId,
            'scan_method'   => 'barcode',
            'result_status' => 'duplicate',
            'message'       => 'Fixture duplicate without photo',
            'device_info'   => 'PHPUnit',
            'app_platform'  => 'web',
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/scan-logs?serial_number=' . $asset['serial_number']);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertCount(1, $json['data']);
        $this->assertArrayHasKey('photo_url', $json['data'][0]);
        $this->assertNull($json['data'][0]['photo_url']);
    }

    public function testScannerCannotAccessGlobalAuditLogs(): void
    {
        $scannerId = $this->userId('scanner01');
        $asset     = $this->createExistingAssetWithPhotos($scannerId, 1);
        $token     = $this->bearerTokenFor('scanner01');

        $this->db->table('asset_audit_logs')->insert([
            'asset_id' => $asset['id'],
            'action' => 'update',
            'changed_by' => $scannerId,
            'change_source' => 'manual_edit',
            'field_name' => 'notes',
            'old_value' => 'old',
            'new_value' => 'new',
            'change_note' => 'fixture',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/audit-logs');

        $response->assertStatus(403);
        $json = $this->parseJsonResponse($response);
        $this->assertFalse($json['success']);

        $this->trackAssetPhotoFiles($asset['id']);
    }
}
