<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRolesTables extends Migration
{
    public function up()
    {
        // ── Role ──────────────────────────────────────────────
        $this->forge->addField([
            'id'          => ['type' => 'VARCHAR', 'constraint' => 25],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'isSystem'    => ['type' => 'BOOLEAN', 'default' => false],
            'createdAt'   => ['type' => 'TIMESTAMP', 'null' => true],
            'updatedAt'   => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('Role');

        // ── Permission ──────────────────────────────────────────
        $this->forge->addField([
            'id'          => ['type' => 'VARCHAR', 'constraint' => 25],
            'key'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'group'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'createdAt'   => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('key');
        $this->forge->createTable('Permission');

        // ── RolePermission ──────────────────────────────────────
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 25],
            'roleId'       => ['type' => 'VARCHAR', 'constraint' => 25],
            'permissionId' => ['type' => 'VARCHAR', 'constraint' => 25],
            'createdAt'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['roleId', 'permissionId']);
        $this->forge->addForeignKey('roleId', '"Role"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permissionId', '"Permission"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('RolePermission');

        // ── UserRole ────────────────────────────────────────────
        $this->forge->addField([
            'id'        => ['type' => 'VARCHAR', 'constraint' => 25],
            'userId'    => ['type' => 'VARCHAR', 'constraint' => 25],
            'roleId'    => ['type' => 'VARCHAR', 'constraint' => 25],
            'createdAt' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['userId', 'roleId']);
        $this->forge->addForeignKey('userId', '"User"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('roleId', '"Role"', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('UserRole');
    }

    public function down()
    {
        $this->forge->dropTable('UserRole', true);
        $this->forge->dropTable('RolePermission', true);
        $this->forge->dropTable('Permission', true);
        $this->forge->dropTable('Role', true);
    }
}
