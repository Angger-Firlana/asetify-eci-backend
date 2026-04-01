<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

abstract class BaseApiController extends BaseController
{
    use ResponseTrait;

    protected function respondSuccess(
        string $message,
        mixed $data = [],
        int $status = ResponseInterface::HTTP_OK,
        array $meta = []
    ): ResponseInterface {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return $this->respond($payload, $status);
    }

    protected function respondError(
        string $message,
        int $status = ResponseInterface::HTTP_BAD_REQUEST,
        array $errors = []
    ): ResponseInterface {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return $this->respond($payload, $status);
    }

    protected function currentTokenUser(): ?User
    {
        /** @var User|null $user */
        $user = auth('tokens')->user();

        return $user;
    }

    protected function resolveUserRole(User $user): ?string
    {
        foreach (['admin', 'supervisor', 'scanner'] as $role) {
            if ($user->inGroup($role)) {
                return $role;
            }
        }

        $groups = $user->getGroups() ?? [];

        return $groups[0] ?? null;
    }

    protected function serializeUser(User $user): array
    {
        return [
            'id'       => (int) $user->id,
            'name'     => $user->username ?? $user->getEmail(),
            'username' => $user->username,
            'email'    => $user->getEmail(),
            'role'     => $this->resolveUserRole($user),
            'groups'   => $user->getGroups() ?? [],
        ];
    }

    protected function extractBearerToken(): ?string
    {
        $header = trim($this->request->getHeaderLine('Authorization'));

        if ($header === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    protected function normalizeSerialNumber(?string $serialNumber): string
    {
        return strtoupper(trim((string) $serialNumber));
    }
}
