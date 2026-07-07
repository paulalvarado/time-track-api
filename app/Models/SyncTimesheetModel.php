<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncTimesheetModel extends Model
{
    protected $table = 'public.synctimesheet';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id', 'odooId', 'name', 'unitAmount', 'date',
        'userOdooId', 'employeeOdooId', 'taskOdooId', 'odooConfigId', 'createdAt', 'updatedAt',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    public function findByOdooId(int $odooId, string $odooConfigId)
    {
        return $this->where('odooId', $odooId)->where('odooConfigId', $odooConfigId)->first();
    }

    public function findByTask(int $taskOdooId, string $odooConfigId)
    {
        return $this->where('taskOdooId', $taskOdooId)
            ->where('odooConfigId', $odooConfigId)
            ->orderBy('date', 'DESC')
            ->findAll();
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

    public function sumByUser(int $userOdooId, string $odooConfigId): float
    {
        $db = $this->db->table($this->table);
        $db->select('COALESCE(SUM("unitAmount"), 0) as total');
        $db->where('"userOdooId"', $userOdooId);
        $db->where('"odooConfigId"', $odooConfigId);
        $result = $db->get()->getRow();
        return (float) ($result->total ?? 0);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
