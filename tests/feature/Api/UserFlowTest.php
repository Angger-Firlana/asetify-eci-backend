<?php

use Tests\Support\ApiFeatureTestCase;

final class UserFlowTest extends ApiFeatureTestCase
{
    public function testAdminCanCrudUsers(): void
    {
        $token = $this->bearerTokenFor('admin');

        $email = sprintf('user.%s@asetify.test', bin2hex(random_bytes(4)));

        $createResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/users', [
                'username' => 'user_' . bin2hex(random_bytes(3)),
                'email'    => $email,
                'password' => 'Password123!',
                'group'    => 'scanner',
                'active'   => 1,
            ]);

        $createResponse->assertStatus(201);
        $createJson = $this->parseJsonResponse($createResponse);
        $userId     = (int) $createJson['data']['id'];

        $this->assertSame($email, $createJson['data']['email']);
        $this->assertSame('scanner', $createJson['data']['role']);
        $this->assertTrue($createJson['data']['active']);

        $listResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/users?search=' . urlencode($email));

        $listResponse->assertStatus(200);
        $listJson = $this->parseJsonResponse($listResponse);
        $this->assertGreaterThanOrEqual(1, count($listJson['data']['items']));

        $found = array_values(array_filter($listJson['data']['items'], static fn (array $row): bool => (int) $row['id'] === $userId));
        $this->assertNotEmpty($found);

        $showResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/users/' . $userId);

        $showResponse->assertStatus(200);

        $updateResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/users/' . $userId, [
                'group'  => 'supervisor',
                'active' => 0,
            ]);

        $updateResponse->assertStatus(200);
        $updateJson = $this->parseJsonResponse($updateResponse);
        $this->assertSame('supervisor', $updateJson['data']['role']);
        $this->assertFalse($updateJson['data']['active']);

        $deleteResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/users/' . $userId);

        $deleteResponse->assertStatus(200);

        $showAfterDeleteResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/users/' . $userId);

        $showAfterDeleteResponse->assertStatus(404);
    }

    public function testScannerCannotManageUsers(): void
    {
        $token = $this->bearerTokenFor('scanner01');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/users');

        $response->assertStatus(403);
    }
}
