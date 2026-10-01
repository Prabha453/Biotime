<?php

namespace App\Controllers;

use App\Libraries\AttPolicy;
use App\Libraries\LeaveBalance;
use App\Libraries\Mailer;
use App\Libraries\Notify;

/**
 * Unified request / approval workflow. Every request is a
 * workflow_workflowinstance row plus one child row:
 *
 *   leave    → att_leave           (start/end, pay code)
 *   manual   → att_manuallog       (a single missed punch, in/out state)
 *   overtime → att_overtime        (start/end, pay code)
 *   schedule → att_changeschedule  (a date, a replacement timetable)
 *
 * Approved leave / manual / overtime / schedule-change are all consumed by
 * AttendanceCalculator, so approving one immediately changes the register,
 * the timesheet and the next payroll run.
 */
class Requests extends BaseController
{
    private const TABS = [
        'leave'    => 'Leave',
        'manual'   => 'Manual Punch',
        'overtime' => 'Overtime',
        'training' => 'Training',
        'schedule' => 'Schedule Change',
    ];

    // ------------------------------------------------------------------ list

    public function index(string $tab = 'leave'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'leave';
        }
        $db = db_connect();

        $builder = match ($tab) {
            'leave' => $db->table('att_leave x')
                ->select("x.workflowinstance_ptr_id id,
                          to_char(x.start_time,'YYYY-MM-DD') a, to_char(x.end_time,'YYYY-MM-DD') b,
                          x.leave_day qty, x.apply_reason reason, pc.name detail,
                          to_char(x.apply_time,'YYYY-MM-DD HH24:MI') applied")
                ->join('att_paycode pc', 'pc.id = x.pay_code_id', 'left'),
            'manual' => $db->table('att_manuallog x')
                ->select("x.workflowinstance_ptr_id id,
                          to_char(x.punch_time,'YYYY-MM-DD HH24:MI') a, NULL b,
                          NULL qty, x.apply_reason reason,
                          CASE x.punch_state WHEN '0' THEN 'Check In' WHEN '1' THEN 'Check Out'
                               WHEN '2' THEN 'Break Out' WHEN '3' THEN 'Break In' ELSE 'Punch' END detail,
                          to_char(x.apply_time,'YYYY-MM-DD HH24:MI') applied"),
            'overtime' => $db->table('att_overtime x')
                ->select("x.workflowinstance_ptr_id id,
                          to_char(x.start_time,'YYYY-MM-DD HH24:MI') a, to_char(x.end_time,'YYYY-MM-DD HH24:MI') b,
                          round(extract(epoch from (x.end_time - x.start_time))/3600, 2) qty,
                          x.apply_reason reason, pc.name detail,
                          to_char(x.apply_time,'YYYY-MM-DD HH24:MI') applied")
                ->join('att_paycode pc', 'pc.id = x.pay_code_id', 'left'),
            'training' => $db->table('att_training x')
                ->select("x.workflowinstance_ptr_id id,
                          to_char(x.start_time,'YYYY-MM-DD') a, to_char(x.end_time,'YYYY-MM-DD') b,
                          (x.end_time::date - x.start_time::date + 1) qty,
                          x.apply_reason reason, COALESCE(pc.name, 'Training') detail,
                          to_char(x.apply_time,'YYYY-MM-DD HH24:MI') applied")
                ->join('att_paycode pc', 'pc.id = x.pay_code_id', 'left'),
            'schedule' => $db->table('att_changeschedule x')
                ->select("x.workflowinstance_ptr_id id,
                          to_char(x.att_date,'YYYY-MM-DD') a, NULL b, NULL qty,
                          x.apply_reason reason, ti.alias detail,
                          to_char(x.apply_time,'YYYY-MM-DD HH24:MI') applied")
                ->join('att_timeinterval ti', 'ti.id = x.timeinterval_id', 'left'),
        };

        $builder
            ->select("w.approval_status, w.approval_remark, w.approver,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
            ->join('workflow_workflowinstance w', 'w.id = x.workflowinstance_ptr_id')
            ->join('personnel_employee e', 'e.id = w.employee_id', 'left')
            ->orderBy('x.workflowinstance_ptr_id', 'DESC');

        [$rows, $pager] = $this->paged($builder, 20);

        $pending = (int) $db->query(
            'SELECT count(*) c FROM workflow_workflowinstance w
              WHERE w.approval_status = 0
                AND (EXISTS (SELECT 1 FROM att_leave l WHERE l.workflowinstance_ptr_id = w.id)
                  OR EXISTS (SELECT 1 FROM att_manuallog m WHERE m.workflowinstance_ptr_id = w.id)
                  OR EXISTS (SELECT 1 FROM att_overtime o WHERE o.workflowinstance_ptr_id = w.id)
                  OR EXISTS (SELECT 1 FROM att_training tr WHERE tr.workflowinstance_ptr_id = w.id)
                  OR EXISTS (SELECT 1 FROM att_changeschedule c WHERE c.workflowinstance_ptr_id = w.id))'
        )->getRow()->c;

        return view('admin/requests/index', [
            'title'      => 'Requests',
            'subtitle'   => 'Leave, corrections, overtime and schedule changes',
            'active'     => 'requests',
            'tab'        => $tab,
            'tabs'       => self::TABS,
            'rows'       => $rows,
            'pager'      => $pager,
            'pending'    => $pending,
            'employees'  => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit")->getResultArray(),
            'leaveTypes' => $db->query('SELECT id, code, name, is_paid FROM att_paycode WHERE code_type = 3 ORDER BY display_order')->getResultArray(),
            'otTypes'    => $db->query('SELECT id, code, name FROM att_paycode WHERE code_type = 2 ORDER BY display_order')->getResultArray(),
            'timetables' => $db->query('SELECT id, alias FROM att_timeinterval ORDER BY alias')->getResultArray(),
            'workCodes'  => $db->query('SELECT id, code, alias FROM iclock_terminalworkcode ORDER BY code')->getResultArray(),
        ]);
    }

    // ------------------------------------------------------------------ apply

    public function apply()
    {
        $type = (string) $this->request->getPost('type');
        if (! isset(self::TABS[$type])) {
            return redirect()->back()->with('error', 'Unknown request type.');
        }

        $rules = ['employee_id' => 'required|is_natural_no_zero'];
        $rules += match ($type) {
            'leave'    => ['pay_code_id' => 'required|is_natural_no_zero', 'start_date' => 'required|valid_date', 'end_date' => 'required|valid_date'],
            'manual'   => ['punch_date' => 'required|valid_date', 'punch_time' => 'required', 'punch_state' => 'required|in_list[0,1,2,3,4,5]'],
            'overtime' => ['ot_date' => 'required|valid_date', 'ot_start' => 'required', 'ot_end' => 'required'],
            'training' => ['start_date' => 'required|valid_date', 'end_date' => 'required|valid_date'],
            'schedule' => ['sc_date' => 'required|valid_date', 'timeinterval_id' => 'required|is_natural_no_zero'],
        };
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'req_' . $type);
        }

        $p     = $this->request;
        $empId = (int) $p->getPost('employee_id');
        $auto  = (int) $p->getPost('auto_approve') === 1;
        $now   = date('Y-m-d H:i:sP');

        // Leave: enforce the remaining balance for the employee's leave group.
        if ($type === 'leave') {
            $payCode = (int) $p->getPost('pay_code_id');
            $reqDays = $p->getPost('half_day') ? 0.5 : $this->workingDays($p->getPost('start_date'), $p->getPost('end_date'));
            $bal     = (new LeaveBalance())->forEmployee($empId, $payCode, (int) substr($p->getPost('start_date'), 0, 4));
            if ($bal['entitlement'] > 0 && ! $bal['allow_exceed'] && $reqDays > $bal['balance']) {
                return redirect()->back()->with('reopen', 'req_leave')->with('error',
                    sprintf('Only %s day(s) of that leave type remain (requested %s).',
                        rtrim(rtrim((string) $bal['balance'], '0'), '.'), rtrim(rtrim((string) $reqDays, '0'), '.')));
            }
        }

        $ok = $this->tx(function ($db) use ($type, $p, $empId, $auto, $now) {
            $db->table('workflow_workflowinstance')->insert([
                'approval_status' => $auto ? 1 : 0,
                'approval_time'   => $auto ? $now : null,
                'approver'        => $auto ? session()->get('userName') : null,
                'employee_id'     => $empId,
            ]);
            $wid = (int) $db->insertID();

            match ($type) {
                'leave' => $db->table('att_leave')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'start_time'  => $p->getPost('start_date') . ' 00:00:00',
                    'end_time'    => $p->getPost('end_date') . ' 23:59:59',
                    'apply_reason'=> $p->getPost('reason') ?: null,
                    'apply_time'  => $now,
                    'leave_day'   => $p->getPost('half_day')
                        ? 0.5
                        : $this->workingDays($p->getPost('start_date'), $p->getPost('end_date')),
                    'pay_code_id' => (int) $p->getPost('pay_code_id'),
                ]),
                'manual' => $db->table('att_manuallog')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'punch_time'  => $p->getPost('punch_date') . ' ' . $p->getPost('punch_time') . ':00',
                    'punch_state' => (string) (int) $p->getPost('punch_state'),
                    'apply_reason'=> $p->getPost('reason') ?: null,
                    'apply_time'  => $now,
                    'work_code_id'=> (int) $p->getPost('work_code_id') ?: null,
                ]),
                'overtime' => $db->table('att_overtime')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'overtime_type' => 1,
                    'start_time'  => $p->getPost('ot_date') . ' ' . $p->getPost('ot_start') . ':00',
                    'end_time'    => $p->getPost('ot_date') . ' ' . $p->getPost('ot_end') . ':00',
                    'apply_reason'=> $p->getPost('reason') ?: null,
                    'apply_time'  => $now,
                    'pay_code_id' => (int) $p->getPost('pay_code_id') ?: null,
                ]),
                'training' => $db->table('att_training')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'start_time'  => $p->getPost('start_date') . ' 00:00:00',
                    'end_time'    => $p->getPost('end_date') . ' 23:59:59',
                    'apply_reason'=> $p->getPost('reason') ?: null,
                    'apply_time'  => $now,
                    'pay_code_id' => (int) $p->getPost('pay_code_id') ?: null,
                ]),
                'schedule' => $db->table('att_changeschedule')->insert([
                    'workflowinstance_ptr_id' => $wid,
                    'att_date'        => $p->getPost('sc_date'),
                    'timeinterval_id' => (int) $p->getPost('timeinterval_id'),
                    'apply_time'      => $now,
                    'apply_reason'    => $p->getPost('reason') ?: null,
                ]),
            };
        });

        if (! $ok) {
            return redirect()->back()->with('error', 'Request could not be saved — check the employee / pay code / timetable.');
        }
        if ($type === 'leave' && $auto && (int) $p->getPost('pay_code_id')) {
            (new LeaveBalance())->snapshot($empId, (int) $p->getPost('pay_code_id'), (int) substr($p->getPost('start_date'), 0, 4));
        }
        if (! $auto) {
            $who = db_connect()->query(
                "SELECT NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') n, emp_code FROM personnel_employee WHERE id = ?",
                [$empId]
            )->getRowArray();
            $whoName = $who['n'] ?? ('#' . ($who['emp_code'] ?? $empId));
            Notify::push(
                'New ' . strtolower(self::TABS[$type]) . ' request from ' . $whoName,
                Notify::REQUEST,
                site_url('admin/requests/' . $type)
            );
            // email every admin/manager who can approve it — mirrors who'd
            // see it in the shared in-app notification stream above.
            $approvers = db_connect()->query(
                "SELECT email FROM auth_user
                  WHERE COALESCE(del_flag,0) = 0 AND is_active = true
                    AND (is_superuser = true OR is_staff = true)
                    AND email IS NOT NULL AND email <> ''"
            )->getResultArray();
            foreach ($approvers as $a) {
                Mailer::fire(Mailer::EV_REQUEST_SUBMITTED, $a['email'], [
                    'employee' => $whoName,
                    'type'     => strtolower(self::TABS[$type]),
                ]);
            }
        }
        $this->audit('request.' . $type . ($auto ? '.auto_approve' : '.create'), 'emp=' . $empId);

        return redirect()->to(route_to('requests.tab', $type))
            ->with('success', self::TABS[$type] . ' request ' . ($auto ? 'approved.' : 'submitted.'));
    }

    // ------------------------------------------------------------------ decide / delete

    public function decide(int $id)
    {
        $map = ['approve' => 1, 'reject' => 2, 'revoke' => -1];
        $act = (string) $this->request->getPost('action');
        if (! isset($map[$act])) {
            return redirect()->back()->with('error', 'Unknown action.');
        }

        $db = db_connect();
        $db->table('workflow_workflowinstance')->where('id', $id)->update([
            'approval_status' => $map[$act],
            'approval_time'   => date('Y-m-d H:i:sP'),
            'approver'        => session()->get('userName'),
            'approval_remark' => $this->request->getPost('remark') ?: null,
        ]);

        // Refresh the leave-balance snapshot when a leave request is decided.
        $lv = $db->query(
            'SELECT w.employee_id, l.pay_code_id, EXTRACT(YEAR FROM l.start_time)::int y
               FROM att_leave l JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
              WHERE l.workflowinstance_ptr_id = ?', [$id]
        )->getRowArray();
        if ($lv && $lv['pay_code_id']) {
            (new LeaveBalance($db))->snapshot((int) $lv['employee_id'], (int) $lv['pay_code_id'], (int) $lv['y']);
        }
        $this->audit('request.' . $act, (string) $id);

        // Notify + (best-effort) email the employee on approve / reject.
        if (in_array($act, ['approve', 'reject'], true)) {
            $meta = $db->query(
                "SELECT w.employee_id, e.email,
                        NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') n, e.emp_code,
                        CASE
                          WHEN EXISTS(SELECT 1 FROM att_leave t WHERE t.workflowinstance_ptr_id = w.id) THEN 'leave'
                          WHEN EXISTS(SELECT 1 FROM att_manuallog t WHERE t.workflowinstance_ptr_id = w.id) THEN 'manual punch'
                          WHEN EXISTS(SELECT 1 FROM att_overtime t WHERE t.workflowinstance_ptr_id = w.id) THEN 'overtime'
                          WHEN EXISTS(SELECT 1 FROM att_training t WHERE t.workflowinstance_ptr_id = w.id) THEN 'training'
                          ELSE 'schedule change' END AS kind
                   FROM workflow_workflowinstance w
                   JOIN personnel_employee e ON e.id = w.employee_id
                  WHERE w.id = ?", [$id]
            )->getRowArray();
            if ($meta) {
                $status = $act === 'approve' ? 'approved' : 'rejected';
                Notify::push(
                    ucfirst($meta['kind']) . " request for {$meta['n']} {$status}",
                    Notify::APPROVAL, site_url('admin/requests')
                );
                Mailer::fire(
                    $act === 'approve' ? Mailer::EV_REQUEST_APPROVED : Mailer::EV_REQUEST_REJECTED,
                    $meta['email'],
                    [
                        'employee' => $meta['n'] ?: $meta['emp_code'],
                        'type'     => $meta['kind'],
                        'status'   => $status,
                        'approver' => session()->get('userName'),
                        'remark'   => $this->request->getPost('remark') ?: '',
                        'dates'    => '',
                    ]
                );
            }
        }

        return redirect()->back()->with('success', 'Request ' . $act . 'd.');
    }

    public function delete(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            foreach (['att_leave', 'att_manuallog', 'att_overtime', 'att_training', 'att_changeschedule'] as $t) {
                $db->table($t)->where('workflowinstance_ptr_id', $id)->delete();
            }
            $db->table('workflow_workflowinstance')->where('id', $id)->delete();
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Could not delete the request.');
        }
        $this->audit('request.delete', (string) $id);

        return redirect()->back()->with('success', 'Request deleted.');
    }

    // ------------------------------------------------------------------ helpers

    /** Calendar days in [from,to] minus weekend days minus holidays. */
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
