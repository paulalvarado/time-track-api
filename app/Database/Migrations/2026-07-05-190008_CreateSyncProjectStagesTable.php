<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSyncProjectStagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 25],
            'stageOdooId'  => ['type' => 'INT'],
            'projectOdooId'=> ['type' => 'INT'],
            'odooConfigId' => ['type' => 'VARCHAR', 'constraint' => 25],
            'createdAt'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('syncprojectstage');
    }

    public function down()
    {
        $this->forge->dropTable('syncprojectstage');
    }
}
