<?php

namespace App\Controllers;

class Shifts extends BaseController
{
    /** Sensible defaults so a timetable can be created with just a few fields. */
    private const TT_DEFAULTS = [
        'use_mode' => 0, 'in_ahead_margin' => 60, 'in_above_margin' => 60,
        'out_ahead_margin' => 120, 'out_above_margin' => 60, 'in_required' => 1, 'out_required' => 1,
        'early_in' => 0, 'min_early_in' => 0, 'count_early_in_interval' => false,
        'late_out' => 0, 'min_late_out' => 0, 'count_late_out_interval' => false,
        'overtime_lv' => 0, 'overtime_lv1' => 0, 'overtime_lv2' => 0, 'overtime_lv3' => 0,
        'multiple_punch' => 0, 'available_interval_type' => 0, 'available_interval' => 1,
        'func_key' => 0, 'work_type' => 0, 'day_change' => '00:00:00',
        'enable_early_in' => false, 'enable_late_out' => false, 'enable_overtime' => true,
        'enable_max_ot_limit' => false, 'max_ot_limit' => 300, 'overtime_policy' => -1, 'compensate_duration' => 0,
    ];

    public function saveTimetable()
    {
        $rules = [
            'alias'    => 'required|max_length[50]',
            'in_time'  => 'required',
            'duration' => 'required|is_natural_no_zero',
        ];
        if (! $this->validate($rules)) {
            $id = (int) $this->request->getPost('id');
            return redirect()->back()->with('errors', $this->validator->getErrors())
                ->with('reopen', $id ? 'ttEdit' . $id : 'ttNew');
        }
        $p        = $this->request;
        $duration = (int) $p->getPost('duration');
        $data = [
            'alias'             => $p->getPost('alias'),
            'in_time'           => $p->getPost('in_time') . ':00',
            'duration'          => $duration,
            'work_time_duration'=> $duration,
            'work_day'          => (float) ($p->getPost('work_day') ?: 1),
            'allow_late'        => (int) $p->getPost('allow_late'),
            'allow_leave_early' => (int) $p->getPost('allow_early'),
        ];

        $db = db_connect();
        $id = (int) $p->getPost('id');
        $ok = $this->dbGuard(function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('att_timeinterval')->where('id', $id)->update($data);
            }
            $company = (int) ($db->query('SELECT id FROM personnel_company ORDER BY id LIMIT 1')->getRow()->id ?? 1);

            return $db->table('att_timeinterval')->insert(array_merge(self::TT_DEFAULTS, $data, ['company_id' => $company]));
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Timetable could not be saved.');
        }
        $this->audit($id ? 'timetable.update' : 'timetable.create', $data['alias']);

        return redirect()->to(route_to('shifts'))->with('success', $id ? 'Timetable updated.' : 'Timetable created.');
    }

    public function saveShift()
    {
        if (! $this->validate(['alias' => 'required|max_length[50]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'shNew');
        }
        $p    = $this->request;
        $ttId = (int) $p->getPost('timetable_id');
        $ok   = $this->tx(function ($db) use ($p, $ttId) {
            $company = (int) ($db->query('SELECT id FROM personnel_company ORDER BY id LIMIT 1')->getRow()->id ?? 1);
            $db->table('att_attshift')->insert([
                'alias'        => $p->getPost('alias'),
                'cycle_unit'   => 1, 'shift_cycle' => 1,
                'work_weekend' => (bool) $p->getPost('work_weekend'),
                'weekend_type' => 0,
                'work_day_off' => (bool) $p->getPost('work_day_off'),
                'day_off_type' => 0, 'auto_shift' => 1, 'enable_ot_rule' => false, 'frequency' => 2,
                'company_id'   => $company,
            ]);
            $shiftId = (int) $db->insertID();
            if ($ttId) {
                for ($i = 0; $i <= 6; $i++) {
                    $db->table('att_shiftdetail')->insert([
                        'in_time' => '00:00:00', 'out_time' => '00:00:00',
                        'day_index' => $i, 'shift_id' => $shiftId, 'time_interval_id' => $ttId,
                    ]);
                }
            }
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Shift could not be created — check the timetable you picked.');
        }
        $this->audit('shift.create', (string) $p->getPost('alias'));

        return redirect()->to(route_to('shifts'))->with('success', 'Shift created.');
    }

    public function index(): string
    {
        $db = db_connect();

        $timetables = $db->query(
            "SELECT ti.id, ti.alias, ti.in_time, ti.duration, ti.work_day,
                    ti.allow_late, ti.allow_leave_early, ti.in_ahead_margin, ti.in_above_margin,
                    ti.out_ahead_margin, ti.out_above_margin, ti.work_type,
                    (ti.in_time + (ti.duration || ' minutes')::interval)::time AS out_time,
                    c.company_name
               FROM att_timeinterval ti
          LEFT JOIN personnel_company c ON c.id = ti.company_id
           ORDER BY ti.alias"
        )->getResultArray();

        foreach ($timetables as &$t) {
            $t['breaks'] = $db->query(
                "SELECT b.alias, b.period_start, b.duration, b.with_salary
                   FROM att_timeinterval_break_time l JOIN att_breaktime b ON b.id = l.breaktime_id
                  WHERE l.timeinterval_id = ? ORDER BY b.period_start",
                [$t['id']]
            )->getResultArray();
        }
        unset($t);

        $shifts = $db->query(
            "SELECT s.id, s.alias, s.cycle_unit, s.shift_cycle, s.work_weekend, s.work_day_off, s.auto_shift,
                    c.company_name,
                    (SELECT count(*) FROM att_shiftdetail d WHERE d.shift_id = s.id) AS detail_count,
                    (SELECT count(*) FROM att_attschedule sc WHERE sc.shift_id = s.id) AS emp_count
               FROM att_attshift s LEFT JOIN personnel_company c ON c.id = s.company_id
           ORDER BY s.alias"
        )->getResultArray();

        $personalB = $db->table('att_attschedule sc')
            ->select("sc.start_date, sc.end_date, sh.alias AS shift, e.emp_code,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
            ->join('personnel_employee e', 'e.id = sc.employee_id')
            ->join('att_attshift sh', 'sh.id = sc.shift_id')
            ->orderBy('e.emp_code_digit', 'ASC');
        [$personal, $personalPager] = $this->paged($personalB, 25, 'personal');

        $deptSched = $db->query(
            "SELECT ds.start_date, ds.end_date, sh.alias AS shift, d.dept_name
               FROM att_departmentschedule ds
               JOIN personnel_department d ON d.id = ds.department_id
               JOIN att_attshift sh ON sh.id = ds.shift_id
           ORDER BY ds.start_date DESC"
        )->getResultArray();

        return view('admin/shifts/index', [
            'title'        => 'Shifts & Timetables',
            'subtitle'     => 'How scheduled hours are defined',
            'active'       => 'shifts',
            'timetables'   => $timetables,
            'shifts'       => $shifts,
            'personal'     => $personal,
            'personalPager' => $personalPager,
            'deptSched'    => $deptSched,
            'cfg'          => config('Attendance'),
        ]);
    }
}
