<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table = 'public."Role"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'name', 'description', 'isSystem', 'createdAt', 'updatedAt'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    public function findByName(string $name)
    {
        return $this->where('name', $name)->first();
    }

    public function findAllWithPermissionCount()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"Role" r');
        $builder->select('r.*, (SELECT COUNT(*) FROM "RolePermission" rp WHERE rp."roleId" = r."id") as permissionCount');
        $builder->orderBy('r."isSystem"', 'DESC');
        $builder->orderBy('r."name"', 'ASC');
        return $builder->get()->getResult();
    }

    public function getPermissions(string $roleId): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"RolePermission" rp');
        $builder->select('p.*');
        $builder->join('"Permission" p', 'p."id" = rp."permissionId"');
        $builder->where('rp."roleId"', $roleId);
        $builder->orderBy('p."group"', 'ASC');
        $builder->orderBy('p."name"', 'ASC');
        return $builder->get()->getResult();
    }

    public function hasPermission(string $roleId, string $permissionKey): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"RolePermission" rp');
        $builder->join('"Permission" p', 'p."id" = rp."permissionId"');
        $builder->where('rp."roleId"', $roleId);
        $builder->where('p."key"', $permissionKey);
        return $builder->countAllResults() > 0;
    }

    public function assignPermission(string $roleId, string $permissionId): void
    {
        $db = \Config\Database::connect();
        $builder = $db->table('"RolePermission"');
        $exists = $builder->where('"roleId"', $roleId)
            ->where('"permissionId"', $permissionId)
            ->get()
            ->getRow();
        if (!$exists) {
            $builder->insert([
                'id' => 'c' . bin2hex(random_bytes(12)),
                'roleId' => $roleId,
                'permissionId' => $permissionId,
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function removePermission(string $roleId, string $permissionId): void
    {
        $db = \Config\Database::connect();
        $db->table('"RolePermission"')
            ->where('"roleId"', $roleId)
            ->where('"permissionId"', $permissionId)
            ->delete();
    }

    public function syncPermissions(string $roleId, array $permissionIds): void
    {
        $db = \Config\Database::connect();
        $db->table('"RolePermission"')
            ->where('"roleId"', $roleId)
            ->delete();

        foreach ($permissionIds as $permId) {
            $db->table('"RolePermission"')->insert([
                'id' => 'c' . bin2hex(random_bytes(12)),
                'roleId' => $roleId,
                'permissionId' => $permId,
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
