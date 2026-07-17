<?php

namespace App\Models;

use CodeIgniter\Model;

class SyncProgressModel extends Model
{
    protected $table = 'public.syncprogress';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'userId', 'status', 'progress', 'log', 'createdAt', 'updatedAt'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    public function findLatestByUser(string $userId)
    {
        return $this->where('userId', $userId)
            ->orderBy('createdAt', 'DESC')
            ->first();
    }

    public function createProgress(string $userId): object
    {
        $data = [
            'id'       => $this->generateCuid(),
            'userId'   => $userId,
            'status'   => 'pending',
            'progress' => 0,
            'log'      => '',
        ];
        $this->insert($data);
        return $this->find($data['id']);
    }

    public function updateProgress(string $id, array $data): void
    {
        $this->update($id, $data);
    }

    public function appendLog(string $id, string $message): void
    {
        $entry = $this->find($id);
        $existingLog = $entry ? ($entry->log ?? '') : '';
        $newLog = $existingLog . "\n[" . date('H:i:s') . "] " . $message;
        $this->update($id, ['log' => $newLog]);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
