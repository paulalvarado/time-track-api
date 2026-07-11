<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSelectedEmployeeToOdooConfig extends Migration
{
    public function up()
    {
        $this->forge->addColumn('OdooConfig', [
            'selectedEmployeeId' => ['type' => 'INT', 'null' => true],
            'selectedOdooUserId' => ['type' => 'INT', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('OdooConfig', ['selectedEmployeeId', 'selectedOdooUserId']);
    }
}
