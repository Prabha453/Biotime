<?php

namespace App\Controllers;

use App\Libraries\LeaveBalance;

/**
 * Personnel ▸ Employee Leave Balance — mirrors ZKBioTime's
 * personnel.EmployeeLeaveBalance grid: one editable entitlement / carry-forward
 * row per (employee, leave type, year), with the taken days and remaining
 * balance computed live from approved att_leave. Stored in att_leaveyearbalance.
 *
 * Leave types are the att_paycode rows with code_type = 3 (leave).
 */
class EmployeeLeaveBalance extends BaseController
{
    public function index(): string
    {
        $db   = db_connect();
        $year = (int) ($this->request->getGet('year') ?: date('Y'));
        $dept = (int) $this->request->getGet('dept');
        $q    = trim((string) $this->request->getGet('q'));

        $b = $db->table('att_leaveyearbalance b')
            ->select("b.id, b.year, b.entitlement_days, b.pre_balance, b.employee_id, b.pay_code_id,
                      p.code AS pay_code, p.name AS pay_name,
                      e.emp_code, e.department_id,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS emp_name,
                      d.dept_name,
                      COALESCE((SELECT SUM(l.leave_day)
                                  FROM att_leave l
                                  JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
                                 WHERE w.employee_id = b.employee_id
                                   AND l.pay_code_id = b.pay_code_id
                                   AND w.approval_status = 1
                                   AND EXTRACT(YEAR FROM l.start_time) = b.year), 0) AS taken")
            ->join('att_paycode p', 'p.id = b.pay_code_id', 'left')
            ->join('personnel_employee e', 'e.id = b.employee_id')
            ->join('personnel_department d', 'd.id = e.department_id', 'left')
            ->where('b.year', $year)
            ->orderBy('e.emp_code_digit', 'ASC')->orderBy('p.code', 'ASC');

        if ($dept) {
            $b->where('e.department_id', $dept);
        }
        if ($q !== '') {
            $b->groupStart()
                ->like('e.emp_code', $q, 'both', null, true)
                ->orLike('e.first_name', $q, 'both', null, true)
                ->orLike('e.last_name', $q, 'both', null, true)
                ->groupEnd();
        }

        $fbApplied = $this->filterBuilder($b, [
            'emp_code'         => ['e.emp_code', 'text'],
            'emp_name'         => ["TRIM(CONCAT(e.first_name,' ',e.last_name))", 'text'],
            'pay_code'         => ['p.code', 'text'],
            'entitlement_days' => ['b.entitlement_days', 'number'],
            'pre_balance'      => ['b.pre_balance', 'number'],
        ]);

        [$rows, $pager] = $this->paged($b, 30);

        return view('admin/leavebalance/index', [
            'title'     => 'Employee Leave Balance',
            'subtitle'  => 'Entitlement, carried-forward and remaining leave per employee',
            'active'    => 'psnl.leavebal',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'year'      => $year,
            'dept'      => $dept,
            'q'         => $q,
            'years'     => range((int) date('Y') + 1, (int) date('Y') - 4),
            'depts'     => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
            'types'     => $db->query("SELECT id, code, name FROM att_paycode WHERE code_type = 3 ORDER BY code")->getResultArray(),
            'employees' => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit")->getResultArray(),
        ]);
    }

    public function save()
    {
        if (! $this->validate([
            'employee_id'      => 'required|is_natural_no_zero',
            'pay_code_id'      => 'required|is_natural_no_zero',
            'year'             => 'required|is_natural_no_zero',
            'entitlement_days' => 'permit_empty|numeric',
            'pre_balance'      => 'permit_empty|numeric',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'lbForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $emp  = (int) $p->getPost('employee_id');
        $pc   = (int) $p->getPost('pay_code_id');
        $year = (int) $p->getPost('year');
        $data = [
            'employee_id'      => $emp,
            'pay_code_id'      => $pc,
            'year'             => $year,
            'entitlement_days' => (int) round((float) $p->getPost('entitlement_days')),
            'pre_balance'      => (int) round((float) $p->getPost('pre_balance')),
            'status'           => 0,
        ];

        $db = db_connect();
        $ok = $this->dbGuard(static function () use ($db, $id, $emp, $pc, $year, $data) {
            $existing = $id
                ? $db->table('att_leaveyearbalance')->where('id', $id)
                : $db->table('att_leaveyearbalance')->where('employee_id', $emp)->where('pay_code_id', $pc)->where('year', $year);
            if ((clone $existing)->countAllResults()) {
                return $existing->update($data + ['change_time' => date('Y-m-d H:i:sP'), 'change_user' => session()->get('userName')]);
            }

            return $db->table('att_leaveyearbalance')->insert($data + ['create_time' => date('Y-m-d H:i:sP'), 'create_user' => session()->get('userName')]);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Could not save the leave balance.');
        }
        $this->audit($id ? 'personnel.leavebal.update' : 'personnel.leavebal.create', "emp={$emp} pc={$pc} {$year}");

        return redirect()->to(route_to('leavebalance') . '?year=' . $year)->with('success', 'Leave balance saved.');
    }

    public function delete(int $id)
    {
        db_connect()->table('att_leaveyearbalance')->where('id', $id)->delete();
        $this->audit('personnel.leavebal.delete', (string) $id);

        return redirect()->back()->with('success', 'Leave balance removed.');
    }

    public function bulkDelete()
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        db_connect()->table('att_leaveyearbalance')->whereIn('id', $ids)->delete();
        $this->audit('personnel.leavebal.bulk_delete', count($ids) . ' rows');

        return redirect()->back()->with('success', 'Deleted ' . count($ids) . ' leave balance(s).');
    }

    /**
     * Seed / refresh rows for a whole year from each employee's leave group
     * quota (att_leavegroupdetail). Existing manual rows are left untouched.
     */
    public function seed()
    {
        $year = (int) ($this->request->getPost('year') ?: date('Y'));
        $db   = db_connect();
        $lb   = new LeaveBalance($db);

        $pairs = $db->query(
            "SELECT e.id emp_id, gd.pay_code_id
               FROM personnel_employee e
               JOIN att_leavegroupdetail gd ON gd.leave_group_id = e.leave_group
              WHERE e.is_active = true AND e.leave_group > 0"
        )->getResultArray();

        $n = 0;
        $this->tx(function ($db) use ($pairs, $year, $lb, &$n) {
            foreach ($pairs as $r) {
                $emp = (int) $r['emp_id'];
                $pc  = (int) $r['pay_code_id'];
                if ($db->table('att_leaveyearbalance')->where('employee_id', $emp)->where('pay_code_id', $pc)->where('year', $year)->countAllResults()) {
                    continue;
                }
                $bal = $lb->forEmployee($emp, $pc, $year);
                $db->table('att_leaveyearbalance')->insert([
                    'employee_id' => $emp, 'pay_code_id' => $pc, 'year' => $year,
                    'entitlement_days' => (int) round($bal['entitlement']),
                    'pre_balance' => (int) round($bal['pre_balance']),
                    'status' => 0, 'create_time' => date('Y-m-d H:i:sP'),
                ]);
                $n++;
            }
        }, 'Could not seed leave balances.');
        $this->audit('personnel.leavebal.seed', "{$year}: {$n} rows");

        return redirect()->to(route_to('leavebalance') . '?year=' . $year)->with(
            $n ? 'success' : 'error',
            $n ? "Seeded {$n} leave-balance rows from leave-group quotas." : 'Nothing to seed — no employees have a leave group with quota rows.'
        );
    }
}
