<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLocationDetailsToAssetsAndWorkspaceItems extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('assets') && ! $this->db->fieldExists('current_location_detail', 'assets')) {
            $this->forge->addColumn('assets', [
                'current_location_detail' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
            ]);
        }

        if ($this->db->tableExists('asset_workspace_items') && ! $this->db->fieldExists('current_location_detail', 'asset_workspace_items')) {
            $this->forge->addColumn('asset_workspace_items', [
                'current_location_detail' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('asset_workspace_items') && $this->db->fieldExists('current_location_detail', 'asset_workspace_items')) {
            $this->forge->dropColumn('asset_workspace_items', 'current_location_detail');
        }

        if ($this->db->tableExists('assets') && $this->db->fieldExists('current_location_detail', 'assets')) {
            $this->forge->dropColumn('assets', 'current_location_detail');
        }
    }
}
