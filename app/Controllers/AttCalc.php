<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;

/**
 * Attendance ▸ Configurations ▸ Attendance Calculation.
 *
 * ZKBioTime scores punches against the schedule on a trigger (manual here, or a
 * cron) and persists a timecard — reports never recompute. This runs
 * AttendanceCalculator over a date range and writes one att_payloadtimecard row
 * per employee × date, with the rolled-up totals in the JSON payload.
 */
class AttCalc extends BaseController
{
    public function index(): string
    {
        $db = db_connect();

        return view('admin/attcalc/index', [
            'title'    => 'Attendance Calculation',
            'subtitle' => 'Score the punch records against the schedule',
            'active'   => 'att.calc',
            'depts'    => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
            'lastRun'  => $db->query("SELECT to_char(last_date,'YYYY-MM-DD HH24:MI') d FROM att_calculatelastdate ORDER BY id DESC LIMIT 1")->getRow()->d ?? null,
            'stats'    => [
                'timecards' => (int) ($db->query('SELECT count(*) c FROM att_payloadtimecard')->getRow()->c ?? 0),
                'range'     => $db->query("SELECT to_char(min(att_date),'YYYY-MM-DD') a, to_char(max(att_date),'YYYY-MM-DD') b FROM att_payloadtimecard")->getRowArray(),
                'txns'      => (int) ($db->query('SELECT count(*) c FROM iclock_transaction')->getRow()->c ?? 0),
            ],
            'from'     => date('Y-m-01'),
            'to'       => date('Y-m-d'),
        ]);
    }

    public function run()
    {
        $p    = $this->request;
        $from = $p->getPost('from') ?: date('Y-m-01');
        $to   = $p->getPost('to') ?: date('Y-m-d');
        $dept = (int) $p->getPost('dept');
        if (strtotime($from) > strtotime($to)) {
            return redirect()->back()->with('error', 'The start date is after the end date.');
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 92) {
            return redirect()->back()->with('error', 'Calculate at most 3 months at a time.');
        }

        $calc = new AttendanceCalculator();
        $rows = $calc->forRange([], $from, $to, $dept ?: null);

        $db     = db_connect();
        $now    = date('Y-m-d H:i:sP');
        $wrote  = 0;
        $ok = $this->tx(function ($db) use ($rows, $from, $to, $dept, $now, &$wrote) {
            // clear the window we are recomputing
            $del = $db->table('att_payloadtimecard')->where('att_date >=', $from)->where('att_date <=', $to);
            if ($dept) {
                $del->whereIn('emp_id', function ($b) use ($dept) {
                    return $b->select('id')->from('personnel_employee')->where('department_id', $dept);
                });
            }
            $del->delete();

            foreach ($rows as $r) {
                if (($r['status'] ?? '') === 'No Data' || empty($r['first_in'])) {
                    continue;   // nothing to persist for an unpunched / future day
                }
                $emp   = $r['employee'];
                $ttId  = $r['timetable']['id'] ?? null;
                $ts    = strtotime($r['date']);
                $ci    = $r['date'] . ' ' . substr((string) $r['first_in'], 0, 8);
                $co    = $r['date'] . ' ' . substr((string) ($r['last_out'] ?: $r['first_in']), 0, 8);
                $payload = [
                    'duration'  => ['minutes' => (int) $r['worked_minutes'], 'hours' => round($r['worked_minutes'] / 60, 2), 'workday' => (float) $r['work_day_fraction']],
                    'late'      => (int) $r['late_minutes'],
                    'early'     => (int) $r['early_minutes'],
                    'short'     => (int) $r['short_minutes'],
                    'overtime'  => (int) $r['ot_minutes'],
                    'ot_kind'   => $r['ot_kind'] ?? 'normal',
                    'break'     => (int) ($r['break_minutes'] ?? 0),
                    'status'    => $r['status'],
                ];
                $db->table('att_payloadtimecard')->insert([
                    'id'               => $this->uuid(),
                    'att_date'         => $r['date'],
                    'week'             => (int) date('W', $ts),
                    'weekday'          => (int) date('N', $ts) - 1,
                    'date_type'        => $r['is_holiday'] ? 2 : ($r['is_weekend'] ? 1 : 0),
                    'time_table_alias' => $r['timetable']['alias'] ?? '',
                    'check_in'         => $ci,
                    'check_out'        => $co,
                    'work_day'         => (float) $r['work_day_fraction'],
                    'clock_in'         => $ci,
                    'clock_out'        => $co,
                    'lock_down'        => false,
                    'present'          => in_array($r['status'], ['Present', 'Late', 'Early Out', 'Incomplete', 'Overtime'], true) ? 1 : 0,
                    'full_attendance'  => $r['work_day_fraction'] >= 0.999 ? 1 : 0,
                    'payload'          => json_encode($payload),
                    'emp_id'           => (int) $emp['id'],
                    'time_table_id'    => $ttId,
                ]);
                $wrote++;
            }

            $db->table('att_calculatelastdate')->insert(['last_date' => $now]);
        }, 'The calculation could not be saved.');

        if (! $ok) {
            return redirect()->back()->with('error', 'The calculation could not be saved.');
        }
        $this->audit('att.calculate', "{$from} … {$to}", "{$wrote} timecards" . ($dept ? " (dept {$dept})" : ''));

        return redirect()->to(route_to('attcalc'))
            ->with('success', "Calculated {$from} to {$to} — {$wrote} timecard row(s) written.");
    }

    private function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
