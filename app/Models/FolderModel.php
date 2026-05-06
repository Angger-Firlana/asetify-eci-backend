<?php

namespace App\Models;

use CodeIgniter\Model;

class FolderModel extends Model
{
    protected $table          = 'folders';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields  = [
        'name',
        'type',
        'parent_id',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;

    public function queryWithRelations(): self
    {
        return $this->select([
                'folders.id',
                'folders.name',
                'folders.type',
                'folders.parent_id',
                'parent.name AS parent_name',
                'parent.type AS parent_type',
                'folders.created_at',
                'folders.updated_at',
            ])
            ->join('folders parent', 'parent.id = folders.parent_id', 'left');
    }

    public function findWithRelations(int $folderId): ?array
    {
        return $this->queryWithRelations()
            ->where('folders.id', $folderId)
            ->first();
    }
}
