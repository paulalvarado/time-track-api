<?php

namespace App\Models;

use CodeIgniter\Model;

class RolePermissionModel extends Model
{
    protected $table = 'public."RolePermission"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'roleId', 'permissionId', 'createdAt'];
    protected $useTimestamps = false;

    public function findByRole(string $roleId): array
    {
        return $this->where('roleId', $roleId)->findAll();
    }

    public function findByPermission(string $permissionId): array
    {
        return $this->where('permissionId', $permissionId)->findAll();
    }

    public function userHasPermission(string $userId, string $permissionKey): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"UserRole" ur');
        $builder->select('1');
        $builder->join('"RolePermission" rp', 'rp."roleId" = ur."roleId"');
        $builder->join('"Permission" p', 'p."id" = rp."permissionId"');
        $builder->where('ur."userId"', $userId);
        $builder->where('p."key"', $permissionKey);
        $builder->limit(1);
        return $builder->get()->getRow() !== null;
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
