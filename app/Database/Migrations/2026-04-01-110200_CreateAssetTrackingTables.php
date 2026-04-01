<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetTrackingTables extends Migration
{
    public function up(): void
    {
        $this->createAssetScanLogsTable();
        $this->createAssetMovementsTable();
        $this->createAssetAuditLogsTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_audit_logs', true);
        $this->forge->dropTable('asset_movements', true);
        $this->forge->dropTable('asset_scan_logs', true);
    }

    private function createAssetScanLogsTable(): void
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
        $this->forge->addKey('serial_number', false, false, 'idx_scan_logs_serial');
        $this->forge->addKey('scanned_by', false, false, 'idx_scan_logs_user');
        $this->forge->addKey('result_status', false, false, 'idx_scan_logs_result');
        $this->forge->addKey('created_at', false, false, 'idx_scan_logs_created_at');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'SET NULL', 'fk_scan_logs_asset');
        $this->forge->createTable('asset_scan_logs', true);
    }

    private function createAssetMovementsTable(): void
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
            'from_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'to_location_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'moved_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('asset_id', false, false, 'idx_asset_movements_asset');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'CASCADE', 'fk_asset_movements_asset');
        $this->forge->addForeignKey('from_location_id', 'locations', 'id', 'CASCADE', 'SET NULL', 'fk_asset_movements_from_location');
        $this->forge->addForeignKey('to_location_id', 'locations', 'id', 'CASCADE', 'RESTRICT', 'fk_asset_movements_to_location');
        $this->forge->createTable('asset_movements', true);
    }

    private function createAssetAuditLogsTable(): void
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
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'changed_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'change_source' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'field_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'old_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'new_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'change_note' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('asset_id', false, false, 'idx_asset_audit_logs_asset');
        $this->forge->addKey('changed_by', false, false, 'idx_asset_audit_logs_changed_by');
        $this->forge->addKey('action', false, false, 'idx_asset_audit_logs_action');
        $this->forge->addKey('created_at', false, false, 'idx_asset_audit_logs_created_at');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'CASCADE', 'fk_asset_audit_logs_asset');
        $this->forge->createTable('asset_audit_logs', true);
    }
}
