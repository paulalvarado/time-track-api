<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCompositeIndexToSyncProjects extends Migration
{
    public function up()
    {
        // Índice compuesto para la consulta de polling:
        //   SELECT * FROM syncproject WHERE "odooConfigId" = ? AND "updatedAt" > ?
        // Esto es más eficiente que dos índices separados porque cubre ambas columnas
        $this->db->query('CREATE INDEX IF NOT EXISTS "idx_syncproject_config_updated" ON "public"."syncproject" ("odooConfigId", "updatedAt")');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS "idx_syncproject_config_updated"');
    }
}
