<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSyncTasksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'VARCHAR', 'constraint' => 25],
            'odooId'        => ['type' => 'INT'],
            'name'          => ['type' => 'TEXT'],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'stageOdooId'   => ['type' => 'INT', 'null' => true],
            'assigneeIds'   => ['type' => 'TEXT', 'null' => true],
            'priority'      => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '0'],
            'deadline'      => ['type' => 'DATE', 'null' => true],
            'color'         => ['type' => 'INT', 'null' => true],
            'projectOdooId' => ['type' => 'INT'],
            'odooConfigId'  => ['type' => 'VARCHAR', 'constraint' => 25],
            'createdAt'     => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt'     => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('synctask');
    }

    public function down()
    {
        $this->forge->dropTable('synctask');
    }
}
