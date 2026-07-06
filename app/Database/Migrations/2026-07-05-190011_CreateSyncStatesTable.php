<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSyncStatesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 25],
            'odooConfigId' => ['type' => 'VARCHAR', 'constraint' => 25],
            'lastSyncAt'   => ['type' => 'TIMESTAMP', 'null' => true],
            'syncing'      => ['type' => 'BOOLEAN', 'default' => false],
            'error'        => ['type' => 'TEXT', 'null' => true],
            'odooUid'      => ['type' => 'INT', 'null' => true],
            'createdAt'    => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('syncstate');
    }

    public function down()
    {
        $this->forge->dropTable('syncstate');
    }
}
