<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncStateModel extends Model
{
    protected $table = 'public.syncstate';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'odooConfigId', 'lastSyncAt', 'syncing', 'error', 'odooUid', 'createdAt', 'updatedAt'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    public function findByConfigId(string $odooConfigId)
    {
        return $this->where('odooConfigId', $odooConfigId)->first();
    }

    public function upsert(string $odooConfigId, array $data): void
    {
        $existing = $this->findByConfigId($odooConfigId);
        if ($existing) {
            $this->update($existing->id, $data);
        } else {
            $data['id'] = $odooConfigId;
            $data['odooConfigId'] = $odooConfigId;
            $this->insert($data);
        }
    }
}
