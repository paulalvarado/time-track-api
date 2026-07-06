<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOdooConfigsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 25],
            'userId'       => ['type' => 'VARCHAR', 'constraint' => 25],
            'url'          => ['type' => 'TEXT'],
            'dbName'       => ['type' => 'TEXT'],
            'username'     => ['type' => 'TEXT'],
            'apiKey'       => ['type' => 'TEXT'],
            'geminiApiKey' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('userId');
        $this->forge->addForeignKey('userId', '"User"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('OdooConfig');
    }

    public function down()
    {
        $this->forge->dropTable('OdooConfig');
    }
}
