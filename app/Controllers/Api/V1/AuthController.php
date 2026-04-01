<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;

class AuthController extends BaseApiController
{
    private const TOKEN_EXPIRY_SECONDS = 3600;

    public function login(): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'identity' => 'required|string|max_length[254]',
            'password' => 'required|string',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        $identity = trim((string) $payload['identity']);
        $password = (string) $payload['password'];

        $credentials = filter_var($identity, FILTER_VALIDATE_EMAIL) !== false
            ? ['email' => $identity, 'password' => $password]
            : ['username' => $identity, 'password' => $password];

        $result = auth('session')->check($credentials);

        if (! $result->isOK()) {
            return $this->respondError(
                'Invalid credentials',
                ResponseInterface::HTTP_UNAUTHORIZED,
                ['identity' => [$result->reason() ?? 'Invalid credentials.']]
            );
        }

        /** @var User $user */
        $user = $result->extraInfo();

        if ($user->isBanned()) {
            return $this->respondError(
                'Your account is blocked',
                ResponseInterface::HTTP_FORBIDDEN,
                ['account' => [$user->getBanMessage() ?? 'Your account is blocked.']]
            );
        }

        $expiresAt = Time::now('UTC')->addSeconds(self::TOKEN_EXPIRY_SECONDS);
        $token     = $user->generateAccessToken('api-login', ['*'], $expiresAt);

        return $this->respondSuccess(
            'Login success',
            [
                'access_token' => $token->raw_token,
                'token_type'   => 'Bearer',
                'expires_in'   => self::TOKEN_EXPIRY_SECONDS,
                'user'         => [
                    'id'   => (int) $user->id,
                    'name' => $user->username ?? $user->getEmail(),
                    'role' => $this->resolveUserRole($user),
                ],
            ]
        );
    }

    public function logout(): ResponseInterface
    {
        $user  = $this->currentTokenUser();
        $token = $this->extractBearerToken();

        if ($user === null || $token === null) {
            return $this->respondError(
                'Unauthorized',
                ResponseInterface::HTTP_UNAUTHORIZED,
                ['token' => ['Invalid or missing access token.']]
            );
        }

        $user->revokeAccessToken($token);

        return $this->respondSuccess('Logout success', null);
    }

    public function me(): ResponseInterface
    {
        $user = $this->currentTokenUser();

        if ($user === null) {
            return $this->respondError(
                'Unauthorized',
                ResponseInterface::HTTP_UNAUTHORIZED,
                ['token' => ['Invalid or missing access token.']]
            );
        }

        return $this->respondSuccess('Profile fetched', $this->serializeUser($user));
    }
}
