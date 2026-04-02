<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class CorsFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testPreflightRequestReturnsCorsHeadersForApiRoute(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type',
        ])->call('OPTIONS', 'api/v1/auth/login');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
    }

    public function testRegularApiRequestIncludesCorsHeadersForAllowedOrigin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->get('/');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response->assertHeader('Vary');
    }
}
