<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class AssetAuthorizationService
{
    private const SCANNER_ALLOWED_FIELDS = [
        'asset_type_id',
        'asset_category_id',
        'brand_id',
        'model_name',
        'source_location_id',
        'current_location_id',
        'condition_status',
        'notes',
    ];

    private const SUPERVISOR_ALLOWED_FIELDS = [
        'serial_number',
        'asset_type_id',
        'asset_category_id',
        'brand_id',
        'model_name',
        'source_location_id',
        'current_location_id',
        'condition_status',
        'notes',
    ];

    public function canViewAsset(User $user): bool
    {
        return $user->inGroup('scanner', 'supervisor', 'admin');
    }

    public function canUpdateAsset(User $user): bool
    {
        return $user->inGroup('scanner', 'supervisor', 'admin');
    }

    public function canEditSerialNumber(User $user): bool
    {
        return $user->inGroup('supervisor', 'admin');
    }

    public function canManageExistingPhotos(User $user): bool
    {
        return $user->inGroup('supervisor', 'admin');
    }

    public function assertAllowedFields(User $user, array $payload): void
    {
        $allowedFields = $user->inGroup('scanner')
            ? self::SCANNER_ALLOWED_FIELDS
            : self::SUPERVISOR_ALLOWED_FIELDS;

        $forbiddenFields = array_diff(array_keys($payload), $allowedFields);

        if ($forbiddenFields !== []) {
            throw new RuntimeException(
                'Forbidden fields: ' . implode(', ', $forbiddenFields)
            );
        }
    }
}
