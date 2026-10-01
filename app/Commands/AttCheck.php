<?php

namespace App\Commands;

use App\Libraries\AttendanceCalculator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Dev helper: dump the AttendanceCalculator output for one employee over a
 * date range so calculator changes can be eyeballed against real punches.
 *
 *   php spark att:check <empId> <from> <to>
 */
class AttCheck extends BaseCommand
{
    protected $group       = 'Attendance';
    protected $name        = 'att:check';
    protected $description  = 'Print calculated attendance for an employee over a range.';
    protected $usage        = 'att:check [empId] [from] [to]';

    public function run(array $params)
    {
        $db   = db_connect();
        $calc = new AttendanceCalculator($db);

        $empId = (int) ($params[0] ?? 0);
        if ($empId === 0) {
            $top = $db->query("SELECT emp_id FROM iclock_transaction
                                WHERE emp_id IS NOT NULL AND punch_time >= DATE '2026-01-01'
                             GROUP BY emp_id ORDER BY count(*) DESC LIMIT 1")->getRow();
            $empId = (int) ($top->emp_id ?? 1);
        }
        $from = $params[1] ?? '2026-07-01';
        $to   = $params[2] ?? '2026-07-13';

        $emp = $calc->employee($empId);
        CLI::write('Employee: #' . ($emp['emp_code'] ?? '?') . ' ' . ($emp['name'] ?? ''), 'green');

        foreach ($calc->forEmployee($empId, $from, $to) as $r) {
            CLI::write(sprintf(
                '%s %-3s sched %s-%s  in %-5s out %-5s worked %-6s late %-3d early %-3d short %-4d ot %-4d  %s',
                $r['date'], $r['weekday'],
                $r['scheduled']['in'] ?? '--', $r['scheduled']['out'] ?? '--',
                $r['first_in'] ?? '-', $r['last_out'] ?? '-',
                AttendanceCalculator::hhmm($r['worked_minutes']),
                $r['late_minutes'], $r['early_minutes'], $r['short_minutes'], $r['ot_minutes'], $r['status']
            ));
            if ($r['punches']) {
                CLI::write('    ' . implode('  ', array_map(
                    static fn ($p) => $p['state'] . '@' . $p['time'], $r['punches']
                )), 'dark_gray');
            }
        }

        CLI::newLine();
        CLI::write('Monthly totals ' . substr($from, 0, 7) . ':', 'yellow');
        foreach ($calc->monthly($empId, (int) substr($from, 0, 4), (int) substr($from, 5, 2))['totals'] as $k => $v) {
            CLI::write('  ' . str_pad($k, 16) . $v);
        }
    }
}
