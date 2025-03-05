<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WalkingServiceBookings extends Migration
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
            'walking_service_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'package_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'service_frequency' => [
                'type' => 'ENUM',
                'constraint' => ['once a day', 'twice a day', 'thrice a day']
            ],
            'walk_duration' => [
                'type' => 'ENUM',
                'constraint' => ['30 min walk', '60 min walk']
            ],
            'service_days' => [
                'type' => 'ENUM',
                'constraint' => ['weekdays', 'all days']
            ],
            'service_start_date' => [
                'type' => 'DATE'
            ],
            'preferable_time' => [
                'type' => 'JSON'
            ],
            'addons' => [
                'type' => 'JSON'
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
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
        $this->forge->addForeignKey('walking_service_id', 'services', 'id');
        $this->forge->addForeignKey('package_id', 'packages', 'id');
        $this->forge->createTable('walking_service_bookings');
    }

    public function down()
    {
        $this->forge->dropTable('walking_service_bookings');
    }
}
