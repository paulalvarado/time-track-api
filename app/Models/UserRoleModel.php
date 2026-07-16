<?php

namespace App\Models;

use CodeIgniter\Model;

class UserRoleModel extends Model
{
    protected $table = 'public."UserRole"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'userId', 'roleId', 'createdAt'];
    protected $useTimestamps = false;

    public function findByUser(string $userId): array
    {
        return $this->where('userId', $userId)->findAll();
    }

    public function getRolesForUser(string $userId): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"UserRole" ur');
        $builder->select('r.*');
        $builder->join('"Role" r', 'r."id" = ur."roleId"');
        $builder->where('ur."userId"', $userId);
        return $builder->get()->getResult();
    }

    public function getPermissionsForUser(string $userId): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"UserRole" ur');
        $builder->select('p."key"');
        $builder->join('"RolePermission" rp', 'rp."roleId" = ur."roleId"');
        $builder->join('"Permission" p', 'p."id" = rp."permissionId"');
        $builder->where('ur."userId"', $userId);
        $builder->distinct();
        $rows = $builder->get()->getResult();
        return array_map(fn($r) => $r->key, $rows);
    }

    public function assignRole(string $userId, string $roleId): void
    {
        $existing = $this->where('userId', $userId)->where('roleId', $roleId)->first();
        if (!$existing) {
            $this->insert([
                'id' => $this->generateCuid(),
                'userId' => $userId,
                'roleId' => $roleId,
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function removeRole(string $userId, string $roleId): void
    {
        $this->where('userId', $userId)
            ->where('roleId', $roleId)
            ->delete();
    }

    public function isAdmin(string $userId): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"UserRole" ur');
        $builder->join('"Role" r', 'r."id" = ur."roleId"');
        $builder->where('ur."userId"', $userId);
        $builder->where('r."name"', 'admin');
        return $builder->countAllResults() > 0;
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
