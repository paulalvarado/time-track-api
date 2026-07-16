<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdminUser extends Seeder
{
    public function run()
    {
        // Ahora delegamos en RolesAndPermissions que incluye la creación
        // del usuario admin con credenciales desde .env
        $this->call(RolesAndPermissions::class);
    }
}
