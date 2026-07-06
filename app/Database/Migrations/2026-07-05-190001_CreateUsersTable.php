<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'VARCHAR', 'constraint' => 25],
            'email'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'password'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'createdAt' => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('User');
    }

    public function down()
    {
        $this->forge->dropTable('User');
    }
}
