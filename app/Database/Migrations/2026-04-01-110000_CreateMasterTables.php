<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMasterTables extends Migration
{
    public function up(): void
    {
        $this->createAssetTypesTable();
        $this->createAssetCategoriesTable();
        $this->createBrandsTable();
        $this->createLocationsTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('locations', true);
        $this->forge->dropTable('brands', true);
        $this->forge->dropTable('asset_categories', true);
        $this->forge->dropTable('asset_types', true);
    }

    private function createAssetTypesTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code', 'uq_asset_types_code');
        $this->forge->createTable('asset_types', true);
    }

    private function createAssetCategoriesTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'asset_type_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('asset_type_id', false, false, 'idx_asset_categories_asset_type');
        $this->forge->addUniqueKey('code', 'uq_asset_categories_code');
        $this->forge->addForeignKey(
            'asset_type_id',
            'asset_types',
            'id',
            'CASCADE',
            'RESTRICT',
            'fk_asset_categories_asset_type'
        );
        $this->forge->createTable('asset_categories', true);
    }

    private function createBrandsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code', 'uq_brands_code');
        $this->forge->createTable('brands', true);
    }

    private function createLocationsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'location_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code', 'uq_locations_code');
        $this->forge->addKey('location_type', false, false, 'idx_locations_type');
        $this->forge->createTable('locations', true);
    }
}
