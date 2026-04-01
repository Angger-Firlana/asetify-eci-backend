<?php

use Tests\Support\ApiFeatureTestCase;

final class AssetPhotoManagementTest extends ApiFeatureTestCase
{
    public function testSupervisorCanAddPhotoToExistingAsset(): void
    {
        $supervisorId = $this->db->table('users')
            ->select('id')
            ->where('username', 'supervisor01')
            ->get()
            ->getRow('id');

        $asset  = $this->createExistingAssetWithPhotos((int) $supervisorId, 1);
        $upload = $this->createTemporaryUpload((int) $supervisorId);
        $token  = $this->bearerTokenFor('supervisor01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/assets/' . $asset['id'] . '/photos', [
                'photo_upload_ids' => [$upload['upload_id']],
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertCount(1, $json['data']);
        $this->seeNumRecords(2, 'asset_photos', ['asset_id' => $asset['id']]);
        $this->seeInDatabase('asset_photo_uploads', [
            'upload_id' => $upload['upload_id'],
            'asset_id' => $asset['id'],
        ]);
        $this->seeInDatabase('asset_audit_logs', [
            'asset_id' => $asset['id'],
            'action' => 'photo_add',
            'field_name' => 'photo',
            'changed_by' => (int) $supervisorId,
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testScannerCannotAddPhotoToExistingAsset(): void
    {
        $supervisorId = (int) $this->db->table('users')->select('id')->where('username', 'supervisor01')->get()->getRow('id');
        $scannerId    = (int) $this->db->table('users')->select('id')->where('username', 'scanner01')->get()->getRow('id');

        $asset  = $this->createExistingAssetWithPhotos($supervisorId, 1);
        $upload = $this->createTemporaryUpload($scannerId);
        $token  = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/assets/' . $asset['id'] . '/photos', [
                'photo_upload_ids' => [$upload['upload_id']],
            ]);

        $response->assertStatus(403);
        $json = $this->parseJsonResponse($response);

        $this->assertFalse($json['success']);
        $this->seeNumRecords(1, 'asset_photos', ['asset_id' => $asset['id']]);
        $this->dontSeeInDatabase('asset_audit_logs', [
            'asset_id' => $asset['id'],
            'action' => 'photo_add',
            'changed_by' => $scannerId,
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testSupervisorCanDeleteExistingPrimaryPhotoAndPrimaryIsReassigned(): void
    {
        $supervisorId = (int) $this->db->table('users')->select('id')->where('username', 'supervisor01')->get()->getRow('id');
        $asset        = $this->createExistingAssetWithPhotos($supervisorId, 2);
        $photos       = model(\App\Models\AssetPhotoModel::class)->findForAsset($asset['id']);
        $primaryPhoto = $photos[0];
        $token        = $this->bearerTokenFor('supervisor01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/assets/' . $asset['id'] . '/photos/' . $primaryPhoto['id']);

        $response->assertStatus(200);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->dontSeeInDatabase('asset_photos', ['id' => $primaryPhoto['id']]);
        $this->seeNumRecords(1, 'asset_photos', ['asset_id' => $asset['id']]);
        $this->seeInDatabase('asset_photos', [
            'asset_id' => $asset['id'],
            'is_primary' => 1,
        ]);
        $this->seeInDatabase('asset_audit_logs', [
            'asset_id' => $asset['id'],
            'action' => 'photo_delete',
            'field_name' => 'photo',
            'changed_by' => $supervisorId,
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }

    public function testScannerCannotDeleteExistingPhoto(): void
    {
        $supervisorId = (int) $this->db->table('users')->select('id')->where('username', 'supervisor01')->get()->getRow('id');
        $scannerId    = (int) $this->db->table('users')->select('id')->where('username', 'scanner01')->get()->getRow('id');
        $asset        = $this->createExistingAssetWithPhotos($supervisorId, 2);
        $photo        = model(\App\Models\AssetPhotoModel::class)->findForAsset($asset['id'])[0];
        $token        = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/assets/' . $asset['id'] . '/photos/' . $photo['id']);

        $response->assertStatus(403);
        $json = $this->parseJsonResponse($response);

        $this->assertFalse($json['success']);
        $this->seeInDatabase('asset_photos', ['id' => $photo['id']]);
        $this->dontSeeInDatabase('asset_audit_logs', [
            'asset_id' => $asset['id'],
            'action' => 'photo_delete',
            'changed_by' => $scannerId,
        ]);

        $this->trackAssetPhotoFiles($asset['id']);
    }
}
