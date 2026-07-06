<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'public."User"';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['id', 'email', 'name', 'password', 'createdAt', 'updatedAt'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'createdAt';
    protected $updatedField = 'updatedAt';

    public function findByEmail(string $email)
    {
        return $this->where('email', $email)->first();
    }

    public function findById(string $id)
    {
        return $this->find($id);
    }

    public function createUser(array $data): object
    {
        $data['id'] = $data['id'] ?? $this->generateCuid();
        $this->insert($data);
        return $this->find($data['id']);
    }

    public function updatePassword(string $id, string $password): void
    {
        $this->update($id, ['password' => $password]);
    }

    public function updateName(string $id, string $name): void
    {
        $this->update($id, ['name' => $name]);
    }

    private function generateCuid(): string
    {
        return 'c' . bin2hex(random_bytes(12));
    }
}
