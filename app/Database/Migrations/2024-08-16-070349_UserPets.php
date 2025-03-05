<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UserPets extends Migration
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
        'name' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'type' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'breed' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'age' => [
            'type' => 'INT',
            'constraint' => 11,
        ],
        'gender' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'image' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'aggressiveness_level' => [
            'type' => 'ENUM',
            'constraint' => ['normal', 'slightly', 'high'],
            'default' => 'normal',
        ],
        'insured' => [
            'type' => 'ENUM',
            'constraint' => ['yes', 'no'],
            'default' => 'no',
        ],
        'vaccinated' => [
            'type' => 'ENUM',
            'constraint' => ['yes', 'no'],
            'default' => 'no',
        ],
        'last_vaccination' => [
            'type' => 'DATE',
            'null' => true,
        ],
        'dewormed_on' => [
            'type' => 'DATE',
            'null' => true,
        ],
        'last_vet_visit' => [
            'type' => 'DATE',
            'null' => true,
        ],
        'visit_purpose' => [
            'type' => 'ENUM',
            'constraint' => ['vaccination', 'deworming', 'checkup'],
            'default' => 'checkup',
        ],
        'vet_name' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'vet_phone' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
        ],
        'vet_address' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
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
    $this->forge->createTable('user_pets');
}




    public function down()
    {
        $this->forge->dropTable('user_pets');
    }
}
