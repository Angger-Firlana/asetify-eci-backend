<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetWorkspaceTables extends Migration
{
    public function up(): void
    {
        $this->createAssetWorkspacesTable();
        $this->createAssetWorkspaceItemsTable();
        $this->createAssetWorkspaceItemPhotosTable();
        $this->createAssetWorkspaceItemScansTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_workspace_item_scans', true);
        $this->forge->dropTable('asset_workspace_item_photos', true);
        $this->forge->dropTable('asset_workspace_items', true);
        $this->forge->dropTable('asset_workspaces', true);
    }

    private function createAssetWorkspacesTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'workspace_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'source_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'target_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'active', 'completed', 'cancelled'],
                'default'    => 'active',
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
            'closed_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addUniqueKey('workspace_code', 'uq_asset_workspaces_code');
        $this->forge->addKey('source_location_id', false, false, 'idx_asset_workspaces_source_location');
        $this->forge->addKey('target_location_id', false, false, 'idx_asset_workspaces_target_location');
        $this->forge->addKey('status', false, false, 'idx_asset_workspaces_status');
        $this->forge->addKey('created_by', false, false, 'idx_asset_workspaces_created_by');
        $this->forge->addForeignKey('source_location_id', 'locations', 'id', 'CASCADE', 'RESTRICT', 'fk_asset_workspaces_source_location');
        $this->forge->addForeignKey('target_location_id', 'locations', 'id', 'CASCADE', 'RESTRICT', 'fk_asset_workspaces_target_location');
        $this->forge->createTable('asset_workspaces', true);
    }

    private function createAssetWorkspaceItemsTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'workspace_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'serial_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'asset_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'match_status' => [
                'type'       => 'ENUM',
                'constraint' => ['matched', 'not_found'],
            ],
            'action_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'asset_updated', 'ready_to_register', 'asset_registered'],
            ],
            'asset_category_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'brand_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
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
                'null'       => true,
            ],
            'target_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'condition_status' => [
                'type'       => 'ENUM',
                'constraint' => ['good', 'bad'],
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'scanned_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'scan_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'last_scan_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'synced_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addUniqueKey(['workspace_id', 'serial_number'], 'uq_asset_workspace_items_workspace_serial');
        $this->forge->addKey('asset_id', false, false, 'idx_asset_workspace_items_asset');
        $this->forge->addKey('match_status', false, false, 'idx_asset_workspace_items_match_status');
        $this->forge->addKey('action_status', false, false, 'idx_asset_workspace_items_action_status');
        $this->forge->addKey('last_scan_at', false, false, 'idx_asset_workspace_items_last_scan_at');
        $this->forge->addForeignKey('workspace_id', 'asset_workspaces', 'id', 'CASCADE', 'CASCADE', 'fk_asset_workspace_items_workspace');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'SET NULL', 'fk_asset_workspace_items_asset');
        $this->forge->addForeignKey('asset_category_id', 'asset_categories', 'id', 'CASCADE', 'SET NULL', 'fk_asset_workspace_items_asset_category');
        $this->forge->addForeignKey('brand_id', 'brands', 'id', 'CASCADE', 'SET NULL', 'fk_asset_workspace_items_brand');
        $this->forge->addForeignKey('source_location_id', 'locations', 'id', 'CASCADE', 'SET NULL', 'fk_asset_workspace_items_source_location');
        $this->forge->addForeignKey('target_location_id', 'locations', 'id', 'CASCADE', 'SET NULL', 'fk_asset_workspace_items_target_location');
        $this->forge->createTable('asset_workspace_items', true);
    }

    private function createAssetWorkspaceItemPhotosTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'workspace_item_id' => [
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
        $this->forge->addKey('workspace_item_id', false, false, 'idx_asset_workspace_item_photos_item');
        $this->forge->addForeignKey('workspace_item_id', 'asset_workspace_items', 'id', 'CASCADE', 'CASCADE', 'fk_asset_workspace_item_photos_item');
        $this->forge->createTable('asset_workspace_item_photos', true);

        $this->db->query(
            'ALTER TABLE `asset_workspace_item_photos` ADD CONSTRAINT `chk_asset_workspace_item_photos_file_size` CHECK (`file_size_bytes` <= 1048576)'
        );
    }

    private function createAssetWorkspaceItemScansTable(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'workspace_item_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'workspace_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'serial_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'asset_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'scanned_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'scan_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'result_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'message' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'device_info' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'app_platform' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('workspace_item_id', false, false, 'idx_asset_workspace_item_scans_item');
        $this->forge->addKey('workspace_id', false, false, 'idx_asset_workspace_item_scans_workspace');
        $this->forge->addKey('serial_number', false, false, 'idx_asset_workspace_item_scans_serial');
        $this->forge->addKey('result_status', false, false, 'idx_asset_workspace_item_scans_result');
        $this->forge->addKey('created_at', false, false, 'idx_asset_workspace_item_scans_created_at');
        $this->forge->addForeignKey('workspace_item_id', 'asset_workspace_items', 'id', 'CASCADE', 'CASCADE', 'fk_asset_workspace_item_scans_item');
        $this->forge->addForeignKey('workspace_id', 'asset_workspaces', 'id', 'CASCADE', 'CASCADE', 'fk_asset_workspace_item_scans_workspace');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'SET NULL', 'fk_asset_workspace_item_scans_asset');
        $this->forge->createTable('asset_workspace_item_scans', true);
    }
}
