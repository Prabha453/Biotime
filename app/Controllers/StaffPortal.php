<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;
use App\Libraries\AttPolicy;
use App\Libraries\LeaveBalance;
use App\Libraries\Mailer;
use App\Libraries\Notify;
use App\Libraries\PayrollCalculator;

/**
 * Employee self-service portal. Read-only views of the signed-in employee's
 * own attendance / payslips / leave balance, plus leave / manual-punch /
 * overtime requests that flow through the very same
 * workflow_workflowinstance + att_* tables the admin Requests screen approves.
 */
class StaffPortal extends BaseController
{
    private const REQ_TYPES = ['leave' => 'Leave', 'manual' => 'Manual Punch', 'overtime' => 'Overtime'];

    private function eid(): int
    {
        return (int) session()->get('staffId');
    }

    private function emp(): array
    {
        return db_connect()->query(
            "SELECT e.id, e.emp_code, e.email, e.mobile, e.hire_date,
                    NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                    d.dept_name, p.position_name
               FROM personnel_employee e
          LEFT JOIN personnel_department d ON d.id = e.department_id
          LEFT JOIN personnel_position p ON p.id = e.position_id
              WHERE e.id = ?",
            [$this->eid()]
        )->getRowArray() ?? [];
    }

    // ------------------------------------------------------------------ dashboard

    public function dashboard(): string
    {
        $year  = (int) date('Y');
        $month = (int) date('n');
        $m     = (new AttendanceCalculator())->monthly($this->eid(), $year, $month);
        $t     = $m['totals'];

        $db      = db_connect();
        $pending = (int) $db->query(
            'SELECT count(*) c FROM workflow_workflowinstance WHERE employee_id = ? AND approval_status = 0',
            [$this->eid()]
        )->getRow()->c;

        $lastSlip = $db->query(
            "SELECT to_char(calc_time,'YYYY') y, to_char(calc_time,'MM') m, net_pay
               FROM payroll_payrollpayload WHERE employee_id = ? ORDER BY calc_time DESC LIMIT 1",
            [$this->eid()]
        )->getRowArray();

        $types    = $db->query('SELECT id, name FROM att_paycode WHERE code_type = 3 ORDER BY display_order')->getResultArray();
        $lb       = new LeaveBalance($db);
        $balances = [];
        foreach ($types as $ty) {
            $b = $lb->forEmployee($this->eid(), (int) $ty['id'], $year);
            if (($b['entitlement'] ?? 0) > 0) {
                $balances[] = ['name' => $ty['name']] + $b;
            }
        }

        return view('portal/dashboard', [
            'title'    => 'My Dashboard',
            'active'   => 'dashboard',
            'emp'      => $this->emp(),
            'totals'   => $t,
            'period'   => date('F Y'),
            'pending'  => $pending,
            'lastSlip' => $lastSlip,
            'balances' => $balances,
        ]);
    }

    // ------------------------------------------------------------------ attendance

    public function attendance(): string
    {
        $ym = (string) ($this->request->getGet('ym') ?: date('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = date('Y-m');
        }
        [$year, $month] = array_map('intval', explode('-', $ym));

        $data = (new AttendanceCalculator())->monthly($this->eid(), $year, $month);

        return view('portal/attendance', [
            'title'  => 'My Attendance',
            'active' => 'attendance',
            'emp'    => $this->emp(),
            'ym'     => $ym,
            'label'  => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
            'days'   => $data['days'],
            'totals' => $data['totals'],
        ]);
    }

    // ------------------------------------------------------------------ payslips

    public function payslips(): string
    {
        $rows = db_connect()->query(
            "SELECT to_char(calc_time,'YYYY') y, to_char(calc_time,'MM') m,
                    basic_salary, total_increase, total_deduction, net_pay
               FROM payroll_payrollpayload
              WHERE employee_id = ? ORDER BY calc_time DESC",
            [$this->eid()]
        )->getResultArray();

        return view('portal/payslips', [
            'title'  => 'My Payslips',
            'active' => 'payslips',
            'emp'    => $this->emp(),
            'rows'   => $rows,
        ]);
    }

    public function payslip(int $y, int $m)
    {
        $calc   = new PayrollCalculator();
        $stored = $calc->slip($this->eid(), $y, $m);
        if (! $stored) {
            return redirect()->to(route_to('portal.payslips'))
                ->with('error', 'No payslip has been published for that month.');
        }
        $live = $calc->compute($this->eid(), $y, $m);
        $fd   = json_decode((string) $stored['format_dict'], true) ?: [];
        $s    = array_merge($live, [
            'base_salary'      => (float) $stored['basic_salary'],
            'net_pay'          => (float) $stored['net_pay'],
            'total_earnings'   => (float) $stored['total_salary'],
            'total_deductions' => (float) $stored['total_deduction'],
            'earnings'         => $fd['earnings'] ?? $live['earnings'],
            'deductions'       => $fd['deductions'] ?? $live['deductions'],
        ]);

        return view('portal/payslip', [
            'title'  => 'Payslip',
            'active' => 'payslips',
            'emp'    => $this->emp(),
            's'      => $s,
            'y'      => $y,
            'm'      => $m,
            'cur'    => config('Payroll')->currency,
        ]);
    }

    // ------------------------------------------------------------------ requests

    public function requests(): string
    {
        $db = db_connect();
        $b  = $db->table('workflow_workflowinstance w')
            ->select("w.id, w.approval_status, w.approval_remark, w.approver,
                    CASE
                      WHEN l.workflowinstance_ptr_id IS NOT NULL THEN 'leave'
                      WHEN mn.workflowinstance_ptr_id IS NOT NULL THEN 'manual'
                      WHEN o.workflowinstance_ptr_id IS NOT NULL THEN 'overtime'
                      ELSE 'other' END kind,
                    COALESCE(to_char(l.start_time,'YYYY-MM-DD'), to_char(mn.punch_time,'YYYY-MM-DD HH24:MI'),
                             to_char(o.start_time,'YYYY-MM-DD HH24:MI')) a,
                    COALESCE(to_char(l.end_time,'YYYY-MM-DD'), to_char(o.end_time,'YYYY-MM-DD HH24:MI')) b,
                    COALESCE(pcl.name, pco.name,
                      CASE mn.punch_state WHEN '0' THEN 'Check In' WHEN '1' THEN 'Check Out'
                           WHEN '2' THEN 'Break Out' WHEN '3' THEN 'Break In' ELSE 'Punch' END) detail,
                    COALESCE(l.apply_reason, mn.apply_reason, o.apply_reason) reason,
                    COALESCE(to_char(l.apply_time,'YYYY-MM-DD'), to_char(mn.apply_time,'YYYY-MM-DD'),
                             to_char(o.apply_time,'YYYY-MM-DD')) applied")
            ->join('att_leave l', 'l.workflowinstance_ptr_id = w.id', 'left')
            ->join('att_manuallog mn', 'mn.workflowinstance_ptr_id = w.id', 'left')
            ->join('att_overtime o', 'o.workflowinstance_ptr_id = w.id', 'left')
            ->join('att_paycode pcl', 'pcl.id = l.pay_code_id', 'left')
            ->join('att_paycode pco', 'pco.id = o.pay_code_id', 'left')
            ->where('w.employee_id', $this->eid())
            ->groupStart()
                ->where('l.workflowinstance_ptr_id IS NOT NULL', null, false)
                ->orWhere('mn.workflowinstance_ptr_id IS NOT NULL', null, false)
                ->orWhere('o.workflowinstance_ptr_id IS NOT NULL', null, false)
            ->groupEnd()
            ->orderBy('w.id', 'DESC');
        [$rows, $pager] = $this->paged($b, 25);

        return view('portal/requests', [
            'title'      => 'My Requests',
            'active'     => 'requests',
            'emp'        => $this->emp(),
            'rows'       => $rows,
            'pager'      => $pager,
            'types'      => self::REQ_TYPES,
            'leaveTypes' => $db->query('SELECT id, name FROM att_paycode WHERE code_type = 3 ORDER BY display_order')->getResultArray(),
            'otTypes'    => $db->query('SELECT id, name FROM att_paycode WHERE code_type = 2 ORDER BY display_order')->getResultArray(),
        ]);
    }

    public function apply()
    {
        $type = (string) $this->request->getPost('type');
        if (! isset(self::REQ_TYPES[$type])) {
            return redirect()->back()->with('error', 'Unknown request type.');
        }

        $rules = match ($type) {
            'leave'    => ['pay_code_id' => 'required|is_natural_no_zero', 'start_date' => 'required|valid_date', 'end_date' => 'required|valid_date'],
            'manual'   => ['punch_date' => 'required|valid_date', 'punch_time' => 'required', 'punch_state' => 'required|in_list[0,1,2,3,4,5]'],
            'overtime' => ['ot_date' => 'required|valid_date', 'ot_start' => 'required', 'ot_end' => 'required'],
        };
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'req_' . $type);
        }

        $p     = $this->request;
        $empId = $this->eid();
        $now   = date('Y-m-d H:i:sP');

        if ($type === 'leave') {
            $payCode = (int) $p->getPost('pay_code_id');
            $reqDays = $p->getPost('half_day') ? 0.5 : $this->workingDays($p->getPost('start_date'), $p->getPost('end_date'));
            $bal     = (new LeaveBalance())->forEmployee($empId, $payCode, (int) substr($p->getPost('start_date'), 0, 4));
            if (($bal['entitlement'] ?? 0) > 0 && empty($bal['allow_exceed']) && $reqDays > $bal['balance']) {
                return redirect()->back()->with('reopen', 'req_leave')->with('error',
                    sprintf('Only %s day(s) of that leave type remain.', rtrim(rtrim((string) $bal['balance'], '0'), '.')));
            }
        }

        $ok = $this->tx(function ($db) use ($type, $p, $empId, $now) {
            $db->table('workflow_workflowinstance')->insert([
                'approval_status' => 0,
                'employee_id'     => $empId,
            ]);
            $wid = (int) $db->insertID();

            match ($type) {
                'leave' => $db->table('att_leave')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'start_time'   => $p->getPost('start_date') . ' 00:00:00',
                    'end_time'     => $p->getPost('end_date') . ' 23:59:59',
                    'apply_reason' => $p->getPost('reason') ?: null,
                    'apply_time'   => $now,
                    'leave_day'    => $p->getPost('half_day') ? 0.5 : $this->workingDays($p->getPost('start_date'), $p->getPost('end_date')),
                    'pay_code_id'  => (int) $p->getPost('pay_code_id'),
                ]),
                'manual' => $db->table('att_manuallog')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'punch_time'   => $p->getPost('punch_date') . ' ' . $p->getPost('punch_time') . ':00',
                    'punch_state'  => (string) (int) $p->getPost('punch_state'),
                    'apply_reason' => $p->getPost('reason') ?: null,
                    'apply_time'   => $now,
                ]),
                'overtime' => $db->table('att_overtime')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'overtime_type' => 1,
                    'start_time'   => $p->getPost('ot_date') . ' ' . $p->getPost('ot_start') . ':00',
                    'end_time'     => $p->getPost('ot_date') . ' ' . $p->getPost('ot_end') . ':00',
                    'apply_reason' => $p->getPost('reason') ?: null,
                    'apply_time'   => $now,
                    'pay_code_id'  => (int) $p->getPost('pay_code_id') ?: null,
                ]),
            };
        });

        if (! $ok) {
            return redirect()->back()->with('error', 'Your request could not be submitted — please check the dates.');
        }

        Notify::push(
            'New ' . strtolower(self::REQ_TYPES[$type]) . ' request from ' . (session()->get('staffName')),
            Notify::REQUEST,
            site_url('admin/requests/' . $type)
        );
        $approvers = db_connect()->query(
            "SELECT email FROM auth_user
              WHERE COALESCE(del_flag,0) = 0 AND is_active = true
                AND (is_superuser = true OR is_staff = true)
                AND email IS NOT NULL AND email <> ''"
        )->getResultArray();
        foreach ($approvers as $a) {
            Mailer::fire(Mailer::EV_REQUEST_SUBMITTED, $a['email'], [
                'employee' => (string) session()->get('staffName'),
                'type'     => strtolower(self::REQ_TYPES[$type]),
            ]);
        }

        return redirect()->to(route_to('portal.requests'))
            ->with('success', self::REQ_TYPES[$type] . ' request submitted for approval.');
    }

    public function withdraw(int $id)
    {
        $db  = db_connect();
        $own = $db->query(
            'SELECT approval_status FROM workflow_workflowinstance WHERE id = ? AND employee_id = ?',
            [$id, $this->eid()]
        )->getRowArray();
        if (! $own) {
            return redirect()->back()->with('error', 'Request not found.');
        }
        if ((int) $own['approval_status'] !== 0) {
            return redirect()->back()->with('error', 'That request has already been decided and cannot be withdrawn.');
        }

        $ok = $this->tx(function ($db) use ($id) {
            foreach (['att_leave', 'att_manuallog', 'att_overtime'] as $t) {
                $db->table($t)->where('workflowinstance_ptr_id', $id)->delete();
            }
            $db->table('workflow_workflowinstance')->where('id', $id)->delete();
        });

        return redirect()->back()->with($ok ? 'success' : 'error',
            $ok ? 'Request withdrawn.' : 'Could not withdraw the request.');
    }

    // ------------------------------------------------------------------ profile

    public function profile(): string
    {
        return view('portal/profile', [
            'title'  => 'My Profile',
            'active' => 'profile',
            'emp'    => $this->emp(),
        ]);
    }

    public function changePassword()
    {
        if (! $this->validate([
            'current'  => 'required',
            'new'      => 'required|min_length[8]',
            'confirm'  => 'required|matches[new]',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $db  = db_connect();
        $cur = $db->query('SELECT self_password FROM personnel_employee WHERE id = ?', [$this->eid()])->getRowArray();
        if (! $cur || ! \App\Models\AuthUserModel::verifyDjangoPassword($this->request->getPost('current'), (string) $cur['self_password'])) {
            return redirect()->back()->with('error', 'Your current password is incorrect.');
        }

        $hash = \App\Models\AuthUserModel::makeDjangoPassword((string) $this->request->getPost('new'));
        $ok   = $this->dbGuard(fn () => $db->table('personnel_employee')->where('id', $this->eid())->update(['self_password' => $hash]));

        return redirect()->to(route_to('portal.profile'))->with($ok !== null ? 'success' : 'error',
            $ok !== null ? 'Password changed.' : 'Password could not be changed.');
    }

    // ------------------------------------------------------------------ helper

    private function workingDays(string $from, string $to): float
    {
        $pol      = AttPolicy::load();
        $db       = db_connect();
        $holidays = $db->query(
            'SELECT start_date, end_date FROM att_holiday WHERE start_date <= ? AND end_date >= ?',
            [$to, $from]
        )->getResultArray();

        $days   = 0;
        $cursor = strtotime($from);
        $end    = strtotime($to);
        while ($cursor <= $end) {
            $d  = date('Y-m-d', $cursor);
            $wd = (int) date('w', $cursor);
            $isHol = false;
            foreach ($holidays as $h) {
                if ($d >= $h['start_date'] && $d <= $h['end_date']) {
                    $isHol = true;
                    break;
                }
            }
            if (! in_array($wd, $pol->weekendDays, true) && ! $isHol) {
                $days++;
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        return max(1, $days);
    }
}
