<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use RuntimeException;

class UserService
{
    public function createUser(array $payload): User
    {
        $userModel = new UserModel();

        $username = isset($payload['username']) ? trim((string) $payload['username']) : null;
        $email    = isset($payload['email']) ? trim((string) $payload['email']) : null;
        $password = isset($payload['password']) ? (string) $payload['password'] : null;
        $group    = isset($payload['group']) ? strtolower(trim((string) $payload['group'])) : null;
        $active   = array_key_exists('active', $payload) ? (int) (bool) $payload['active'] : 1;

        if ($email === null || $email === '' || $password === null || $password === '' || $group === null || $group === '') {
            throw new RuntimeException('Invalid user payload.');
        }

        $user = new User([
            'username' => $username !== '' ? $username : null,
            'email'    => $email,
            'password' => $password,
            'active'   => $active,
        ]);

        if (! $userModel->save($user)) {
            $errors = $userModel->errors();
            throw new RuntimeException($errors !== [] ? json_encode($errors, JSON_THROW_ON_ERROR) : 'Failed to create user.');
        }

        $createdId = (int) $userModel->getInsertID();

        /** @var User|null $created */
        $created = $userModel->withGroups()->withIdentities()->find($createdId);
        if ($created === null) {
            throw new RuntimeException('Failed to load created user.');
        }

        $created->syncGroups($group);

        return $created;
    }

    public function updateUser(int $userId, array $payload): User
    {
        $userModel = new UserModel();

        /** @var User|null $user */
        $user = $userModel->withGroups()->withIdentities()->find($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        if (array_key_exists('username', $payload)) {
            $username = trim((string) $payload['username']);
            $user->username = $username !== '' ? $username : null;
        }

        if (array_key_exists('email', $payload)) {
            $email = trim((string) $payload['email']);
            if ($email === '') {
                throw new RuntimeException('Email is required.');
            }
            $user->setEmail($email);
        }

        if (array_key_exists('password', $payload)) {
            $password = (string) $payload['password'];
            if ($password !== '') {
                $user->setPassword($password);
            }
        }

        if (array_key_exists('active', $payload)) {
            $user->active = (int) (bool) $payload['active'];
        }

        if (! $userModel->save($user)) {
            $errors = $userModel->errors();
            throw new RuntimeException($errors !== [] ? json_encode($errors, JSON_THROW_ON_ERROR) : 'Failed to update user.');
        }

        if (array_key_exists('group', $payload)) {
            $group = strtolower(trim((string) $payload['group']));
            if ($group === '') {
                throw new RuntimeException('group is invalid.');
            }

            $user->syncGroups($group);
        }

        /** @var User|null $reloaded */
        $reloaded = $userModel->withGroups()->withIdentities()->find($userId);
        if ($reloaded === null) {
            throw new RuntimeException('Failed to load updated user.');
        }

        return $reloaded;
    }

    public function deleteUser(int $userId): void
    {
        $userModel = new UserModel();

        /** @var User|null $user */
        $user = $userModel->find($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }

        if (! $userModel->delete($userId)) {
            $errors = $userModel->errors();
            throw new RuntimeException($errors !== [] ? json_encode($errors, JSON_THROW_ON_ERROR) : 'Failed to delete user.');
        }
    }
}

