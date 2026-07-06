<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCatalogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'VARCHAR', 'constraint' => 25],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'odooConfigId'=> ['type' => 'VARCHAR', 'constraint' => 25],
            'lastSyncAt'  => ['type' => 'TIMESTAMP', 'null' => true],
            'createdAt'   => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('odooConfigId', '"OdooConfig"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('Catalog');
    }

    public function down()
    {
        $this->forge->dropTable('Catalog');
    }
}
