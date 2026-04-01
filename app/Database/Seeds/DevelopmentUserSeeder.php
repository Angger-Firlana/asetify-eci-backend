<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'username' => 'admin',
                'email'    => 'admin@asetify.test',
                'password' => 'Password123!',
                'group'    => 'admin',
            ],
            [
                'username' => 'supervisor01',
                'email'    => 'supervisor@asetify.test',
                'password' => 'Password123!',
                'group'    => 'supervisor',
            ],
            [
                'username' => 'scanner01',
                'email'    => 'scanner@asetify.test',
                'password' => 'Password123!',
                'group'    => 'scanner',
            ],
        ];

        foreach ($users as $item) {
            /** @var UserModel $userModel */
            $userModel = new UserModel();

            $existing = $userModel->findByCredentials(['email' => $item['email']]);

            if ($existing === null) {
                /** @var UserModel $lookupByUsername */
                $lookupByUsername = new UserModel();
                $existing = $lookupByUsername->findByCredentials(['username' => $item['username']]);
            }

            if ($existing !== null) {
                $existing->email    = $item['email'];
                $existing->password = $item['password'];

                if (! $userModel->save($existing)) {
                    throw new \RuntimeException(json_encode($userModel->errors(), JSON_THROW_ON_ERROR));
                }

                if (! $existing->inGroup($item['group'])) {
                    $existing->addGroup($item['group']);
                }

                continue;
            }

            $user = new User([
                'username' => $item['username'],
                'email'    => $item['email'],
                'password' => $item['password'],
                'active'   => 1,
            ]);

            if (! $userModel->save($user)) {
                throw new \RuntimeException(json_encode($userModel->errors(), JSON_THROW_ON_ERROR));
            }

            /** @var UserModel $savedUserLookup */
            $savedUserLookup = new UserModel();
            $savedUser = $savedUserLookup->findByCredentials(['username' => $item['username']]);
            if ($savedUser !== null && ! $savedUser->inGroup($item['group'])) {
                $savedUser->addGroup($item['group']);
            }
        }
    }
}
