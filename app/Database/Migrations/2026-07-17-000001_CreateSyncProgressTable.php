<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSyncProgressTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'VARCHAR', 'constraint' => 50],
            'userId' => ['type' => 'VARCHAR', 'constraint' => 50],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'progress' => ['type' => 'INT', 'default' => 0],
            'log' => ['type' => 'TEXT', 'null' => true],
            'createdAt' => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('syncprogress', true);
    }

    public function down()
    {
        $this->forge->dropTable('syncprogress', true);
    }
}
