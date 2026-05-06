<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFolderTables extends Migration
{
    public function up(): void
    {
        $this->createFoldersTable();
        $this->createAssetFoldersTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_folders', true);
        $this->forge->dropTable('folders', true);
    }

    private function createFoldersTable(): void
    {
        if ($this->db->tableExists('folders')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'parent_id' => [
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('parent_id', false, false, 'idx_folders_parent');
        $this->forge->addKey('type', false, false, 'idx_folders_type');
        $this->forge->addForeignKey('parent_id', 'folders', 'id', 'CASCADE', 'SET NULL', 'fk_folders_parent');
        $this->forge->createTable('folders', true);
    }

    private function createAssetFoldersTable(): void
    {
        if ($this->db->tableExists('asset_folders')) {
            return;
        }

        $this->forge->addField([
            'asset_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'folder_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey(['asset_id', 'folder_id'], true);
        $this->forge->addKey('folder_id', false, false, 'idx_asset_folders_folder');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'CASCADE', 'fk_asset_folders_asset');
        $this->forge->addForeignKey('folder_id', 'folders', 'id', 'CASCADE', 'CASCADE', 'fk_asset_folders_folder');
        $this->forge->createTable('asset_folders', true);
    }
}
