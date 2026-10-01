<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Break Time (att_breaktime) gained two features, mirroring Timetable's own
 * Check-In/Check-Out window pattern and adding rule validity dates:
 *   - start_date / end_date: the break rule's own effective date range
 *     (blank = unbounded).
 *   - break_out_ahead_margin / break_out_above_margin: the Break-Out punch
 *     window, around period_start.
 *   - break_in_ahead_margin: the Break-In punch window's early side (its
 *     late side already existed as end_margin — renamed to
 *     break_in_above_margin for naming symmetry with the other three).
 *
 * These columns were applied directly against the shared waves_new database
 * on 2026-09-15 while building the feature; this migration codifies that
 * change so a fresh clone / another environment can reproduce it, and is
 * idempotent (fieldExists guards) since it may run against a database where
 * the columns already exist.
 */
class AddBreaktimeWindowColumns extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('end_margin', 'att_breaktime')
            && ! $this->db->fieldExists('break_in_above_margin', 'att_breaktime')) {
            $this->db->query('ALTER TABLE att_breaktime RENAME COLUMN end_margin TO break_in_above_margin');
        }

        $fields = [];
        if (! $this->db->fieldExists('start_date', 'att_breaktime')) {
            $fields['start_date'] = ['type' => 'DATE', 'null' => true];
        }
        if (! $this->db->fieldExists('end_date', 'att_breaktime')) {
            $fields['end_date'] = ['type' => 'DATE', 'null' => true];
        }
        if (! $this->db->fieldExists('break_out_ahead_margin', 'att_breaktime')) {
            $fields['break_out_ahead_margin'] = ['type' => 'INTEGER', 'default' => 60];
        }
        if (! $this->db->fieldExists('break_out_above_margin', 'att_breaktime')) {
            $fields['break_out_above_margin'] = ['type' => 'INTEGER', 'default' => 60];
        }
        if (! $this->db->fieldExists('break_in_ahead_margin', 'att_breaktime')) {
            $fields['break_in_ahead_margin'] = ['type' => 'INTEGER', 'default' => 60];
        }
        if ($fields !== []) {
            $this->forge->addColumn('att_breaktime', $fields);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('att_breaktime', ['start_date', 'end_date', 'break_out_ahead_margin', 'break_out_above_margin', 'break_in_ahead_margin']);
        if ($this->db->fieldExists('break_in_above_margin', 'att_breaktime')
            && ! $this->db->fieldExists('end_margin', 'att_breaktime')) {
            $this->db->query('ALTER TABLE att_breaktime RENAME COLUMN break_in_above_margin TO end_margin');
        }
    }
}
