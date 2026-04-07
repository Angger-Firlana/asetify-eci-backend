<?php

use Tests\Support\ApiFeatureTestCase;

final class MasterDataManagementTest extends ApiFeatureTestCase
{
    public function testAdminCanCreateBrand(): void
    {
        $token = $this->bearerTokenFor('admin');
        $name  = 'Asus ' . strtolower(bin2hex(random_bytes(2)));
        $code  = strtolower(str_replace(' ', '-', $name));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/masters/brands', [
                'name' => $name,
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame($code, $json['data']['code']);
        $this->assertSame(strtolower($name), $json['data']['name']);
        $this->seeInDatabase('brands', [
            'code' => $code,
            'name' => strtolower($name),
        ]);
    }

    public function testScannerCanCreateBrandWithInlinePermission(): void
    {
        $token = $this->bearerTokenFor('scanner01');
        $name  = 'Inline Brand ' . strtolower(bin2hex(random_bytes(2)));
        $code  = strtolower(str_replace(' ', '-', $name));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/masters/brands', [
                'name' => $name,
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame($code, $json['data']['code']);
        $this->assertArrayHasKey('id', $json['data']);
    }

    public function testAdminCanCreateModelForBrand(): void
    {
        $token   = $this->bearerTokenFor('admin');
        $brandId = $this->idFromCodeForTest('brands', 'dell');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/masters/models', [
                'brand_id' => $brandId,
                'name' => 'Latitude 5450',
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame('latitude 5450', $json['data']['name']);
        $this->assertSame('Dell', $json['data']['brand_name']);
        $this->seeInDatabase('asset_models', [
            'brand_id' => $brandId,
            'code' => 'latitude-5450',
            'name' => 'latitude 5450',
        ]);
    }

    public function testAdminCanCreateTypeViaTypesEndpoint(): void
    {
        $token = $this->bearerTokenFor('admin');
        $name  = 'Scanner Barcode ' . strtolower(bin2hex(random_bytes(2)));
        $code  = strtolower(str_replace(' ', '-', $name));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/masters/types', [
                'name' => $name,
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame($code, $json['data']['code']);
        $this->assertSame(strtolower($name), $json['data']['name']);
        $this->seeInDatabase('asset_categories', [
            'code' => $code,
            'name' => strtolower($name),
        ]);
    }

    public function testScannerCanCreateTypeWithInlinePermission(): void
    {
        $token = $this->bearerTokenFor('scanner01');
        $name  = 'Inline Type ' . strtolower(bin2hex(random_bytes(2)));
        $code  = strtolower(str_replace(' ', '-', $name));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/masters/types', [
                'name' => $name,
            ]);

        $response->assertStatus(201);
        $json = $this->parseJsonResponse($response);

        $this->assertTrue($json['success']);
        $this->assertSame($code, $json['data']['code']);
        $this->assertArrayHasKey('id', $json['data']);
    }

    public function testBrandsCanBeFetchedByIdOrName(): void
    {
        $adminToken = $this->bearerTokenFor('admin');
        $name       = 'MSI ' . strtolower(bin2hex(random_bytes(2)));
        $lowerName  = strtolower($name);

        $createResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/masters/brands', [
                'name' => $name,
            ]);

        $createResponse->assertStatus(201);
        $created = $this->parseJsonResponse($createResponse)['data'];

        $scannerToken = $this->bearerTokenFor('scanner01');

        $byIdResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $scannerToken])
            ->get('api/v1/masters/brands?id=' . $created['id']);

        $byIdResponse->assertStatus(200);
        $byIdJson = $this->parseJsonResponse($byIdResponse);

        $this->assertCount(1, $byIdJson['data']);
        $this->assertSame($lowerName, $byIdJson['data'][0]['name']);

        $byNameResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $scannerToken])
            ->get('api/v1/masters/brands?name=' . strtoupper($name));

        $byNameResponse->assertStatus(200);
        $byNameJson = $this->parseJsonResponse($byNameResponse);

        $this->assertCount(1, $byNameJson['data']);
        $this->assertSame((int) $created['id'], (int) $byNameJson['data'][0]['id']);
    }

    public function testModelsCanBeFetchedByIdOrName(): void
    {
        $adminToken = $this->bearerTokenFor('admin');
        $brandId    = $this->idFromCodeForTest('brands', 'dell');
        $name       = 'Vostro ' . strtolower(bin2hex(random_bytes(2)));
        $lowerName  = strtolower($name);

        $createResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/masters/models', [
                'brand_id' => $brandId,
                'name' => $name,
            ]);

        $createResponse->assertStatus(201);
        $created = $this->parseJsonResponse($createResponse)['data'];

        $scannerToken = $this->bearerTokenFor('scanner01');

        $byIdResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $scannerToken])
            ->get('api/v1/masters/models?id=' . $created['id']);

        $byIdResponse->assertStatus(200);
        $byIdJson = $this->parseJsonResponse($byIdResponse);

        $this->assertCount(1, $byIdJson['data']);
        $this->assertSame($lowerName, $byIdJson['data'][0]['name']);

        $byNameResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $scannerToken])
            ->get('api/v1/masters/models?name=' . strtoupper($name));

        $byNameResponse->assertStatus(200);
        $byNameJson = $this->parseJsonResponse($byNameResponse);

        $this->assertCount(1, $byNameJson['data']);
        $this->assertSame((int) $created['id'], (int) $byNameJson['data'][0]['id']);
    }

    public function testSupervisorCannotCreateBrand(): void
    {
        $token   = $this->bearerTokenFor('supervisor01');
        $brandId = $this->idFromCodeForTest('brands', 'dell');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/masters/models', [
                'brand_id' => $brandId,
                'name' => 'Blocked Model',
            ]);

        $response->assertStatus(403);
        $json = $this->parseJsonResponse($response);

        $this->assertFalse($json['success']);
        $this->dontSeeInDatabase('asset_models', [
            'brand_id' => $brandId,
            'name' => 'blocked model',
        ]);
    }

    private function idFromCodeForTest(string $table, string $code): int
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
