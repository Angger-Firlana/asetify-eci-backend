<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetPhotoUploadsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'upload_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'asset_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
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
            'uploaded_by' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'consumed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('upload_id', 'uq_asset_photo_uploads_upload_id');
        $this->forge->addKey('asset_id', false, false, 'idx_asset_photo_uploads_asset');
        $this->forge->addKey('uploaded_by', false, false, 'idx_asset_photo_uploads_uploaded_by');
        $this->forge->addKey('expires_at', false, false, 'idx_asset_photo_uploads_expires_at');
        $this->forge->addForeignKey('asset_id', 'assets', 'id', 'CASCADE', 'SET NULL', 'fk_asset_photo_uploads_asset');
        $this->forge->createTable('asset_photo_uploads', true);

        $this->db->query(
            'ALTER TABLE `asset_photo_uploads` ADD CONSTRAINT `chk_asset_photo_uploads_file_size` CHECK (`file_size_bytes` <= 1048576)'
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('asset_photo_uploads', true);
    }
}
