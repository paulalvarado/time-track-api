<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmployeeOdooIdToSyncTimesheets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('synctimesheet', [
            'employeeOdooId' => ['type' => 'INT', 'null' => true, 'after' => 'userOdooId'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('synctimesheet', 'employeeOdooId');
    }
}
