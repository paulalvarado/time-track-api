<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSyncTimesheetsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 25],
            'odooId'       => ['type' => 'INT'],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'unitAmount'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'date'         => ['type' => 'DATE', 'null' => true],
            'userOdooId'   => ['type' => 'INT', 'null' => true],
            'taskOdooId'   => ['type' => 'INT'],
            'odooConfigId' => ['type' => 'VARCHAR', 'constraint' => 25],
            'createdAt'    => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('synctimesheet');
    }

    public function down()
    {
        $this->forge->dropTable('synctimesheet');
    }
}
