<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table = 'public."Permission"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'key', 'name', 'group', 'description', 'createdAt'];
    protected $useTimestamps = false;

    public function findByKey(string $key)
    {
        return $this->where('key', $key)->first();
    }

    public function findAllGrouped(): array
    {
        $permissions = $this->orderBy('"group"', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($permissions as $p) {
            $group = $p->group ?: 'General';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $p;
        }
        return $grouped;
    }

    public function upsert(string $key, array $data): object
    {
        $existing = $this->findByKey($key);
        if ($existing) {
            $this->update($existing->id, $data);
            return $this->find($existing->id);
        }
        $data['id'] = $this->generateCuid();
        $data['key'] = $key;
        $this->insert($data);
        return $this->find($data['id']);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
