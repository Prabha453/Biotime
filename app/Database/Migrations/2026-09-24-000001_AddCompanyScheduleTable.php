<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * att_companyschedule — a company-wide shift assignment, one row per
 * company/shift/date-range. ZKBioTime has no such table (only personal /
 * department / group schedules); this app added a "Company schedule"
 * action that used to fan out into one att_departmentschedule row per
 * department under the company, which left nothing distinct to list or
 * resolve against on its own Company Schedule page. This table makes it a
 * first-class schedule tier instead, modelled column-for-column on
 * att_departmentschedule (just department_id -> company_id). It's a new,
 * additive table only — no existing (ZKBioTime-shared) table is touched.
 */
class AddCompanyScheduleTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('att_companyschedule')) {
            return;
        }
        $this->forge->addField([
            'id'            => ['type' => 'INTEGER', 'auto_increment' => true],
            'create_time'   => ['type' => 'TIMESTAMP', 'null' => true],
            'create_user'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'change_time'   => ['type' => 'TIMESTAMP', 'null' => true],
            'change_user'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'status'        => ['type' => 'SMALLINT', 'default' => 0],
            'start_date'    => ['type' => 'DATE'],
            'end_date'      => ['type' => 'DATE'],
            'company_id'    => ['type' => 'INTEGER'],
            'shift_id'      => ['type' => 'INTEGER'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('company_id');
        $this->forge->addKey('shift_id');
        $this->forge->createTable('att_companyschedule');
    }

    public function down(): void
    {
        $this->forge->dropTable('att_companyschedule', true);
    }
}
