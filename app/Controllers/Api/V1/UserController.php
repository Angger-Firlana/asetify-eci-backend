<?php

namespace App\Controllers\Api\V1;

use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

class UserController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $access = $this->requireUserManagementAccess();
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?: 20)));
        $offset  = ($page - 1) * $perPage;

        $search = trim((string) $this->request->getGet('search'));
        $group  = trim((string) $this->request->getGet('group'));
        $group  = $group !== '' ? strtolower($group) : null;
        $active = $this->parseBooleanQueryValue($this->request->getGet('active'));

        $emailIds = $search !== '' ? $this->findUserIdsByEmailSearch($search) : [];

        $countModel = new UserModel();
        $this->applyListFilters($countModel, $search, $emailIds, $group, $active);
        $total = $countModel->countAllResults();

        $userModel = new UserModel();
        $this->applyListFilters($userModel, $search, $emailIds, $group, $active);
        $users = $userModel
            ->withIdentities()
            ->withGroups()
            ->orderBy('id', 'DESC')
            ->findAll($perPage, $offset);

        return $this->respondPaginated(
            'Users fetched',
            array_map([$this, 'serializeManagedUser'], $users),
            [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            ResponseInterface::HTTP_OK
        );
    }

    public function show(int $userId): ResponseInterface
    {
        $access = $this->requireUserManagementAccess();
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        /** @var User|null $user */
        $user = (new UserModel())->withIdentities()->withGroups()->find($userId);
        if ($user === null) {
            return $this->respondError('User not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->respondSuccess('User fetched', $this->serializeManagedUser($user));
    }

    public function create(): ResponseInterface
    {
        $access = $this->requireUserManagementAccess();
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($payload)) {
            $payload = [];
        }

        $rules = [
            'username' => 'permit_empty|string|max_length[30]',
            'email'    => 'required|string|max_length[254]|valid_email',
            'password' => 'required|string|min_length[8]',
            'group'    => 'required|in_list[scanner,supervisor,admin]',
            'active'   => 'permit_empty|in_list[0,1,true,false]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $user = (new UserService())->createUser($payload);

            return $this->respondSuccess(
                'User created successfully',
                $this->serializeManagedUser($user),
                ResponseInterface::HTTP_CREATED
            );
        } catch (RuntimeException $e) {
            return $this->respondError($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable) {
            return $this->respondError('Failed to create user', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(int $userId): ResponseInterface
    {
        $access = $this->requireUserManagementAccess();
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (! is_array($payload)) {
            $payload = [];
        }

        if ($payload !== [] && ! $this->validateData($payload, [
            'username' => 'permit_empty|string|max_length[30]',
            'email'    => 'permit_empty|string|max_length[254]|valid_email',
            'password' => 'permit_empty|string|min_length[8]',
            'group'    => 'permit_empty|in_list[scanner,supervisor,admin]',
            'active'   => 'permit_empty|in_list[0,1,true,false]',
        ])) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors()
            );
        }

        try {
            $user = (new UserService())->updateUser($userId, $payload);

            return $this->respondSuccess('User updated successfully', $this->serializeManagedUser($user));
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        } catch (\Throwable) {
            return $this->respondError('Failed to update user', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete(int $userId): ResponseInterface
    {
        $access = $this->requireUserManagementAccess();
        if ($access instanceof ResponseInterface) {
            return $access;
        }

        $current = $this->currentTokenUser();
        if ($current !== null && (int) $current->id === $userId) {
            return $this->respondError(
                'Validation failed',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                ['user_id' => ['Cannot delete your own account.']]
            );
        }

        try {
            (new UserService())->deleteUser($userId);

            return $this->respondSuccess('User deleted successfully', null);
        } catch (RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'not found')
                ? ResponseInterface::HTTP_NOT_FOUND
                : ResponseInterface::HTTP_UNPROCESSABLE_ENTITY;

            return $this->respondError($e->getMessage(), $status);
        } catch (\Throwable) {
            return $this->respondError('Failed to delete user', ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function requireUserManagementAccess(): User|ResponseInterface
    {
        $user = $this->currentTokenUser();
        if ($user === null) {
            return $this->respondError('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if (! $user->inGroup('admin') && ! $user->can('users.manage')) {
            return $this->respondError('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        return $user;
    }

    private function applyListFilters(UserModel $model, string $search, array $emailUserIds, ?string $group, ?bool $active): void
    {
        if ($active !== null) {
            $model->where('active', $active ? 1 : 0);
        }

        if ($group !== null) {
            // Filter by group membership using Shield table.
            $tables = config('Auth')->tables;
            $model->join($tables['groups_users'] . ' groups_users', 'groups_users.user_id = ' . $tables['users'] . '.id', 'left');
            $model->where('groups_users.group', $group);
        }

        if ($search === '') {
            return;
        }

        $model->groupStart()
            ->like('username', $search);

        if ($emailUserIds !== []) {
            $model->orWhereIn('id', $emailUserIds);
        }

        $model->groupEnd();
    }

    private function findUserIdsByEmailSearch(string $search): array
    {
        $tables = config('Auth')->tables;

        $rows = (new UserIdentityModel())
            ->builder()
            ->select('user_id')
            ->where('type', 'email_password')
            ->like('secret', $search)
            ->get()
            ->getResultArray();

        $ids = array_map(static fn (array $row): int => (int) $row['user_id'], $rows);

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    private function serializeManagedUser(User $user): array
    {
        return [
            'id'         => (int) $user->id,
            'username'   => $user->username,
            'email'      => $user->getEmail(),
            'active'     => (bool) ($user->active ?? false),
            'status'     => $user->status ?? null,
            'role'       => $this->resolveUserRole($user),
            'groups'     => $user->getGroups() ?? [],
            'created_at' => $user->created_at ?? null,
            'updated_at' => $user->updated_at ?? null,
        ];
    }

    private function parseBooleanQueryValue(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
