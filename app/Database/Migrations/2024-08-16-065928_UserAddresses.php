<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UserAddresses extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'address' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'city' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'state' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'country' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'zip_code' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'latitude' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'longitude' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => '255'
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id');
        $this->forge->createTable('user_addresses');
    }

    public function down()
    {
        $this->forge->dropTable('user_addresses');
    }
}
