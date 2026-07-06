<?php

namespace App\Models;

use CodeIgniter\Model;

class CatalogModel extends Model
{
    protected $table = 'public."Catalog"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'name', 'odooConfigId', 'lastSyncAt', 'createdAt'];
    protected $useTimestamps = false;

    public function findByName(string $name, string $odooConfigId)
    {
        return $this->where('name', $name)->where('odooConfigId', $odooConfigId)->first();
    }

    public function upsert(string $name, string $odooConfigId, array $data): object
    {
        $existing = $this->findByName($name, $odooConfigId);
        if ($existing) {
            $this->update($existing->id, $data);
            return $this->find($existing->id);
        }
        $data['id'] = $this->generateCuid();
        $data['name'] = $name;
        $data['odooConfigId'] = $odooConfigId;
        $this->insert($data);
        return $this->find($data['id']);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
