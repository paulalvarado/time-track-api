<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncProjectStageModel extends Model
{
    protected $table = 'public.syncprojectstage';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'stageOdooId', 'projectOdooId', 'odooConfigId', 'createdAt'];
    protected $useTimestamps = false;

    public function findByProject(int $projectOdooId, string $odooConfigId)
    {
        return $this->where('projectOdooId', $projectOdooId)
            ->where('odooConfigId', $odooConfigId)
            ->findAll();
    }

    public function deleteByConfig(string $odooConfigId): void
    {
        $this->where('odooConfigId', $odooConfigId)->delete();
    }

    public function insertPair(int $stageOdooId, int $projectOdooId, string $odooConfigId): void
    {
        $existing = $this->where('stageOdooId', $stageOdooId)
            ->where('projectOdooId', $projectOdooId)
            ->where('odooConfigId', $odooConfigId)
            ->first();
        if (!$existing) {
            $this->insert([
                'id' => $this->generateCuid(),
                'stageOdooId' => $stageOdooId,
                'projectOdooId' => $projectOdooId,
                'odooConfigId' => $odooConfigId,
            ]);
        }
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
