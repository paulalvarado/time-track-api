<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCatalogItemsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'VARCHAR', 'constraint' => 25],
            'catalogId' => ['type' => 'VARCHAR', 'constraint' => 25],
            'key'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'     => ['type' => 'TEXT'],
            'extra'     => ['type' => 'TEXT', 'null' => true],
            'createdAt' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('catalogId', '"Catalog"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('CatalogItem');
    }

    public function down()
    {
        $this->forge->dropTable('CatalogItem');
    }
}
