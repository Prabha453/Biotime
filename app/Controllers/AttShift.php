<?php

namespace App\Controllers;

/**
 * Attendance ▸ Shift — the scheduling chain, three ZKBioTime pages:
 *
 *   Break Time   att_breaktime      a named break window (auto-deduct / punch)
 *   Timetable    att_timeinterval   the shift window + margins + grace + breaks
 *   Shift        att_attshift       a rotation of timetables over a cycle
 *
 * Break Time feeds Timetable (M2M att_timeinterval_break_time); Timetable feeds
 * Shift (att_shiftdetail maps a day_index in the cycle → one timetable).
 */
class AttShift extends BaseController
{
    // ---- enum maps (verbatim from att.migrations) -------------------------

    public const BREAK_CALC   = [0 => 'Auto Deduct', 1 => 'Required Punch'];
    public const WITH_SALARY  = [0 => 'Unpaid', 1 => 'Paid'];
    public const TT_MODE      = [0 => 'Normal', 1 => 'Flexible'];
    public const WORK_TYPE    = [0 => 'Normal work', 1 => 'Day off', 2 => 'Weekend'];
    public const CYCLE_UNIT   = [0 => 'Day', 1 => 'Week', 2 => 'Month'];
    public const OT_ON_OFFDAY = [0 => 'Normal work', 1 => 'Normal OT', 2 => 'Weekend OT'];
    public const OT_POLICY    = [-1 => 'Ignore', 1 => 'Needs approval', 2 => 'Auto-approve'];
    public const UNSCHEDULED  = [0 => 'Ignore', 1 => 'Normal work', 2 => 'Normal OT', 3 => 'Weekend OT', 4 => 'Holiday OT'];

    private const TT_DEFAULTS = [
        'use_mode' => 0, 'in_ahead_margin' => 60, 'in_above_margin' => 60,
        'out_ahead_margin' => 60, 'out_above_margin' => 60, 'in_required' => 1, 'out_required' => 1,
        'early_in' => 0, 'min_early_in' => 60, 'count_early_in_interval' => true,
        'late_out' => 0, 'min_late_out' => 60, 'count_late_out_interval' => true,
        'overtime_lv' => 0, 'overtime_lv1' => 0, 'overtime_lv2' => 0, 'overtime_lv3' => 0,
        'multiple_punch' => 0, 'available_interval_type' => 0, 'available_interval' => 1,
        'func_key' => 0, 'work_type' => 0, 'day_change' => '00:00:00',
        'enable_early_in' => false, 'enable_late_out' => false, 'enable_overtime' => false,
        'enable_max_ot_limit' => false, 'max_ot_limit' => 240, 'overtime_policy' => -1, 'compensate_duration' => 0,
    ];

    // =====================================================================
    //  Break Time
    // =====================================================================

    public function breaks(): string
    {
        $db = db_connect();
        $b  = $db->table('att_breaktime bt')
            ->select("bt.id, bt.alias, bt.period_start, bt.duration,
                      bt.break_out_ahead_margin, bt.break_out_above_margin,
                      bt.break_in_ahead_margin, bt.break_in_above_margin,
                      bt.calc_type, bt.with_salary, bt.minimum_duration, bt.available_interval,
                      bt.multiple_punch, bt.func_key, bt.late_in, bt.start_date, bt.end_date,
                      (bt.period_start + (bt.duration || ' minutes')::interval)::time AS break_in_time,
                      (SELECT count(*) FROM att_timeinterval_break_time l WHERE l.breaktime_id = bt.id) AS used")
            ->orderBy('bt.period_start');

        $fbApplied = $this->filterBuilder($b, [
            'alias'       => ['bt.alias', 'text'],
            'calc_type'   => ['bt.calc_type', 'select'],
            'with_salary' => ['bt.with_salary', 'select'],
        ]);

        [$rows, $pager] = $this->paged($b, 30);

        return view('admin/attshift/breaks', [
            'title'     => 'Break Time',
            'subtitle'  => 'Named break windows used by timetables',
            'active'    => 'att.breaktime',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'calcTypes' => self::BREAK_CALC,
            'salary'    => self::WITH_SALARY,
            'lateIn'    => [0 => 'Ignore', 1 => 'Count as late', 2 => 'Count as early-out', 3 => 'Count as absence'],
        ]);
    }

    public function saveBreak()
    {
        if (! $this->validate([
            'alias'        => 'required|max_length[50]',
            'period_start' => 'required',
            'break_in'     => 'required',
            'start_date'   => 'permit_empty|valid_date',
            'end_date'     => 'permit_empty|valid_date',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'btForm');
        }
        $p  = $this->request;
        $id = (int) $p->getPost('id');

        $startDate = trim((string) $p->getPost('start_date')) ?: null;
        $endDate   = trim((string) $p->getPost('end_date')) ?: null;
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            return redirect()->back()->withInput()->with('error', 'End Date cannot be before Start Date.')->with('reopen', 'btForm');
        }

        // Break-In is a clock time, like the Timetable's Check-Out — duration
        // is derived from Break-Out (period_start) → Break-In the same way
        // Timetable derives duration from Check-In → Check-Out.
        $hm    = static fn ($t) => ($x = array_map('intval', explode(':', substr((string) $t, 0, 5)))) ? $x[0] * 60 + ($x[1] ?? 0) : 0;
        $start = $hm($p->getPost('period_start'));
        $cross = (int) $p->getPost('break_cross_days') === 1 ? 1440 : 0;
        $endMin = $hm($p->getPost('break_in')) + $cross;
        if ($endMin <= $start) {
            $endMin += 1440;   // break-in past midnight
        }
        $dur = $endMin - $start;

        // Break-Out / Break-In Start/End Time are clock times, like the
        // Timetable's Check-In/Check-Out windows — store them as margins
        // around their anchor (period_start for break-out, period_start +
        // duration for break-in), mod 1440 to stay positive across midnight.
        $startClk = $start % 1440;
        $endClk   = ($start + $dur) % 1440;
        $margins  = [
            'break_out_ahead_margin' => ($startClk - $hm($p->getPost('break_out_start')) + 1440) % 1440,
            'break_out_above_margin' => ($hm($p->getPost('break_out_end')) - $startClk + 1440) % 1440,
            'break_in_ahead_margin'  => ($endClk - $hm($p->getPost('break_in_start')) + 1440) % 1440,
            'break_in_above_margin'  => ($hm($p->getPost('break_in_end')) - $endClk + 1440) % 1440,
        ];

        $data = [
            'alias'                   => trim((string) $p->getPost('alias')),
            'period_start'            => $p->getPost('period_start') . ':00',
            'duration'                => $dur,
            'minimum_duration'        => (int) $p->getPost('minimum_duration'),
            'calc_type'               => (int) $p->getPost('calc_type'),
            'with_salary'             => (int) $p->getPost('with_salary'),
            'available_interval'      => (int) ($p->getPost('available_interval') ?: 1),
            'available_interval_type' => 0,
            'multiple_punch'          => (int) (bool) $p->getPost('multiple_punch'),
            'func_key'                => (int) (bool) $p->getPost('func_key'),
            'early_in'                => 0,
            'late_in'                 => (int) $p->getPost('late_in'),
            'profit_rule'             => false, 'min_early_in' => 0,
            'loss_rule'               => false, 'min_late_in' => 0,
            'start_date'              => $startDate,
            'end_date'                => $endDate,
        ] + $margins;

        $db = db_connect();
        $ok = $this->dbGuard(static function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('att_breaktime')->where('id', $id)->update($data);
            }
            $company = (int) ($db->query('SELECT id FROM personnel_company ORDER BY is_default DESC, id LIMIT 1')->getRow()->id ?? 1);

            return $db->table('att_breaktime')->insert($data + ['company_id' => $company]);
        });
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Break time could not be saved.');
        }
        $this->audit($id ? 'att.breaktime.update' : 'att.breaktime.create', $data['alias']);

        return redirect()->to(route_to('attshift.breaks'))->with('success', 'Break time saved.');
    }

    public function deleteBreak(int $id)
    {
        $db = db_connect();
        if ($db->table('att_timeinterval_break_time')->where('breaktime_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This break is used by a timetable — remove it there first.');
        }
        $db->table('att_breaktime')->where('id', $id)->delete();
        $this->audit('att.breaktime.delete', (string) $id);

        return redirect()->to(route_to('attshift.breaks'))->with('success', 'Break time deleted.');
    }

    public function bulkDeleteBreak()
    {
        $ids = $this->ids();
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        $db   = db_connect();
        $free = array_values(array_filter($ids, static fn ($id) => ! $db->table('att_timeinterval_break_time')->where('breaktime_id', $id)->countAllResults()));
        if ($free) {
            $db->table('att_breaktime')->whereIn('id', $free)->delete();
        }
        $this->audit('att.breaktime.bulk_delete', count($free) . ' deleted');

        return redirect()->to(route_to('attshift.breaks'))->with(
            $free ? 'success' : 'error',
            'Deleted ' . count($free) . '.' . (count($ids) - count($free) ? ' Kept ' . (count($ids) - count($free)) . ' in use.' : '')
        );
    }

    // =====================================================================
    //  Timetable
    // =====================================================================

    public function timetables(): string
    {
        $db = db_connect();
        $b  = $db->table('att_timeinterval ti')
            ->select("ti.id, ti.alias, ti.use_mode, ti.in_time, ti.duration, ti.work_time_duration, ti.work_day,
                      ti.allow_late, ti.allow_leave_early, ti.work_type, ti.enable_overtime, ti.enable_max_ot_limit,
                      ti.overtime_policy, ti.ot_pay_code_id, ti.max_ot_limit, ti.overtime_lv1, ti.overtime_lv2, ti.overtime_lv3,
                      ti.in_ahead_margin, ti.in_above_margin, ti.out_ahead_margin, ti.out_above_margin,
                      ti.in_required, ti.out_required, ti.multiple_punch, ti.func_key, ti.day_change,
                      ti.compensate_duration, ti.color_setting, ti.enable_early_in, ti.enable_late_out,
                      (ti.in_time + (ti.duration || ' minutes')::interval)::time AS out_time,
                      c.company_name,
                      (SELECT count(*) FROM att_shiftdetail d WHERE d.time_interval_id = ti.id) AS used")
            ->join('personnel_company c', 'c.id = ti.company_id', 'left')
            ->orderBy('ti.alias');

        $fbApplied = $this->filterBuilder($b, [
            'alias'     => ['ti.alias', 'text'],
            'use_mode'  => ['ti.use_mode', 'select'],
            'work_type' => ['ti.work_type', 'select'],
        ]);
        [$rows, $pager] = $this->paged($b, 30);

        foreach ($rows as &$t) {
            $t['breaks'] = $db->query(
                "SELECT b.id, b.alias, b.period_start, b.duration, b.break_in_above_margin, b.with_salary, b.calc_type
                   FROM att_timeinterval_break_time l JOIN att_breaktime b ON b.id = l.breaktime_id
                  WHERE l.timeinterval_id = ? ORDER BY b.period_start",
                [$t['id']]
            )->getResultArray();
        }
        unset($t);

        return view('admin/attshift/timetables', [
            'title'      => 'Timetable',
            'subtitle'   => 'A work window: in-time, length, margins, grace and breaks',
            'active'     => 'att.timetable',
            'rows'       => $rows,
            'pager'      => $pager,
            'fbApplied'  => $fbApplied,
            'modes'      => self::TT_MODE,
            'workTypes'  => self::WORK_TYPE,
            'otPolicy'   => self::OT_POLICY,
            'unsched'    => self::UNSCHEDULED,
            'breakList'  => $db->query('SELECT id, alias, period_start, duration, break_in_above_margin, with_salary, calc_type FROM att_breaktime ORDER BY period_start')->getResultArray(),
            'payCodes'   => $db->query("SELECT id, code, name FROM att_paycode WHERE code_type = 2 ORDER BY code")->getResultArray(),
        ]);
    }

    public function saveTimetable()
    {
        if (! $this->validate([
            'alias'   => 'required|max_length[50]',
            'in_time' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'ttForm');
        }
        $p        = $this->request;
        $id       = (int) $p->getPost('id');
        $flex     = (int) $p->getPost('use_mode') === 1;
        $enableOt = (bool) $p->getPost('enable_overtime');

        $hm = static fn ($t) => ($x = array_map('intval', explode(':', substr((string) $t, 0, 5)))) ? $x[0] * 60 + ($x[1] ?? 0) : 0;
        $inMin  = $hm($p->getPost('in_time'));
        $cross  = (int) $p->getPost('cross_days') === 1 ? 1440 : 0;

        if ($flex) {
            // flexible day: Work Time is entered directly, no fixed check-out window
            $duration = max(1, (int) ($p->getPost('flex_work_time') ?: 480));
            $margins  = ['in_ahead_margin' => 0, 'in_above_margin' => 0, 'out_ahead_margin' => 0, 'out_above_margin' => 0];
        } else {
            $outMin = $hm($p->getPost('out_time')) + $cross;
            if ($outMin <= $inMin) {
                $outMin += 1440;
            }
            $duration = $outMin - $inMin;
            // punch windows → margins, measured around the check-in / check-out
            // clock times (mod 1440 keeps them positive across midnight).
            $inClk  = $inMin % 1440;
            $outClk = $outMin % 1440;
            $margins = [
                'in_ahead_margin'  => ($inClk  - $hm($p->getPost('in_start'))  + 1440) % 1440,
                'in_above_margin'  => ($hm($p->getPost('in_end'))  - $inClk    + 1440) % 1440,
                'out_ahead_margin' => ($outClk - $hm($p->getPost('out_start')) + 1440) % 1440,
                'out_above_margin' => ($hm($p->getPost('out_end')) - $outClk   + 1440) % 1440,
            ];
        }

        $data = [
            'alias'              => trim((string) $p->getPost('alias')),
            'use_mode'           => $flex ? 1 : 0,
            'in_time'            => substr((string) $p->getPost('in_time'), 0, 5) . ':00',
            'duration'           => $duration,
            'work_time_duration' => $duration,
            'work_day'           => (float) ($p->getPost('work_day') ?: 1),
            'in_required'        => (int) $p->getPost('in_required') === 1 ? 1 : 0,
            'out_required'       => (int) $p->getPost('out_required') === 1 ? 1 : 0,
            'allow_late'         => max(0, (int) $p->getPost('allow_late')),
            'allow_leave_early'  => max(0, (int) $p->getPost('allow_leave_early')),
            'multiple_punch'     => (int) $p->getPost('multiple_punch') === 1 ? 1 : 0,
            'func_key'           => (int) $p->getPost('func_key') === 1 ? 1 : 0,
            'work_type'          => (int) $p->getPost('work_type'),
            'day_change'         => (substr((string) $p->getPost('day_change'), 0, 5) ?: '00:00') . ':00',
            'compensate_duration'=> max(0, (int) $p->getPost('compensate_duration')),
            'color_setting'      => $p->getPost('color_setting') ?: null,
            'enable_early_in'    => (bool) $p->getPost('enable_early_in'),
            'enable_late_out'    => (bool) $p->getPost('enable_late_out'),
            'enable_overtime'    => $enableOt,
            'overtime_lv1'       => (int) $p->getPost('overtime_lv1'),
            'overtime_lv2'       => (int) $p->getPost('overtime_lv2'),
            'overtime_lv3'       => (int) $p->getPost('overtime_lv3'),
            'overtime_policy'    => in_array((int) $p->getPost('overtime_policy'), [-1, 1, 2], true) ? (int) $p->getPost('overtime_policy') : -1,
            'ot_pay_code_id'     => (int) $p->getPost('ot_pay_code_id') ?: null,
            'enable_max_ot_limit'=> (bool) $p->getPost('enable_max_ot_limit'),
            'max_ot_limit'       => (int) ($p->getPost('max_ot_limit') ?: 240),
        ] + $margins;

        $breakIds = array_values(array_filter(array_map('intval', (array) $p->getPost('break_ids'))));

        $db = db_connect();
        $ok = $this->tx(function ($db) use ($id, $data, $breakIds) {
            if ($id) {
                $db->table('att_timeinterval')->where('id', $id)->update($data);
                $ttId = $id;
            } else {
                $company = (int) ($db->query('SELECT id FROM personnel_company ORDER BY is_default DESC, id LIMIT 1')->getRow()->id ?? 1);
                $db->table('att_timeinterval')->insert($data + self::TT_DEFAULTS + ['company_id' => $company]);
                $ttId = (int) $db->insertID();
            }
            $db->table('att_timeinterval_break_time')->where('timeinterval_id', $ttId)->delete();
            foreach ($breakIds as $bid) {
                $db->table('att_timeinterval_break_time')->insert(['timeinterval_id' => $ttId, 'breaktime_id' => $bid]);
            }
        }, 'Timetable could not be saved.');
        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'Timetable could not be saved.');
        }
        $this->audit($id ? 'timetable.update' : 'timetable.create', $data['alias']);

        return redirect()->to(route_to('attshift.timetables'))->with('success', 'Timetable saved.');
    }

    public function deleteTimetable(int $id)
    {
        $db = db_connect();
        if ($db->table('att_shiftdetail')->where('time_interval_id', $id)->countAllResults()
            || $db->table('att_temporaryschedule')->where('time_interval_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This timetable is used by a shift or a temporary schedule.');
        }
        $this->tx(static function ($db) use ($id) {
            $db->table('att_timeinterval_break_time')->where('timeinterval_id', $id)->delete();
            $db->table('att_timeinterval')->where('id', $id)->delete();
        }, 'Could not delete the timetable.');
        $this->audit('timetable.delete', (string) $id);

        return redirect()->to(route_to('attshift.timetables'))->with('success', 'Timetable deleted.');
    }

    // =====================================================================
    //  Shift
    // =====================================================================

    public function shifts(): string
    {
        $db = db_connect();
        $b  = $db->table('att_attshift s')
            ->select("s.id, s.alias, s.cycle_unit, s.shift_cycle, s.work_weekend, s.work_day_off,
                      s.weekend_type, s.day_off_type, s.auto_shift, s.enable_ot_rule, s.ot_rule, c.company_name,
                      (SELECT count(*) FROM att_shiftdetail d WHERE d.shift_id = s.id) AS detail_count,
                      (SELECT count(*) FROM att_attschedule sc WHERE sc.shift_id = s.id) AS emp_count")
            ->join('personnel_company c', 'c.id = s.company_id', 'left')
            ->orderBy('s.alias');

        $fbApplied = $this->filterBuilder($b, [
            'alias'      => ['s.alias', 'text'],
            'cycle_unit' => ['s.cycle_unit', 'select'],
        ]);
        [$rows, $pager] = $this->paged($b, 30);

        foreach ($rows as &$s) {
            $s['details'] = $db->query(
                "SELECT d.day_index, ti.alias, ti.in_time, ti.duration
                   FROM att_shiftdetail d JOIN att_timeinterval ti ON ti.id = d.time_interval_id
                  WHERE d.shift_id = ? ORDER BY d.day_index",
                [$s['id']]
            )->getResultArray();
            // Overtime Rule tiers (att_overtimepolicy, mode 1/2/3 = OT1/OT2/OT3),
            // keyed by the shift's own ot_rule uuid.
            $s['ot_tiers'] = $s['ot_rule']
                ? $db->query(
                    'SELECT mode, pay_code_id, hrs_from, hrs_to FROM att_overtimepolicy WHERE master = ? ORDER BY mode',
                    [$s['ot_rule']]
                )->getResultArray()
                : [];
        }
        unset($s);

        return view('admin/attshift/shifts', [
            'title'      => 'Shift',
            'subtitle'   => 'A rotation of timetables over a repeating cycle',
            'active'     => 'att.shift',
            'rows'       => $rows,
            'pager'      => $pager,
            'fbApplied'  => $fbApplied,
            'cycleUnits' => self::CYCLE_UNIT,
            'otOffday'   => self::OT_ON_OFFDAY,
            'timetables' => $db->query('SELECT id, alias, in_time, duration FROM att_timeinterval ORDER BY alias')->getResultArray(),
            'otPayCodes' => $db->query("SELECT id, name FROM att_paycode WHERE code_type = 2 ORDER BY display_order, id")->getResultArray(),
        ]);
    }

    public function saveShift()
    {
        if (! $this->validate([
            'alias'       => 'required|max_length[50]',
            'shift_cycle' => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'shForm');
        }
        $p     = $this->request;
        $id    = (int) $p->getPost('id');
        $cycle = (int) $p->getPost('cycle_unit');
        $len   = (int) $p->getPost('shift_cycle');
        // slots in the cycle: day → shift_cycle days; week → 7×cycle; month → 31×cycle
        $slots = $cycle === 0 ? $len : ($cycle === 1 ? 7 * $len : 31 * $len);

        $enableOtRule = (bool) $p->getPost('enable_ot_rule');
        $data = [
            'alias'          => trim((string) $p->getPost('alias')),
            'cycle_unit'     => $cycle,
            'shift_cycle'    => $len,
            'work_weekend'   => (bool) $p->getPost('work_weekend'),
            'weekend_type'   => (int) $p->getPost('weekend_type'),
            'work_day_off'   => (bool) $p->getPost('work_day_off'),
            'day_off_type'   => (int) $p->getPost('day_off_type'),
            'auto_shift'     => (int) (bool) $p->getPost('auto_shift'),
            'enable_ot_rule' => $enableOtRule,
            'frequency'      => 2,
        ];

        // day_index => timetable id  (0-based)
        $map = [];
        foreach ((array) $p->getPost('slot') as $idx => $ttId) {
            $ttId = (int) $ttId;
            if ($ttId > 0) {
                $map[(int) $idx] = $ttId;
            }
        }

        // Overtime Rule — three tiers (OT1/OT2/OT3), each a pay code + hour band,
        // stored in att_overtimepolicy keyed by the shift's own ot_rule uuid
        // (ZKBioTime's OvertimePolicy.master). One uuid per shift, reused on edit.
        $otTiers = [];
        for ($lv = 1; $lv <= 3; $lv++) {
            $pc = (int) $p->getPost("t{$lv}_pay_code");
            if ($pc > 0) {
                $otTiers[$lv] = [
                    'pay_code_id' => $pc,
                    'hrs_from'    => (float) $p->getPost("t{$lv}_hrs_from"),
                    'hrs_to'      => (float) $p->getPost("t{$lv}_hrs_to"),
                ];
            }
        }

        $db = db_connect();
        $ok = $this->tx(function ($db) use ($id, $data, $map, $otTiers) {
            if ($id) {
                $otRule = $db->table('att_attshift')->select('ot_rule')->where('id', $id)->get()->getRow()->ot_rule ?? null;
                $db->table('att_attshift')->where('id', $id)->update($data);
                $shiftId = $id;
            } else {
                $company = (int) ($db->query('SELECT id FROM personnel_company ORDER BY is_default DESC, id LIMIT 1')->getRow()->id ?? 1);
                $otRule  = null;
                $db->table('att_attshift')->insert($data + ['company_id' => $company, 'ot_rule' => null]);
                $shiftId = (int) $db->insertID();
            }
            if ($otTiers !== [] && ! $otRule) {
                $otRule = $db->query('SELECT gen_random_uuid()::text u')->getRow()->u;
                $db->table('att_attshift')->where('id', $shiftId)->update(['ot_rule' => $otRule]);
            }
            if ($otRule) {
                $db->table('att_overtimepolicy')->where('master', $otRule)->delete();
                foreach ($otTiers as $lv => $tier) {
                    $db->table('att_overtimepolicy')->insert([
                        'master'      => $otRule,
                        'mode'        => $lv,
                        'hrs_from'    => $tier['hrs_from'],
                        'hrs_to'      => $tier['hrs_to'],
                        'pay_code_id' => $tier['pay_code_id'],
                        'status'      => 0,
                    ]);
                }
            }
            $db->table('att_shiftdetail')->where('shift_id', $shiftId)->delete();
            foreach ($map as $idx => $ttId) {
                $tt = $db->table('att_timeinterval')->select("in_time, (in_time + (duration || ' minutes')::interval)::time out_time")
                    ->where('id', $ttId)->get()->getRowArray();
                $db->table('att_shiftdetail')->insert([
                    'day_index'        => $idx,
                    'time_interval_id' => $ttId,
                    'shift_id'         => $shiftId,
                    'in_time'          => $tt['in_time'] ?? '09:00:00',
                    'out_time'         => $tt['out_time'] ?? '18:00:00',
                ]);
            }
        }, 'Shift could not be saved.');
        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'Shift could not be saved — check the timetables you picked.');
        }
        $this->audit($id ? 'shift.update' : 'shift.create', $data['alias']);

        return redirect()->to(route_to('attshift.shifts'))->with('success', 'Shift saved.');
    }

    public function deleteShift(int $id)
    {
        $db = db_connect();
        if ($db->table('att_attschedule')->where('shift_id', $id)->countAllResults()
            || $db->table('att_departmentschedule')->where('shift_id', $id)->countAllResults()
            || $db->table('att_groupschedule')->where('shift_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This shift is assigned in a schedule — remove those assignments first.');
        }
        $this->tx(static function ($db) use ($id) {
            $db->table('att_shiftdetail')->where('shift_id', $id)->delete();
            $db->table('att_attshift')->where('id', $id)->delete();
        }, 'Could not delete the shift.');
        $this->audit('shift.delete', (string) $id);

        return redirect()->to(route_to('attshift.shifts'))->with('success', 'Shift deleted.');
    }

    public function bulkDeleteTimetable()
    {
        return $this->bulkGuarded('att_timeinterval', $this->ids(), [
            'att_shiftdetail' => 'time_interval_id', 'att_temporaryschedule' => 'time_interval_id',
        ], 'attshift.timetables', static function ($db, $ids) {
            $db->table('att_timeinterval_break_time')->whereIn('timeinterval_id', $ids)->delete();
        });
    }

    public function bulkDeleteShift()
    {
        return $this->bulkGuarded('att_attshift', $this->ids(), [
            'att_attschedule' => 'shift_id', 'att_departmentschedule' => 'shift_id', 'att_groupschedule' => 'shift_id',
        ], 'attshift.shifts', static function ($db, $ids) {
            $db->table('att_shiftdetail')->whereIn('shift_id', $ids)->delete();
        });
    }

    /**
     * Delete the rows in $ids that aren't referenced by any (table => fk) probe;
     * run $cleanup for the survivors first; report kept vs deleted.
     */
    private function bulkGuarded(string $table, array $ids, array $probes, string $route, ?\Closure $cleanup = null)
    {
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        $db   = db_connect();
        $free = array_values(array_filter($ids, static function ($id) use ($db, $probes) {
            foreach ($probes as $t => $fk) {
                if ($db->table($t)->where($fk, $id)->countAllResults()) {
                    return false;
                }
            }

            return true;
        }));
        if ($free) {
            $this->tx(static function ($db) use ($table, $free, $cleanup) {
                if ($cleanup) {
                    $cleanup($db, $free);
                }
                $db->table($table)->whereIn('id', $free)->delete();
            }, 'Could not delete the selected rows.');
        }
        $this->audit($table . '.bulk_delete', count($free) . ' deleted');

        return redirect()->to(route_to($route))->with(
            $free ? 'success' : 'error',
            'Deleted ' . count($free) . '.' . (count($ids) - count($free) ? ' Kept ' . (count($ids) - count($free)) . ' in use.' : '')
        );
    }

    // --------------------------------------------------------------- helper

    private function ids(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
    }
}
