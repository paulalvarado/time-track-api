<?php

namespace App\Models;

use CodeIgniter\Model;

class OdooConfigModel extends Model
{
    protected $table = 'public."OdooConfig"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'userId', 'url', 'dbName', 'username', 'apiKey', 'geminiApiKey', 'aiProvider', 'aiApiKey', 'aiBaseUrl', 'aiModel', 'selectedEmployeeId', 'selectedOdooUserId'];
    protected $useTimestamps = false;

    public function findByUserId(string $userId)
    {
        return $this->where('userId', $userId)->first();
    }

    public function upsert(string $userId, array $data): object
    {
        $existing = $this->findByUserId($userId);
        if ($existing) {
            $this->update($existing->id, $data);
            return $this->find($existing->id);
        }
        $data['id'] = $this->generateCuid();
        $data['userId'] = $userId;
        $this->insert($data);
        return $this->find($data['id']);
    }

    public function updateGeminiKey(string $userId, string $geminiApiKey): void
    {
        $config = $this->findByUserId($userId);
        if ($config) {
            $this->update($config->id, ['geminiApiKey' => $geminiApiKey]);
        }
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
