<?php

namespace App\Models;

use CodeIgniter\Model;

class CatalogItemModel extends Model
{
    protected $table = 'public."CatalogItem"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'catalogId', 'key', 'value', 'extra', 'createdAt'];
    protected $useTimestamps = false;

    public function findByCatalogId(string $catalogId)
    {
        return $this->where('catalogId', $catalogId)->findAll();
    }

    public function findByKey(string $catalogId, string $key)
    {
        return $this->where('catalogId', $catalogId)->where('key', $key)->first();
    }

    public function upsert(string $catalogId, string $key, array $data): void
    {
        $existing = $this->findByKey($catalogId, $key);
        if ($existing) {
            $this->update($existing->id, $data);
        } else {
            $data['id'] = $this->generateCuid();
            $data['catalogId'] = $catalogId;
            $data['key'] = $key;
            $this->insert($data);
        }
    }

    public function deleteByCatalogExcept(string $catalogId, array $exceptKeys): void
    {
        if (!empty($exceptKeys)) {
            $this->where('catalogId', $catalogId)
                ->whereNotIn('key', $exceptKeys)
                ->delete();
        } else {
            $this->where('catalogId', $catalogId)->delete();
        }
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
