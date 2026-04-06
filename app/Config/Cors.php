<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    /**
     * The default CORS configuration.
     *
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        'allowedOrigins' => [
            'http://localhost:5173',
            'http://localhost',
            'capacitor://localhost',
            'https://asetify.berdikari.tech'
        ],
        'allowedOriginsPatterns' => [
            'http://localhost:\d+',
            'http://127\.0\.0\.1:\d+',
            'https://asetify\.berdikari\.tech',
            'https://localhost:\d+',
            'https://localhost'
        ],

        'supportsCredentials' => true,

        'allowedHeaders' => [
            'Accept',
            'Content-Type',
            'Authorization',
            'X-Requested-With',
        ],

        'exposedHeaders' => [],

        'allowedMethods' => [
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
        ],

        'maxAge' => 7200,
    ];
}
