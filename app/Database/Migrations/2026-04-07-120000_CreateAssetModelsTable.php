<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetModelsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('asset_models')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'brand_id' => [
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
                'constraint' => 150,
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
        $this->forge->addKey('brand_id', false, false, 'idx_asset_models_brand');
        $this->forge->addUniqueKey(['brand_id', 'code'], 'uq_asset_models_brand_code');
        $this->forge->addForeignKey('brand_id', 'brands', 'id', 'CASCADE', 'RESTRICT', 'fk_asset_models_brand');
        $this->forge->createTable('asset_models', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_models', true);
    }
}
