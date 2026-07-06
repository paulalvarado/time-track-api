<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSyncStagesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 25],
            'odooId'       => ['type' => 'INT'],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'sequence'     => ['type' => 'INT', 'default' => 0],
            'odooConfigId' => ['type' => 'VARCHAR', 'constraint' => 25],
            'createdAt'    => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('syncstage');
    }

    public function down()
    {
        $this->forge->dropTable('syncstage');
    }
}
