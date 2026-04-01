<?php

namespace Config;

use CodeIgniter\Shield\Config\Auth as ShieldAuth;

class Auth extends ShieldAuth
{
    public string $defaultAuthenticator = 'session';

    public array $authenticationChain = [
        'session',
        'tokens',
    ];

    public bool $allowRegistration = false;

    public array $validFields = [
        'email',
        'username',
    ];
}
