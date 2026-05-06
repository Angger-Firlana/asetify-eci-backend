<?php

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'scanner';

    public array $groups = [
        'scanner' => [
            'title'       => 'Scanner',
            'description' => 'Petugas scan aset di lapangan.',
        ],
        'supervisor' => [
            'title'       => 'Supervisor',
            'description' => 'Supervisor operasional aset.',
        ],
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Administrator aplikasi aset.',
        ],
    ];

    public array $permissions = [
        'assets.*'                => 'Can access all asset actions',
        'assets.read'             => 'Can view asset lists and details',
        'assets.create'           => 'Can create new assets',
        'assets.update'           => 'Can update non-sensitive asset fields',
        'assets.update-sensitive' => 'Can update serial number and other sensitive asset fields',
        'assets.photos.manage'    => 'Can add or delete photos on existing assets',
        'masters.*'               => 'Can access all master data actions',
        'masters.read'            => 'Can read master data',
        'masters.create-inline'   => 'Can create inline master data options for searchable fields',
        'masters.manage'          => 'Can manage master data',
        'scan-logs.read'          => 'Can view scan logs',
        'audit-logs.read'         => 'Can view audit logs',
        'dashboard.read'          => 'Can view dashboard summary',
        'users.manage'            => 'Can manage application users',
        'folders.*'               => 'Can access all folder actions',
        'folders.read'            => 'Can view folders and folder assets',
        'folders.manage'          => 'Can create, update, and delete folders',
        'folders.assign'          => 'Can assign assets into folders',
    ];

    public array $matrix = [
        'scanner' => [
            'assets.read',
            'assets.create',
            'assets.update',
            'masters.read',
            'masters.create-inline',
            'scan-logs.read',
            'dashboard.read',
            'folders.read',
            'folders.assign',
        ],
        'supervisor' => [
            'assets.read',
            'assets.create',
            'assets.update',
            'assets.update-sensitive',
            'assets.photos.manage',
            'masters.read',
            'masters.create-inline',
            'scan-logs.read',
            'audit-logs.read',
            'dashboard.read',
            'folders.read',
            'folders.manage',
            'folders.assign',
        ],
        'admin' => [
            'assets.*',
            'assets.read',
            'assets.create',
            'assets.update',
            'assets.update-sensitive',
            'assets.photos.manage',
            'masters.*',
            'masters.read',
            'masters.create-inline',
            'masters.manage',
            'scan-logs.read',
            'audit-logs.read',
            'dashboard.read',
            'users.manage',
            'folders.*',
            'folders.read',
            'folders.manage',
            'folders.assign',
        ],
    ];
}
