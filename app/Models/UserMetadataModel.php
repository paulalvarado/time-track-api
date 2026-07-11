<?php

namespace App\Models;

use CodeIgniter\Model;

class UserMetadataModel extends Model
{
    protected $table = 'public."UserMetadata"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'userId', 'key', 'value', 'createdAt', 'updatedAt'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    /**
     * Obtiene el valor de una clave de metadata para un usuario.
     * Retorna null si no existe.
     */
    public function getByKey(string $userId, string $key): mixed
    {
        $row = $this->where('userId', $userId)
            ->where('key', $key)
            ->first();

        if (!$row) {
            return null;
        }

        return json_decode($row->value, true);
    }

    /**
     * Obtiene toda la metadata de un usuario como array asociativo [key => value].
     */
    public function getAllForUser(string $userId): array
    {
        $rows = $this->where('userId', $userId)->findAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row->key] = json_decode($row->value, true);
        }
        return $result;
    }

    /**
     * Establece (inserta o actualiza) un valor de metadata.
     */
    public function saveMetadata(string $userId, string $key, mixed $value): void
    {
        $existing = $this->where('userId', $userId)
            ->where('key', $key)
            ->first();

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($existing) {
            $this->update($existing->id, ['value' => $encoded]);
        } else {
            $this->insert([
                'id'     => $this->generateCuid(),
                'userId' => $userId,
                'key'    => $key,
                'value'  => $encoded,
            ]);
        }
    }

    /**
     * Elimina una clave de metadata.
     */
    public function deleteByKey(string $userId, string $key): bool
    {
        $existing = $this->where('userId', $userId)
            ->where('key', $key)
            ->first();

        if (!$existing) {
            return false;
        }

        return $this->delete($existing->id);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
