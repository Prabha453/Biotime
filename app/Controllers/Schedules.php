<?php

namespace App\Controllers;

/**
 * Schedule Assignment — split into four real pages (Employee / Department /
 * Company / Temporary), one per menu item, instead of one page bundling
 * every type behind modals. Employee/Department/Temporary mirror
 * ZKBioTime's own "Schedule Assignment" menu group; Company has no
 * ZKBioTime equivalent — it's this app's own addition, backed by its own
 * att_companyschedule table (see migration AddCompanyScheduleTable) and
 * resolved by AttendanceCalculator::shiftForEmployee() between Department
 * and Group.
 */
class Schedules extends BaseController
{
    // ------------------------------------------------------------------
    //  Employee (personal) schedule
    // ------------------------------------------------------------------

    public function personal(): string
    {
        $db      = db_connect();
        $q       = trim((string) $this->request->getGet('q'));
        $company = (int) $this->request->getGet('company') ?: null;

        $builder = $db->table('att_attschedule sc')
            ->select("sc.id, sc.start_date, sc.end_date, sc.shift_id, sh.alias AS shift,
                      e.id AS emp_id, e.emp_code,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
            ->join('personnel_employee e', 'e.id = sc.employee_id')
            ->join('att_attshift sh', 'sh.id = sc.shift_id')
            ->orderBy('e.emp_code_digit', 'ASC');
        if ($q !== '') {
            $builder->groupStart()->like('e.emp_code', $q)->orLike('e.first_name', $q)->orLike('e.last_name', $q)->groupEnd();
        }
        if ($company) {
            $builder->join('personnel_department d', 'd.id = e.department_id', 'left')->where('d.company_id', $company);
        }
        [$rows, $pager] = $this->paged($builder, 20);

        return view('admin/schedules/personal', [
            'title'     => 'Employee Schedule',
            'subtitle'  => 'Which shift each employee follows',
            'active'    => 'schedules.personal',
            'q'         => $q,
            'company'   => $company,
            'rows'      => $rows,
            'pager'     => $pager,
            'employees' => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee ORDER BY emp_code_digit")->getResultArray(),
            'shifts'    => $db->query('SELECT id, alias FROM att_attshift ORDER BY alias')->getResultArray(),
            'companies' => (new \App\Models\EmployeeModel())->companies(),
        ]);
    }

    public function assign()
    {
        $p      = $this->request;
        $empIds = array_values(array_unique(array_filter(array_map('intval', (array) $p->getPost('employee_id')))));

        $rules = [
            'shift_id'   => 'required|is_natural_no_zero',
            'start_date' => 'required|valid_date',
            'end_date'   => 'required|valid_date',
        ];
        if ($empIds === [] || ! $this->validate($rules)) {
            $errors = $this->validator->getErrors();
            if ($empIds === []) {
                $errors['employee_id'] = 'Select at least one employee.';
            }

            return redirect()->back()->with('errors', $errors)->with('reopen', 'schAssign');
        }
        if ($p->getPost('end_date') < $p->getPost('start_date')) {
            return redirect()->back()->with('error', 'End date must be after start date.');
        }
        $shiftId = (int) $p->getPost('shift_id');
        $from    = $p->getPost('start_date');
        $to      = $p->getPost('end_date');

        $db       = db_connect();
        $inserted = 0;
        $skipped  = 0;
        foreach ($empIds as $empId) {
            // reject a range that overlaps an existing personal schedule
            $clash = $db->table('att_attschedule')
                ->where('employee_id', $empId)
                ->where('start_date <=', $to)->where('end_date >=', $from)
                ->countAllResults();
            if ($clash) {
                $skipped++;

                continue;
            }
            $ok = $this->dbGuard(fn () => db_connect()->table('att_attschedule')->insert([
                'employee_id' => $empId, 'shift_id' => $shiftId,
                'start_date'  => $from, 'end_date' => $to,
            ]));
            if ($ok !== null) {
                $inserted++;
            }
        }
        $this->audit('schedule.assign', 'emp=' . implode(',', $empIds) . " shift={$shiftId}", "{$from}..{$to}");

        if ($inserted === 0) {
            return redirect()->back()->with('error', 'No schedules saved — the selected employee(s) already have an overlapping schedule.');
        }
        $msg = 'Shift assigned to ' . $inserted . ' employee' . ($inserted === 1 ? '' : 's') . '.';
        if ($skipped) {
            $msg .= ' ' . $skipped . ' skipped (overlapping schedule).';
        }

        return redirect()->to(route_to('schedules.personal'))->with('success', $msg);
    }

    public function delete(int $id)
    {
        db_connect()->table('att_attschedule')->where('id', $id)->delete();
        $this->audit('schedule.delete', (string) $id);

        return redirect()->back()->with('success', 'Schedule removed.');
    }

    // ------------------------------------------------------------------
    //  Department schedule (+ the "company-wide" fan-out convenience)
    // ------------------------------------------------------------------

    public function department(): string
    {
        $db = db_connect();

        return view('admin/schedules/department', [
            'title'       => 'Department Schedule',
            'subtitle'    => 'Assign a shift to every employee in a department',
            'active'      => 'schedules.department',
            'shifts'      => $db->query('SELECT id, alias FROM att_attshift ORDER BY alias')->getResultArray(),
            'departments' => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
            'companies'   => (new \App\Models\EmployeeModel())->companies(),
            'deptSched'   => $db->query("SELECT ds.id, ds.start_date, ds.end_date, d.dept_name, sh.alias shift
                                         FROM att_departmentschedule ds
                                         JOIN personnel_department d ON d.id = ds.department_id
                                         JOIN att_attshift sh ON sh.id = ds.shift_id ORDER BY ds.start_date DESC")->getResultArray(),
        ]);
    }

    public function deptAssign()
    {
        $p = $this->request;
        if (! $p->getPost('department_id') || ! $p->getPost('shift_id') || ! $p->getPost('start_date') || ! $p->getPost('end_date')) {
            return redirect()->back()->with('error', 'Department, shift and both dates are required.');
        }
        if ($p->getPost('end_date') < $p->getPost('start_date')) {
            return redirect()->back()->with('error', 'End date must be after start date.');
        }
        $ok = $this->dbGuard(fn () => db_connect()->table('att_departmentschedule')->insert([
            'department_id' => (int) $p->getPost('department_id'),
            'shift_id'      => (int) $p->getPost('shift_id'),
            'start_date'    => $p->getPost('start_date'),
            'end_date'      => $p->getPost('end_date'),
            'status'        => 0,
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Department schedule could not be saved.');
        }
        $this->audit('schedule.dept', 'dept=' . (int) $p->getPost('department_id'));

        return redirect()->to(route_to('schedules.department'))->with('success', 'Department schedule added.');
    }

    public function deptDelete(int $id)
    {
        db_connect()->table('att_departmentschedule')->where('id', $id)->delete();
        $this->audit('schedule.dept.delete', (string) $id);

        return redirect()->back()->with('success', 'Department schedule removed.');
    }

    // ------------------------------------------------------------------
    //  Company schedule — not a ZKBioTime concept; a first-class tier of
    //  our own in att_companyschedule (see migration
    //  AddCompanyScheduleTable), sitting below Department and above Group
    //  in AttendanceCalculator::shiftForEmployee()'s resolution order.
    // ------------------------------------------------------------------

    public function company(): string
    {
        $db = db_connect();

        return view('admin/schedules/company', [
            'title'     => 'Company Schedule',
            'subtitle'  => 'Assign a shift to every employee across a whole company',
            'active'    => 'schedules.company',
            'shifts'    => $db->query('SELECT id, alias FROM att_attshift ORDER BY alias')->getResultArray(),
            'companies' => (new \App\Models\EmployeeModel())->companies(),
            'companySched' => $db->query("SELECT cs.id, cs.start_date, cs.end_date, c.company_name, sh.alias shift
                                          FROM att_companyschedule cs
                                          JOIN personnel_company c ON c.id = cs.company_id
                                          JOIN att_attshift sh ON sh.id = cs.shift_id ORDER BY cs.start_date DESC")->getResultArray(),
        ]);
    }

    public function companyAssign()
    {
        $p = $this->request;
        if (! $p->getPost('company_id') || ! $p->getPost('shift_id') || ! $p->getPost('start_date') || ! $p->getPost('end_date')) {
            return redirect()->back()->with('error', 'Company, shift and both dates are required.');
        }
        if ($p->getPost('end_date') < $p->getPost('start_date')) {
            return redirect()->back()->with('error', 'End date must be after start date.');
        }
        $ok = $this->dbGuard(fn () => db_connect()->table('att_companyschedule')->insert([
            'company_id' => (int) $p->getPost('company_id'),
            'shift_id'   => (int) $p->getPost('shift_id'),
            'start_date' => $p->getPost('start_date'),
            'end_date'   => $p->getPost('end_date'),
            'status'     => 0,
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Company schedule could not be saved.');
        }
        $this->audit('schedule.company', 'company=' . (int) $p->getPost('company_id'));

        return redirect()->to(route_to('schedules.company'))->with('success', 'Company schedule added.');
    }

    public function companyDelete(int $id)
    {
        db_connect()->table('att_companyschedule')->where('id', $id)->delete();
        $this->audit('schedule.company.delete', (string) $id);

        return redirect()->back()->with('success', 'Company schedule removed.');
    }

    // ------------------------------------------------------------------
    //  Temporary schedule — a one-day timetable override (no approval).
    //  Consumed by AttendanceCalculator::changeScheduleFor().
    // ------------------------------------------------------------------

    public function temporary(): string
    {
        $db = db_connect();

        return view('admin/schedules/temporary', [
            'title'      => 'Temporary Schedule',
            'subtitle'   => 'One-day timetable overrides — shift swaps, training days, etc.',
            'active'     => 'schedules.temporary',
            'employees'  => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee ORDER BY emp_code_digit")->getResultArray(),
            'timetables' => $db->query('SELECT id, alias FROM att_timeinterval ORDER BY alias')->getResultArray(),
            'tempSched'  => $db->query(
                "SELECT ts.id, ts.att_date, ti.alias AS timetable, e.emp_code,
                        NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name
                   FROM att_temporaryschedule ts
                   JOIN personnel_employee e ON e.id = ts.employee_id
              LEFT JOIN att_timeinterval ti ON ti.id = ts.time_interval_id
                  WHERE ts.status = 0 AND ts.att_date >= CURRENT_DATE - INTERVAL '60 days'
               ORDER BY ts.att_date DESC"
            )->getResultArray(),
        ]);
    }

    public function tempAssign()
    {
        $p      = $this->request;
        $empIds = array_values(array_unique(array_filter(array_map('intval', (array) $p->getPost('employee_id')))));

        $rules = [
            'time_interval_id' => 'required|is_natural_no_zero',
            'from_date'        => 'required|valid_date',
            'to_date'          => 'required|valid_date',
        ];
        if ($empIds === [] || ! $this->validate($rules)) {
            $errors = $this->validator->getErrors();
            if ($empIds === []) {
                $errors['employee_id'] = 'Select at least one employee.';
            }

            return redirect()->back()->with('errors', $errors)->with('reopen', 'tmpNew');
        }
        $from = $p->getPost('from_date');
        $to   = $p->getPost('to_date');
        if ($to < $from) {
            return redirect()->back()->with('error', 'End date must be on or after the start date.');
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 62) {
            return redirect()->back()->with('error', 'Use a personal schedule for ranges longer than two months.');
        }
        $tiId = (int) $p->getPost('time_interval_id');

        $ok = $this->tx(function ($db) use ($empIds, $tiId, $from, $to) {
            $end = strtotime($to);
            foreach ($empIds as $empId) {
                $cursor = strtotime($from);
                while ($cursor <= $end) {
                    $d = date('Y-m-d', $cursor);
                    $db->table('att_temporaryschedule')->where('employee_id', $empId)->where('att_date', $d)->delete();
                    $db->table('att_temporaryschedule')->insert([
                        'employee_id'      => $empId,
                        'att_date'         => $d,
                        'time_interval_id' => $tiId,
                        'status'           => 0,
                        'create_time'      => date('Y-m-d H:i:sP'),
                        'create_user'      => session()->get('userName'),
                    ]);
                    $cursor = strtotime('+1 day', $cursor);
                }
            }
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Temporary schedule could not be saved.');
        }
        $this->audit('schedule.temp.assign', 'emp=' . implode(',', $empIds) . " ti={$tiId}", "{$from}..{$to}");

        $n = count($empIds);

        return redirect()->to(route_to('schedules.temporary'))->with('success', 'Temporary schedule set for ' . $n . ' employee' . ($n === 1 ? '' : 's') . ' — recalculated for those dates.');
    }

    public function tempDelete(int $id)
    {
        db_connect()->table('att_temporaryschedule')->where('id', $id)->delete();
        $this->audit('schedule.temp.delete', (string) $id);

        return redirect()->back()->with('success', 'Temporary schedule removed.');
    }
}
