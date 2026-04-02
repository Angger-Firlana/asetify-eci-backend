<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveAssetTypesFromSchema extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('assets') && $this->db->fieldExists('asset_type_id', 'assets')) {
            $this->forge->dropForeignKey('assets', 'fk_assets_asset_type');
            $this->forge->dropColumn('assets', 'asset_type_id');
        }

        if ($this->db->tableExists('asset_categories') && $this->db->fieldExists('asset_type_id', 'asset_categories')) {
            $this->forge->dropForeignKey('asset_categories', 'fk_asset_categories_asset_type');
            $this->forge->dropColumn('asset_categories', 'asset_type_id');
        }

        if ($this->db->tableExists('asset_types')) {
            $this->forge->dropTable('asset_types', true);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('asset_types')) {
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

        if ($this->db->tableExists('asset_categories') && ! $this->db->fieldExists('asset_type_id', 'asset_categories')) {
            $this->forge->addColumn('asset_categories', [
                'asset_type_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'id',
                ],
            ]);
        }

        if ($this->db->tableExists('assets') && ! $this->db->fieldExists('asset_type_id', 'assets')) {
            $this->forge->addColumn('assets', [
                'asset_type_id' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'serial_number',
                ],
            ]);
        }
    }
}
