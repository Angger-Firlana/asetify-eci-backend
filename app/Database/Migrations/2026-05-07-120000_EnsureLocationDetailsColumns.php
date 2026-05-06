<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Throwable;

class EnsureLocationDetailsColumns extends Migration
{
    public function up(): void
    {
        $this->ensureVarcharNullableColumn('assets', 'current_location_detail', 255);
        $this->ensureVarcharNullableColumn('asset_workspace_items', 'current_location_detail', 255);
    }

    public function down(): void
    {
        // Non-destructive in production: do not drop columns automatically.
    }

    private function ensureVarcharNullableColumn(string $table, string $column, int $length): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        if ($this->db->fieldExists($column, $table)) {
            return;
        }

        try {
            $this->forge->addColumn($table, [
                $column => [
                    'type'       => 'VARCHAR',
                    'constraint' => $length,
                    'null'       => true,
                ],
            ]);
        } catch (Throwable) {
            // Fallback for legacy schemas/Forge quirks.
            $this->db->query(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` VARCHAR(%d) NULL',
                str_replace('`', '``', $table),
                str_replace('`', '``', $column),
                $length
            ));
        }
    }
}

