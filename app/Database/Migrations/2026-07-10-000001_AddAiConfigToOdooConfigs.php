<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAiConfigToOdooConfigs extends Migration
{
    public function up()
    {
        $this->forge->addColumn('OdooConfig', [
            'aiProvider' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => 'gemini'],
            'aiApiKey'   => ['type' => 'TEXT', 'null' => true],
            'aiBaseUrl'  => ['type' => 'TEXT', 'null' => true],
            'aiModel'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('OdooConfig', ['aiProvider', 'aiApiKey', 'aiBaseUrl', 'aiModel']);
    }
}
