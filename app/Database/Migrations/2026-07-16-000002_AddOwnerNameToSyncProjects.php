<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOwnerNameToSyncProjects extends Migration
{
    public function up()
    {
        // Add ownerName column to cache Odoo user names locally
        $this->forge->addColumn('syncproject', [
            'ownerName' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'color',
            ],
        ]);

        // Add index on odooConfigId for faster queries
        $this->db->query('CREATE INDEX IF NOT EXISTS "idx_syncproject_config" ON "public"."syncproject" ("odooConfigId")');

        // Add composite unique index on (odooConfigId, odooId) for upsert lookups
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS "idx_syncproject_config_odoo" ON "public"."syncproject" ("odooConfigId", "odooId")');

        // Add index on updatedAt for ?since= polling queries
        $this->db->query('CREATE INDEX IF NOT EXISTS "idx_syncproject_updated" ON "public"."syncproject" ("updatedAt")');
    }

    public function down()
    {
        $this->forge->dropColumn('syncproject', 'ownerName');
        $this->db->query('DROP INDEX IF EXISTS "idx_syncproject_config"');
        $this->db->query('DROP INDEX IF EXISTS "idx_syncproject_config_odoo"');
        $this->db->query('DROP INDEX IF EXISTS "idx_syncproject_updated"');
    }
}
