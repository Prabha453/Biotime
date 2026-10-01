<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Leave entitlement vs. taken vs. remaining, per (employee, pay code, year).
 *
 *   entitlement  → att_leavegroupdetail.allow_leave_day for the employee's
 *                  leave group (personnel_employee.leave_group)
 *   taken        → Σ att_leave.leave_day for APPROVED leave of that pay code
 *                  whose start falls in the year
 *   balance      → entitlement − taken  (+ carried pre_balance if allowed)
 *
 * A snapshot is written to att_leaveyearbalance on each approval so ZKBioTime
 * reports stay consistent.
 */
class LeaveBalance
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return array{entitlement:float,taken:float,balance:float,allow_exceed:bool,pre_balance:float}
     */
    public function forEmployee(int $empId, int $payCodeId, ?int $year = null): array
    {
        $year ??= (int) date('Y');

        $groupId = (int) ($this->db->query(
            'SELECT leave_group FROM personnel_employee WHERE id = ?', [$empId]
        )->getRow()->leave_group ?? 0);

        $detail = $groupId ? $this->db->query(
            'SELECT allow_leave_day, allow_exceed_limit, allow_balance
               FROM att_leavegroupdetail
              WHERE leave_group_id = ? AND pay_code_id = ?
              LIMIT 1',
            [$groupId, $payCodeId]
        )->getRowArray() : null;

        $entitlement  = (float) ($detail['allow_leave_day'] ?? 0);
        $allowExceed  = (int) ($detail['allow_exceed_limit'] ?? 0) === 1;
        $allowBalance = (int) ($detail['allow_balance'] ?? 0) === 1;

        $taken = (float) ($this->db->query(
            "SELECT COALESCE(SUM(l.leave_day),0) t
               FROM att_leave l
               JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
              WHERE w.employee_id = ? AND l.pay_code_id = ? AND w.approval_status = 1
                AND EXTRACT(YEAR FROM l.start_time) = ?",
            [$empId, $payCodeId, $year]
        )->getRow()->t);

        $preBalance = 0.0;
        if ($allowBalance) {
            $preBalance = (float) ($this->db->query(
                'SELECT COALESCE(leave_day,0) b FROM att_leaveyearbalance
                  WHERE employee_id = ? AND pay_code_id = ? AND year = ? LIMIT 1',
                [$empId, $payCodeId, $year - 1]
            )->getRow()->b ?? 0);
        }

        return [
            'entitlement'  => $entitlement,
            'taken'        => round($taken, 2),
            'balance'      => round($entitlement + $preBalance - $taken, 2),
            'allow_exceed' => $allowExceed,
            'pre_balance'  => round($preBalance, 2),
        ];
    }

    /**
     * Batched forEmployee() for a whole report page — 4 queries total
     * (one per data source), not 4 per (employee, pay code) pair. A page
     * with 83 employees × 5 leave types would otherwise run forEmployee()
     * 415 times (up to ~1600 queries) for exactly the same result.
     *
     * @param list<int> $empIds
     * @param list<int> $payCodeIds
     * @return array<int,array<int,array{entitlement:float,taken:float,balance:float,allow_exceed:bool,pre_balance:float}>> empId => payCodeId => same shape as forEmployee()
     */
    public function forEmployees(array $empIds, array $payCodeIds, ?int $year = null): array
    {
        $year ??= (int) date('Y');
        $empIds     = array_values(array_unique(array_map('intval', $empIds)));
        $payCodeIds = array_values(array_unique(array_map('intval', $payCodeIds)));
        if ($empIds === [] || $payCodeIds === []) {
            return [];
        }

        $groupByEmp = [];
        foreach ($this->db->table('personnel_employee')->select('id, leave_group')->whereIn('id', $empIds)->get()->getResultArray() as $r) {
            $groupByEmp[(int) $r['id']] = (int) ($r['leave_group'] ?? 0);
        }
        $groupIds = array_values(array_unique(array_filter($groupByEmp)));

        $details = [];
        if ($groupIds !== []) {
            foreach ($this->db->table('att_leavegroupdetail')
                ->select('leave_group_id, pay_code_id, allow_leave_day, allow_exceed_limit, allow_balance')
                ->whereIn('leave_group_id', $groupIds)->whereIn('pay_code_id', $payCodeIds)
                ->get()->getResultArray() as $r) {
                $details[(int) $r['leave_group_id']][(int) $r['pay_code_id']] = $r;
            }
        }

        $taken = [];
        foreach ($this->db->query(
            'SELECT w.employee_id, l.pay_code_id, COALESCE(SUM(l.leave_day),0) t
               FROM att_leave l
               JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
              WHERE w.employee_id IN (' . implode(',', $empIds) . ')
                AND l.pay_code_id IN (' . implode(',', $payCodeIds) . ')
                AND w.approval_status = 1
                AND EXTRACT(YEAR FROM l.start_time) = ?
              GROUP BY w.employee_id, l.pay_code_id',
            [$year]
        )->getResultArray() as $r) {
            $taken[(int) $r['employee_id']][(int) $r['pay_code_id']] = (float) $r['t'];
        }

        $preBal = [];
        foreach ($this->db->table('att_leaveyearbalance')
            ->select('employee_id, pay_code_id, leave_day')
            ->whereIn('employee_id', $empIds)->whereIn('pay_code_id', $payCodeIds)->where('year', $year - 1)
            ->get()->getResultArray() as $r) {
            $preBal[(int) $r['employee_id']][(int) $r['pay_code_id']] = (float) ($r['leave_day'] ?? 0);
        }

        $out = [];
        foreach ($empIds as $empId) {
            $groupId = $groupByEmp[$empId] ?? 0;
            foreach ($payCodeIds as $payCodeId) {
                $detail       = $groupId ? ($details[$groupId][$payCodeId] ?? null) : null;
                $entitlement  = (float) ($detail['allow_leave_day'] ?? 0);
                $allowBalance = (int) ($detail['allow_balance'] ?? 0) === 1;
                $takenAmt     = $taken[$empId][$payCodeId] ?? 0.0;
                $pre          = $allowBalance ? ($preBal[$empId][$payCodeId] ?? 0.0) : 0.0;

                $out[$empId][$payCodeId] = [
                    'entitlement'  => $entitlement,
                    'taken'        => round($takenAmt, 2),
                    'balance'      => round($entitlement + $pre - $takenAmt, 2),
                    'allow_exceed' => (int) ($detail['allow_exceed_limit'] ?? 0) === 1,
                    'pre_balance'  => round($pre, 2),
                ];
            }
        }

        return $out;
    }

    /** Persist the year snapshot (called after a leave decision). */
    public function snapshot(int $empId, int $payCodeId, int $year): void
    {
        $b   = $this->forEmployee($empId, $payCodeId, $year);
        $row = [
            'employee_id'      => $empId,
            'pay_code_id'      => $payCodeId,
            'year'             => $year,
            'entitlement_days' => (int) round($b['entitlement']),
            'leave_day'        => $b['balance'],
            'pre_balance'      => (int) round($b['pre_balance']),
            'status'           => 0,
        ];
        $exists = $this->db->table('att_leaveyearbalance')
            ->where('employee_id', $empId)->where('pay_code_id', $payCodeId)->where('year', $year)
            ->countAllResults();
        if ($exists) {
            $this->db->table('att_leaveyearbalance')
                ->where('employee_id', $empId)->where('pay_code_id', $payCodeId)->where('year', $year)
                ->update($row);
        } else {
            $row['create_time'] = date('Y-m-d H:i:sP');
            $this->db->table('att_leaveyearbalance')->insert($row);
        }
    }
}
