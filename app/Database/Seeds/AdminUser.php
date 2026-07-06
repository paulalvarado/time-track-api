<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdminUser extends Seeder
{
    public function run()
    {
        $this->db->table('"User"')->insert([
            'id'        => 'c' . bin2hex(random_bytes(12)),
            'email'     => 'admin@timetrack.app',
            'name'      => 'Admin',
            'password'  => password_hash('admin123', PASSWORD_BCRYPT),
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
        ]);

        echo "  Admin user created: admin@timetrack.app / admin123\n";
    }
}
