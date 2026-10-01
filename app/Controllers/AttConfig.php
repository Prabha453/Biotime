<?php

namespace App\Controllers;

use App\Libraries\AttPolicy;

/**
 * Attendance ▸ Rule ▸ Global Rule  +  Configurations ▸ Report Setting.
 *
 * The Global Rule tab is the editable front of the single `att_attpolicy`
 * row that AttPolicy / AttendanceCalculator read on every calculation —
 * weekend days, day-change boundary, the flexible-day hour cap, punch
 * pairing, the overtime master switches and the late / early / absence
 * thresholds. The Report Setting tab edits `att_attreportsetting`.
 *
 * Pay Code and Break Time used to live here; they now have their own
 * screens under Setup and Shift, so this controller only owns the policy.
 */
class AttConfig extends BaseController
{
    private const TABS = [
        'global'    => 'Global Rule',
        'reporting' => 'Report Setting',
    ];

    /** att_attpolicy weekday numbering: 0 = Sunday … 6 = Saturday, 7 = none. */
    private const DOW = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    private const PARING = [0 => 'By time — first punch in, last punch out', 1 => 'By punch state — use the in / out / break keys'];

    private const OT_POLICY = [-1 => 'Ignore overtime', 1 => 'Overtime needs approval', 2 => 'Auto-approve worked overtime'];

    /** att_attpolicy.miss_in / miss_out: what a missing required punch on that side does to the day. */
    private const MISS_IN_MODE  = [0 => 'Incomplete', 1 => 'Late (penalty minutes)', 2 => 'Absent'];
    private const MISS_OUT_MODE = [0 => 'Incomplete', 1 => 'Early Leave (penalty minutes)', 2 => 'Absent'];

    public function index(string $tab = 'global'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'global';
        }
        $db = db_connect();
        $d  = [
            'title'    => 'Attendance Rule',
            'subtitle' => self::TABS[$tab],
            'active'   => $tab === 'reporting' ? 'att.reportsetting' : 'attconfig',
            'tab'      => $tab,
            'tabs'     => self::TABS,
        ];

        if ($tab === 'global') {
            $d['pol']      = $db->table('att_attpolicy')->orderBy('id')->get(1)->getRowArray() ?: [];
            $d['payCodes'] = $db->query("SELECT id, code, name FROM att_paycode WHERE code_type = 2 ORDER BY display_order, id")->getResultArray();
            $d['dow']      = self::DOW;
            $d['paring']   = self::PARING;
            $d['otPolicy'] = self::OT_POLICY;
            $d['missInModes']  = self::MISS_IN_MODE;
            $d['missOutModes'] = self::MISS_OUT_MODE;

            // weekends in the current month, per the resolved weekend-day
            // rule (not just the raw weekend1/weekend2 columns — same
            // fallback/kv-override resolution the calculator itself uses).
            $weekendDays = \App\Libraries\AttPolicy::load($db)->weekendDays;
            $count = 0;
            $daysInMonth = (int) date('t');
            for ($day = 1; $day <= $daysInMonth; $day++) {
                if (in_array((int) date('w', mktime(0, 0, 0, (int) date('n'), $day, (int) date('Y'))), $weekendDays, true)) {
                    $count++;
                }
            }
            $d['weekendCount'] = $count;
            $d['currentMonthLabel'] = date('F Y');
        } else {
            $d['setting'] = $db->table('att_attreportsetting')->orderBy('id')->get(1)->getRowArray() ?: [];
        }

        return view('admin/attconfig/index', $d);
    }

    // ----------------------------------------------------------- global rule

    public function saveGlobal()
    {
        $p  = $this->request;
        $db = db_connect();

        $wk = static fn (string $k, int $def) => ($v = $p->getPost($k)) === null || $v === '' ? $def : max(0, min(7, (int) $v));
        $n  = static fn (string $k, int $def = 0) => ($v = $p->getPost($k)) === null || $v === '' ? $def : max(0, (int) $v);

        $data = [
            'weekend1'           => $wk('weekend1', 7),
            'weekend2'           => $wk('weekend2', 7),
            'start_of_week'      => max(0, min(6, (int) $p->getPost('start_of_week'))),
            'day_change'         => $p->getPost('day_change') ?: '00:00:00',
            'max_hrs'            => max(1, min(24, (float) ($p->getPost('max_hrs') ?: 20))),
            'paring_rule'        => (int) $p->getPost('paring_rule') === 1 ? 1 : 0,
            'punch_period'       => $n('punch_period', 1),
            'use_ot'             => $p->getPost('use_ot') ? 1 : 0,
            'overtime_policy'    => in_array((int) $p->getPost('overtime_policy'), [-1, 1, 2], true) ? (int) $p->getPost('overtime_policy') : 2,
            'daily_ot'           => (bool) $p->getPost('daily_ot'),
            'weekly_ot'          => (bool) $p->getPost('weekly_ot'),
            'weekend_ot'         => (bool) $p->getPost('weekend_ot'),
            'holiday_ot'         => (bool) $p->getPost('holiday_ot'),
            'enable_compensatory' => (bool) $p->getPost('enable_compensatory'),
            'ot_pay_code_id'     => (int) $p->getPost('ot_pay_code_id') ?: null,
            'miss_in'            => in_array((int) $p->getPost('miss_in'), [0, 1, 2], true) ? (int) $p->getPost('miss_in') : 1,
            'miss_out'           => in_array((int) $p->getPost('miss_out'), [0, 1, 2], true) ? (int) $p->getPost('miss_out') : 1,
            'late_in_hrs'        => $n('late_in_hrs'),
            'early_out_hrs'      => $n('early_out_hrs'),
            'late_in2absence'    => $n('late_in2absence', 100),
            'early_out2absence'  => $n('early_out2absence', 100),
            'max_late_in'        => min(32767, $n('max_late_in')),
            'max_early_out'      => min(32767, $n('max_early_out')),
            'max_absent'         => min(32767, $n('max_absent')),
            'require_capture'     => (bool) $p->getPost('require_capture'),
            'require_work_code'   => (bool) $p->getPost('require_work_code'),
            'require_punch_state' => (bool) $p->getPost('require_punch_state'),
            'change_time'        => date('Y-m-d H:i:sP'),
            'change_user'        => session()->get('userName'),
        ];

        $row = $db->table('att_attpolicy')->orderBy('id')->get(1)->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('att_attpolicy')->where('id', $row['id'])->update($data)
            : $db->table('att_attpolicy')->insert($data + ['status' => 0, 'create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'The global rule could not be saved.');
        }

        AttPolicy::flush();
        $this->audit('att.rule.update', 'global rule');

        return redirect()->to(route_to('attconfig'))->with('success', 'Global rule saved. New calculations use it immediately.');
    }

    // ------------------------------------------------------ report settings

    public function saveReporting()
    {
        $p    = $this->request;
        $data = [
            'filter_by_hire_date' => (bool) $p->getPost('filter_by_hire_date'),
            'resign_emp'          => (int) $p->getPost('resign_emp'),
            'short_date'          => (int) $p->getPost('short_date'),
            'short_time'          => (int) $p->getPost('short_time'),
            'auto_calculate'      => (bool) $p->getPost('auto_calculate'),
            'calculate_time'      => $p->getPost('calculate_time') ?: null,
        ];
        $db  = db_connect();
        $row = $db->table('att_attreportsetting')->orderBy('id')->get(1)->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('att_attreportsetting')->where('id', $row['id'])->update($data)
            : $db->table('att_attreportsetting')->insert($data));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Report settings could not be saved.');
        }
        $this->audit('att.reportsetting.update', 'report setting');

        return redirect()->to(route_to('attconfig.tab', 'reporting'))->with('success', 'Report setting saved.');
    }
}
