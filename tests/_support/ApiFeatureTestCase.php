<?php

namespace Tests\Support;

use App\Models\AssetPhotoModel;
use App\Services\PhotoUploadService;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

abstract class ApiFeatureTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = [
        'App',
        'CodeIgniter\\Settings',
        'CodeIgniter\\Shield',
    ];
    protected $refresh = false;
    protected $migrateOnce = true;
    protected $seedOnce = true;
    protected $seed = [
        \App\Database\Seeds\DatabaseSeeder::class,
        \App\Database\Seeds\DevelopmentUserSeeder::class,
    ];
    protected $DBGroup = 'tests';

    /** @var string[] */
    private array $trackedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetRuntimeTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_unique($this->trackedFiles) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->trackedFiles = [];
    }

    protected function bearerTokenFor(string $identity): string
    {
        /** @var UserModel $userModel */
        $userModel = auth()->getProvider();
        /** @var User|null $user */
        $user = $userModel->withGroups()->findByCredentials(
            filter_var($identity, FILTER_VALIDATE_EMAIL) !== false
                ? ['email' => $identity]
                : ['username' => $identity]
        );

        $this->assertNotNull($user, 'Expected seeded user was not found.');

        return $user->generateAccessToken(
            'phpunit',
            ['*'],
            Time::now('UTC')->addHours(1)
        )->raw_token;
    }

    protected function createExistingAssetWithPhotos(int $createdBy, int $photoCount = 1): array
    {
        $now          = gmdate('Y-m-d H:i:s');
        $serialNumber = 'SN-TEST-' . strtoupper(bin2hex(random_bytes(4)));

        $assetData = [
            'serial_number'       => $serialNumber,
            'asset_category_id'   => $this->idFromCode('asset_categories', 'laptop'),
            'brand_id'            => $this->idFromCode('brands', 'dell'),
            'model_name'          => 'Latitude Test',
            'source_location_id'  => $this->idFromCode('locations', 'warehouse-central'),
            'current_location_id' => $this->idFromCode('locations', 'office-jakarta'),
            'condition_status'    => 'good',
            'notes'               => 'Fixture asset',
            'created_by'          => $createdBy,
            'updated_by'          => $createdBy,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        $this->db->table('assets')->insert($assetData);
        $assetId = (int) $this->db->insertID();

        for ($index = 0; $index < $photoCount; $index++) {
            $file = $this->createImageFile(sprintf('tests/fixtures/asset-%d-%d.png', $assetId, $index + 1));

            $this->db->table('asset_photos')->insert([
                'asset_id'         => $assetId,
                'file_name'        => basename($file['relative']),
                'disk'             => 'local',
                'file_path'        => $file['relative'],
                'mime_type'        => 'image/png',
                'extension'        => 'png',
                'file_size_bytes'  => $file['size'],
                'width'            => 1,
                'height'           => 1,
                'sha256_checksum'  => $file['checksum'],
                'is_primary'       => $index === 0 ? 1 : 0,
                'uploaded_by'      => $createdBy,
                'created_at'       => $now,
            ]);
        }

        return [
            'id' => $assetId,
            'serial_number' => $serialNumber,
        ];
    }

    protected function createTemporaryUpload(int $uploadedBy): array
    {
        $uploadId = 'upl_test_' . bin2hex(random_bytes(6));
        $file     = $this->createImageFile('tmp/tests/' . $uploadId . '.png');
        $now      = gmdate('Y-m-d H:i:s');

        $data = [
            'upload_id'       => $uploadId,
            'asset_id'        => null,
            'file_name'       => basename($file['relative']),
            'disk'            => 'local',
            'file_path'       => $file['relative'],
            'mime_type'       => 'image/png',
            'extension'       => 'png',
            'file_size_bytes' => $file['size'],
            'width'           => 1,
            'height'          => 1,
            'sha256_checksum' => $file['checksum'],
            'uploaded_by'     => $uploadedBy,
            'created_at'      => $now,
            'expires_at'      => gmdate('Y-m-d H:i:s', time() + 86400),
            'consumed_at'     => null,
        ];

        $this->db->table('asset_photo_uploads')->insert($data);
        $data['id'] = (int) $this->db->insertID();

        return $data;
    }

    protected function userId(string $username): int
    {
        $row = $this->db->table('users')
            ->select('id')
            ->where('username', $username)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, sprintf('Missing seeded user %s', $username));

        return (int) $row['id'];
    }

    protected function baseCreateAssetPayload(string $serialNumber, array $photoUploadIds): array
    {
        return [
            'serial_number' => $serialNumber,
            'asset_category_id' => $this->idFromCode('asset_categories', 'laptop'),
            'brand_id' => $this->idFromCode('brands', 'dell'),
            'model_name' => 'Latitude 5440',
            'source_location_id' => $this->idFromCode('locations', 'warehouse-central'),
            'current_location_id' => $this->idFromCode('locations', 'office-jakarta'),
            'condition_status' => 'good',
            'notes' => 'Created from feature test',
            'scan_method' => 'barcode',
            'app_platform' => 'web',
            'device_info' => 'PHPUnit',
            'photo_upload_ids' => $photoUploadIds,
        ];
    }

    protected function trackAssetPhotoFiles(int $assetId): void
    {
        $photos = model(AssetPhotoModel::class)->findForAsset($assetId);
        $service = new PhotoUploadService();

        foreach ($photos as $photo) {
            $this->trackedFiles[] = $service->absolutePath($photo['file_path']);
        }
    }

    protected function parseJsonResponse(object $response): array
    {
        $json = json_decode($response->getJSON(), true);
        $this->assertIsArray($json);

        return $json;
    }

    private function idFromCode(string $table, string $code): int
    {
        $row = $this->db->table($table)
            ->select('id')
            ->where('code', $code)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row, sprintf('Missing seeded row in %s for code %s', $table, $code));

        return (int) $row['id'];
    }

    private function createImageFile(string $relativePath): array
    {
        $service  = new PhotoUploadService();
        $absolute = $service->absolutePath($relativePath);
        $service->ensureDirectory(dirname($absolute));

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0MsAAAAASUVORK5CYII=', true);
        if ($png === false) {
            throw new \RuntimeException('Failed to decode fixture image.');
        }

        file_put_contents($absolute, $png);
        $this->trackedFiles[] = $absolute;

        return [
            'absolute' => $absolute,
            'relative' => $relativePath,
            'size' => filesize($absolute),
            'checksum' => hash_file('sha256', $absolute),
        ];
    }

    private function resetRuntimeTables(): void
    {
        if (! isset($this->db)) {
            return;
        }

        $this->db->disableForeignKeyChecks();

        foreach ([
            'asset_photo_uploads',
            'asset_audit_logs',
            'asset_movements',
            'asset_scan_logs',
            'asset_photos',
            'assets',
            'auth_token_logins',
            'auth_logins',
            'auth_remember_tokens',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->truncate();
            }
        }

        if ($this->db->tableExists('auth_identities')) {
            $this->db->table('auth_identities')
                ->where('type', 'access_token')
                ->delete();
        }

        $this->db->enableForeignKeyChecks();
    }
}
