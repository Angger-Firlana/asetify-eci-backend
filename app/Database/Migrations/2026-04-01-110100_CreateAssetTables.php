<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetTables extends Migration
{
    public function up(): void
    {
        $this->createAssetsTable();
        $this->createAssetPhotosTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_photos', true);
        $this->forge->dropTable('assets', true);
    }

    private function createAssetsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'serial_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'asset_category_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'brand_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'model_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'source_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'current_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'condition_status' => [
                'type'       => 'ENUM',
                'constraint' => ['good', 'bad'],
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'updated_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('serial_number', 'uq_assets_serial_number');
        $this->forge->addKey('source_location_id', false, false, 'idx_assets_source_location');
        $this->forge->addKey('current_location_id', false, false, 'idx_assets_current_location');
        $this->forge->addKey('brand_id', false, false, 'idx_assets_brand');
        $this->forge->addKey('asset_category_id', false, false, 'idx_assets_category');
        $this->forge->addKey('condition_status', false, false, 'idx_assets_condition');
        $this->forge->addKey(['created_at', 'current_location_id'], false, false, 'idx_assets_created_location');
        $this->forge->addForeignKey('asset_category_id', 'asset_categories', 'id', 'CASCADE', 'RESTRICT', 'fk_assets_asset_category');
        $this->forge->addForeignKey('brand_id', 'brands', 'id', 'CASCADE', 'RESTRICT', 'fk_assets_brand');
        $this->forge->addForeignKey('source_location_id', 'locations', 'id', 'CASCADE', 'RESTRICT', 'fk_assets_source_location');
        $this->forge->addForeignKey('current_location_id', 'locations', 'id', 'CASCADE', 'RESTRICT', 'fk_assets_current_location');
        $this->forge->createTable('assets', true);
    }

    private function createAssetPhotosTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'asset_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'disk' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'file_size_bytes' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'width' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'height' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'sha256_checksum' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'is_primary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'uploaded_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('asset_id', false, false, 'idx_asset_photos_asset');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'CASCADE', 'fk_asset_photos_asset');
        $this->forge->createTable('asset_photos', true);

        $this->db->query(
            'ALTER TABLE `asset_photos` ADD CONSTRAINT `chk_asset_photos_file_size` CHECK (`file_size_bytes` <= 1048576)'
        );
    }
}
