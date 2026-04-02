<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $assetCategories = [
            [
                'code'       => 'pc',
                'name'       => 'PC',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'laptop',
                'name'       => 'Laptop',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'printer',
                'name'       => 'Printer',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'jetdirect',
                'name'       => 'Jetdirect',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code'       => 'monitor',
                'name'       => 'Monitor',
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->db->table('asset_categories')->ignore(true)->insertBatch($assetCategories);

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
