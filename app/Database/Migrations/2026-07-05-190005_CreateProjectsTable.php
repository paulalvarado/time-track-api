<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProjectsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 25],
            'odooId'     => ['type' => 'INT'],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'userId'     => ['type' => 'VARCHAR', 'constraint' => 25],
            'odooUserId' => ['type' => 'INT', 'null' => true],
            'color'      => ['type' => 'INT', 'null' => true],
            'createdAt'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('userId', '"User"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('Project');
    }

    public function down()
    {
        $this->forge->dropTable('Project');
    }
}
