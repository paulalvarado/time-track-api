<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserMetadataTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'        => ['type' => 'VARCHAR', 'constraint' => 25],
            'userId'    => ['type' => 'VARCHAR', 'constraint' => 25],
            'key'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'value'     => ['type' => 'JSONB', 'null' => true],
            'createdAt' => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['userId', 'key']);
        $this->forge->addForeignKey('userId', 'User', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('UserMetadata');
    }

    public function down()
    {
        $this->forge->dropTable('UserMetadata');
    }
}
