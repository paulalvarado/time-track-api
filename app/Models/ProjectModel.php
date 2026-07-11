<?php

namespace App\Models;

use CodeIgniter\Model;

class ProjectModel extends Model
{
    protected $table = 'public."Project"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'odooId', 'name', 'userId', 'odooUserId', 'color', 'createdAt'];
    protected $useTimestamps = false;

    public function countByUserId(string $userId): int
    {
        return $this->where('userId', $userId)->countAllResults();
    }

    public function countByOdooUserId(string $userId, int $odooUid): int
    {
        return $this->where('userId', $userId)
            ->where('odooUserId', $odooUid)
            ->countAllResults();
    }

    public function findByUserId(string $userId)
    {
        return $this->where('userId', $userId)
            ->orderBy('"odooUserId" ASC NULLS LAST', '', false)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function upsertMany(string $userId, array $projects): array
    {
        $odooIds = array_column($projects, 'odooId');

        // Delete projects no longer in Odoo
        if (!empty($odooIds)) {
            $this->where('userId', $userId)
                ->whereNotIn('odooId', $odooIds)
                ->delete();
        } else {
            $this->where('userId', $userId)->delete();
        }

        foreach ($projects as $project) {
            $existing = $this->where('odooId', $project['odooId'])
                ->where('userId', $userId)
                ->first();

            $data = [
                'name' => $project['name'],
                'odooUserId' => $project['odooUserId'] ?? null,
                'color' => $project['color'] ?? null,
            ];

            if ($existing) {
                $this->update($existing->id, $data);
            } else {
                $data['id'] = $this->generateCuid();
                $data['odooId'] = $project['odooId'];
                $data['userId'] = $userId;
                $this->insert($data);
            }
        }

        return $this->findByUserId($userId);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
