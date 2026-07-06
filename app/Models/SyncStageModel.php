<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncStageModel extends Model
{
    protected $table = 'public.syncstage';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'odooId', 'name', 'sequence', 'odooConfigId', 'createdAt', 'updatedAt'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    public function findByOdooId(int $odooId, string $odooConfigId)
    {
        return $this->where('odooId', $odooId)->where('odooConfigId', $odooConfigId)->first();
    }

    public function upsert(int $odooId, string $odooConfigId, array $data): void
    {
        $existing = $this->findByOdooId($odooId, $odooConfigId);
        if ($existing) {
            $this->update($existing->id, $data);
        } else {
            $data['id'] = $this->generateCuid();
            $data['odooId'] = $odooId;
            $data['odooConfigId'] = $odooConfigId;
            $this->insert($data);
        }
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
