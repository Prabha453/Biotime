<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Login is now against ZKBioTime's own auth_user table. Add the two
 * lockout columns the app needs (nullable / defaulted so ZKBioTime is
 * unaffected).
 */
class AddAuthUserLockout extends Migration
{
    public function up(): void
    {
        $fields = [];
        if (! $this->db->fieldExists('failed_attempts', 'auth_user')) {
            $fields['failed_attempts'] = ['type' => 'INTEGER', 'default' => 0];
        }
        if (! $this->db->fieldExists('locked_until', 'auth_user')) {
            $fields['locked_until'] = ['type' => 'TIMESTAMP', 'null' => true];
        }
        if ($fields !== []) {
            $this->forge->addColumn('auth_user', $fields);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('auth_user', ['failed_attempts', 'locked_until']);
    }
}
