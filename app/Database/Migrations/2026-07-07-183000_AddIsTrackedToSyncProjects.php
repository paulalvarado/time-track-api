<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsTrackedToSyncProjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('syncproject', [
            'isTracked' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'null'       => false,
                'after'      => 'odooConfigId',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('syncproject', 'isTracked');
    }
}
