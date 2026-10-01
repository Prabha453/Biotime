<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ZKBioTime's EmployeePayrollForm carries national_num / payroll_num /
 * internal_emp_num. The imported schema only had personnel_id (= payroll_num),
 * so add the other two identity fields.
 *
 * These columns were applied directly against the shared waves_new database
 * while building the feature; this migration codifies that change so a
 * fresh clone / another environment can reproduce it, and is idempotent
 * (fieldExists guards) since it may run against a database where the
 * columns already exist.
 */
class AddPayrollIdentityNumbers extends Migration
{
    public function up(): void
    {
        $fields = [];
        if (! $this->db->fieldExists('national_num', 'payroll_emppayrollprofile')) {
            $fields['national_num'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true];
        }
        if (! $this->db->fieldExists('internal_emp_num', 'payroll_emppayrollprofile')) {
            $fields['internal_emp_num'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true];
        }
        if ($fields !== []) {
            $this->forge->addColumn('payroll_emppayrollprofile', $fields);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('payroll_emppayrollprofile', ['national_num', 'internal_emp_num']);
    }
}
