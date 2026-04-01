<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $assetTypes = [
            [
                'code'       => 'device',
                'name'       => 'Device',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'peripheral',
                'name'       => 'Peripheral',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'network',
                'name'       => 'Network',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->db->table('asset_types')->ignore(true)->insertBatch($assetTypes);

        $typeMap = $this->db->table('asset_types')
            ->select('id, code')
            ->get()
            ->getResultArray();

        $typeIds = [];
        foreach ($typeMap as $type) {
            $typeIds[$type['code']] = (int) $type['id'];
        }

        $assetCategories = [
            [
                'asset_type_id' => $typeIds['device'] ?? null,
                'code'          => 'pc',
                'name'          => 'PC',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'asset_type_id' => $typeIds['device'] ?? null,
                'code'          => 'laptop',
                'name'          => 'Laptop',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'asset_type_id' => $typeIds['peripheral'] ?? null,
                'code'          => 'printer',
                'name'          => 'Printer',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'asset_type_id' => $typeIds['network'] ?? null,
                'code'          => 'jetdirect',
                'name'          => 'Jetdirect',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'asset_type_id' => $typeIds['peripheral'] ?? null,
                'code'          => 'monitor',
                'name'          => 'Monitor',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        $assetCategories = array_values(array_filter(
            $assetCategories,
            static fn (array $category): bool => $category['asset_type_id'] !== null
        ));

        if ($assetCategories !== []) {
            $this->db->table('asset_categories')->ignore(true)->insertBatch($assetCategories);
        }

        $brands = [
            ['code' => 'dell', 'name' => 'Dell', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'hp', 'name' => 'HP', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'lenovo', 'name' => 'Lenovo', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'epson', 'name' => 'Epson', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];

        $this->db->table('brands')->ignore(true)->insertBatch($brands);

        $locations = [
            [
                'code'          => 'store-bandung',
                'name'          => 'Toko Bandung',
                'location_type' => 'store',
                'address'       => null,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'code'          => 'warehouse-central',
                'name'          => 'Gudang Pusat',
                'location_type' => 'warehouse',
                'address'       => null,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'code'          => 'office-jakarta',
                'name'          => 'Kantor Jakarta',
                'location_type' => 'office',
                'address'       => null,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'code'          => 'service-center-main',
                'name'          => 'Service Center Utama',
                'location_type' => 'service_center',
                'address'       => null,
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        $this->db->table('locations')->ignore(true)->insertBatch($locations);
    }
}
