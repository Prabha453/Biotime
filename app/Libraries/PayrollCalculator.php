<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Payroll as PayrollConfig;

/**
 * Attendance-driven payroll.
 *
 *   iclock_transaction ──(AttendanceCalculator)──▶ monthly totals
 *        + payroll_salarystructure (base salary)
 *        + payroll_emploan (loan installments)
 *        + payroll_salaryadvance (advances)
 *        ─────────────────────────────────────────▶ payroll_payrollpayload (payslip)
 *
 * A "run" recomputes and replaces the payload rows for a month. Nothing
 * outside payroll_* / att_* is touched.
 */
class PayrollCalculator
{
    private BaseConnection $db;
    private PayrollConfig $cfg;
    private AttendanceCalculator $att;

    public function __construct(?BaseConnection $db = null, ?PayrollConfig $cfg = null)
    {
        $this->db  = $db ?? db_connect();
        $this->cfg = $cfg ?? config('Payroll');
        $this->att = new AttendanceCalculator($this->db);
    }

    // ------------------------------------------------------------------
    //  Public API
    // ------------------------------------------------------------------

    /**
     * Compute one employee's payslip for a month WITHOUT persisting it.
     *
     * @return array<string,mixed>
     */
    public function compute(int $empId, int $year, int $month): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $emp     = $this->att->employee($empId);
        $monthly = $this->att->monthly($empId, $year, $month);
        $t       = $monthly['totals'];

        $structure   = $this->activeSalaryStructure($empId, $monthEnd);
        $base        = $structure ? (float) $structure['salary_amount'] : 0.0;
        $structureId = $structure['id'] ?? null;
        $dayRate  = $this->cfg->standardMonthlyDays > 0 ? $base / $this->cfg->standardMonthlyDays : 0.0;
        $hourRate = $this->cfg->standardDailyHours > 0 ? $dayRate / $this->cfg->standardDailyHours : 0.0;

        // Overtime priced per kind — normal / weekend / holiday can carry
        // different multipliers; falls back to the blended one.
        $otNormalH  = round(($t['ot_normal']  ?? $t['ot_minutes']) / 60, 2);
        $otWeekendH = round(($t['ot_weekend'] ?? 0) / 60, 2);
        $otHolidayH = round(($t['ot_holiday'] ?? 0) / 60, 2);
        $otHours    = round($t['ot_minutes'] / 60, 2);
        $otPay      = round(
            $otNormalH  * $hourRate * $this->cfg->overtimeMultiplier
          + $otWeekendH * $hourRate * $this->cfg->weekendOtMultiplier
          + $otHolidayH * $hourRate * $this->cfg->holidayOtMultiplier,
            2
        );

        $absentDays      = $t['absent_days'];
        $absenceDeduction = $this->cfg->deductAbsentDays ? round($absentDays * $dayRate, 2) : 0.0;

        // Unpaid leave (att_paycode.is_paid = false) is deducted at the day rate.
        $unpaidLeaveDays      = $t['unpaid_leave_days'] ?? 0.0;
        $unpaidLeaveDeduction = round($unpaidLeaveDays * $dayRate, 2);

        $lateHours      = round($t['late_minutes'] / 60, 2);
        $lateDeduction  = $this->cfg->deductLateMinutes ? round($lateHours * $hourRate, 2) : 0.0;

        // ---- opt-in formula engine (Payroll Setup ▸ Payslip Settings) ----
        // When base_sysparam(payroll, use_formulas) = 1, each formula type is
        // priced by whichever payroll_*formula rows are ATTACHED TO this
        // employee's active salary structure (payroll_salarystructure_*formula
        // — ZKBioTime scopes formulas per structure, not globally). Overtime
        // and exception formulas REPLACE the flat-rate figures above; leave
        // formula replaces the unpaid-leave deduction the same way; deduction
        // and increasement formulas have no flat-rate equivalent, so they ADD
        // a separate line.
        $formulaIncrease = 0.0;
        $formulaDeduction = 0.0;
        if ($this->useFormulas() && $structureId !== null) {
            $ctx = [
                'Basic Salary' => $base, 'NOT' => $otNormalH, 'WOT' => $otWeekendH, 'HOT' => $otHolidayH,
                'L' => $t['late_minutes'], 'E' => $t['early_minutes'], 'A' => $absentDays,
                'Present' => $t['present_days'], 'Leave' => $t['leave_days'] ?? 0,
                'WorkDays' => $this->cfg->standardMonthlyDays,
            ];
            $fOt = $this->sumFormulas('overtime', $structureId, $ctx);
            if ($fOt !== null) {
                $otPay = round($fOt, 2);
            }
            $fEx = $this->sumFormulas('exception', $structureId, $ctx);
            if ($fEx !== null) {
                $lateDeduction = round($fEx, 2);
            }
            $fLeave = $this->sumFormulas('leave', $structureId, $ctx);
            if ($fLeave !== null) {
                $unpaidLeaveDeduction = round($fLeave, 2);
            }
            $fInc = $this->sumFormulas('increasement', $structureId, $ctx);
            if ($fInc !== null) {
                $formulaIncrease = round($fInc, 2);
            }
            $fDed = $this->sumFormulas('deduction', $structureId, $ctx);
            if ($fDed !== null) {
                $formulaDeduction = round($fDed, 2);
            }
        }

        $loans     = $this->activeLoanInstallment($empId, $monthEnd);
        $advances  = $this->advancesForMonth($empId, $year, $month);

        // One-off adjustments issued within the month: extra increases and
        // reimbursements add to pay; extra deductions subtract.
        $adj            = $this->adjustmentsForMonth($empId, $year, $month);
        $extraIncrease  = round($adj['increase'] + $adj['reimbursement'], 2);
        $extraDeduction = round($adj['deduction'], 2);

        $grossAdd  = round($otPay + $extraIncrease + $formulaIncrease, 2);
        $deductions = round(
            $absenceDeduction + $unpaidLeaveDeduction + $lateDeduction + $extraDeduction + $formulaDeduction
          + $loans['installment'] + $advances['total'],
            2
        );
        $net        = round($base + $grossAdd - $deductions, 2);

        return [
            'employee'    => $emp,
            'employee_id' => $empId,
            'year'        => $year,
            'month'       => $month,
            'month_start' => $monthStart,
            'month_end'   => $monthEnd,
            'base_salary' => round($base, 2),
            'day_rate'    => round($dayRate, 2),
            'hour_rate'   => round($hourRate, 2),

            'worked_days'   => $t['present_days'],
            'absent_days'   => $absentDays,
            'leave_days'    => $t['leave_days'],
            'paid_leave_days'   => $t['paid_leave_days'] ?? 0.0,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'incomplete_days'   => $t['incomplete_days'] ?? 0,
            'holiday_days'  => $t['holiday_days'],
            'weekend_days'  => $t['weekend_days'],
            'late_minutes'  => $t['late_minutes'],
            'ot_minutes'    => $t['ot_minutes'],
            'ot_hours'      => $otHours,
            'ot_normal_hours'  => $otNormalH,
            'ot_weekend_hours' => $otWeekendH,
            'ot_holiday_hours' => $otHolidayH,
            'worked_minutes'=> $t['worked_minutes'],

            'earnings' => array_values(array_filter(array_merge([
                ['label' => 'Basic salary', 'amount' => round($base, 2)],
                $otPay > 0 ? ['label' => 'Overtime (' . $otHours . ' h)', 'amount' => $otPay] : null,
                $formulaIncrease > 0 ? ['label' => 'Formula increase', 'amount' => $formulaIncrease] : null,
                $loans['advance_credit'] > 0 ? ['label' => 'Loan disbursement', 'amount' => $loans['advance_credit']] : null,
            ], $adj['lines_earn']))),
            'deductions' => array_values(array_filter(array_merge([
                $absenceDeduction > 0 ? ['label' => $absentDays . ' absent day(s)', 'amount' => $absenceDeduction] : null,
                $unpaidLeaveDeduction > 0 ? ['label' => 'Unpaid leave (' . $unpaidLeaveDays . ' d)', 'amount' => $unpaidLeaveDeduction] : null,
                $lateDeduction > 0 ? ['label' => 'Late (' . $lateHours . ' h)', 'amount' => $lateDeduction] : null,
                $formulaDeduction > 0 ? ['label' => 'Formula deduction', 'amount' => $formulaDeduction] : null,
                $loans['installment'] > 0 ? ['label' => 'Loan installment (' . $loans['count'] . ')', 'amount' => $loans['installment']] : null,
                $advances['total'] > 0 ? ['label' => 'Salary advance', 'amount' => $advances['total']] : null,
            ], $adj['lines_ded']))),

            'gross'          => round($base + $grossAdd, 2),
            'total_earnings' => round($base + $grossAdd, 2),
            'total_deductions' => $deductions,
            'ot_pay'          => $otPay,
            'extra_increase'  => $extraIncrease,
            'extra_deduction' => $extraDeduction,
            'formula_increase' => $formulaIncrease,
            'formula_deduction' => $formulaDeduction,
            'reimbursement'   => round($adj['reimbursement'], 2),
            'absence_deduction' => $absenceDeduction,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,
            'late_deduction'  => $lateDeduction,
            'loan_deduction'  => $loans['installment'],
            'advance_deduction' => $advances['total'],
            'net_pay'         => $net,
        ];
    }

    /**
     * Recompute + persist payslips for a whole month.
     * Existing payloads for that month/those employees are replaced.
     *
     * @return array{count:int, net:float, rows:array<int,array>}
     */
    public function run(int $year, int $month, ?int $deptId = null): array
    {
        $employees = $this->att->employees($deptId);
        $rows      = [];
        $totalNet  = 0.0;

        $this->db->transStart();
        foreach ($employees as $emp) {
            $slip = $this->compute((int) $emp['id'], $year, $month);
            $this->persist($slip);
            $rows[]   = $slip;
            $totalNet += $slip['net_pay'];
        }
        $this->db->transComplete();

        return ['count' => count($rows), 'net' => round($totalNet, 2), 'rows' => $rows];
    }

    /** Stored payslips for a month (reads payroll_payrollpayload). */
    public function slips(int $year, int $month, ?int $deptId = null): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);

        $b = $this->db->table('payroll_payrollpayload p')
            ->select("p.*, e.emp_code,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                      e.first_name, e.last_name, d.dept_name")
            ->join('personnel_employee e', 'e.id = p.employee_id')
            ->join('personnel_department d', 'd.id = e.department_id', 'left')
            ->where('p.calc_time', $monthStart)
            ->orderBy('e.emp_code_digit', 'ASC');
        if ($deptId) {
            $b->where('e.department_id', $deptId);
        }

        return $b->get()->getResultArray();
    }

    public function slip(int $empId, int $year, int $month): ?array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $row = $this->db->table('payroll_payrollpayload')
            ->where('employee_id', $empId)->where('calc_time', $monthStart)
            ->get()->getRowArray();

        return $row ?: null;
    }

    // ------------------------------------------------------------------
    //  Persistence
    // ------------------------------------------------------------------

    private function persist(array $s): void
    {
        $this->db->table('payroll_payrollpayload')
            ->where('employee_id', $s['employee_id'])
            ->where('calc_time', $s['month_start'])
            ->delete();

        $this->db->table('payroll_payrollpayload')->insert([
            'employee_id'      => $s['employee_id'],
            'calc_time'        => $s['month_start'],
            'calc_end_time'    => $s['month_end'],
            'effective_date'   => date('Y-m-d'),
            'basic_salary'     => $s['base_salary'],
            'payment_mode'     => $this->cfg->defaultPaymentMode,
            'increase'         => $s['ot_pay'],
            'deduction'        => round($s['absence_deduction'] + $s['unpaid_leave_deduction'] + $s['late_deduction'], 2),
            'extra_increase'   => round(($s['extra_increase'] ?? 0) + ($s['formula_increase'] ?? 0), 2),
            'extra_deduction'  => round(($s['extra_deduction'] ?? 0) + ($s['formula_deduction'] ?? 0), 2),
            'loan_deduction'   => $s['loan_deduction'],
            'advance_deduction'=> $s['advance_deduction'],
            'total_increase'   => round($s['total_earnings'] - $s['base_salary'], 2),
            'total_deduction'  => $s['total_deductions'],
            'total_salary'     => $s['gross'],
            'net_pay'          => $s['net_pay'],
            'format_dict'      => json_encode([
                'worked_days'  => $s['worked_days'],
                'absent_days'  => $s['absent_days'],
                'leave_days'   => $s['leave_days'],
                'ot_hours'     => $s['ot_hours'],
                'late_minutes' => $s['late_minutes'],
                'day_rate'     => $s['day_rate'],
                'hour_rate'    => $s['hour_rate'],
                'earnings'     => $s['earnings'],
                'deductions'   => $s['deductions'],
                'generated_at' => date('c'),
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    //  Inputs
    // ------------------------------------------------------------------

    /** The salary structure row in effect on $onDate (latest effective_date ≤ onDate). */
    private function activeSalaryStructure(int $empId, string $onDate): ?array
    {
        return $this->db->table('payroll_salarystructure')
            ->select('id, salary_amount')
            ->where('employee_id', $empId)
            ->where('effective_date <=', $onDate)
            ->where('status', 0)
            ->orderBy('effective_date', 'DESC')
            ->get(1)->getRowArray() ?: null;
    }

    /**
     * @return array{installment:float,count:int,advance_credit:float}
     */
    private function activeLoanInstallment(int $empId, string $monthEnd): array
    {
        $loans = $this->db->table('payroll_emploan')
            ->where('employee_id', $empId)
            ->where('status', 0)
            ->get()->getResultArray();

        $installment = 0.0;
        $count       = 0;
        foreach ($loans as $l) {
            $loanDate = $l['loan_time'] !== null ? substr((string) $l['loan_time'], 0, 10) : null;
            if ($loanDate !== null && $loanDate > $monthEnd) {
                continue; // not disbursed yet
            }
            if ($l['loan_clean_time'] !== null && substr((string) $l['loan_clean_time'], 0, 10) < $monthEnd) {
                continue; // manually settled early
            }

            $perCycle = (float) $l['per_cycle_refund'];
            $amount   = (float) $l['loan_amount'];
            $cycles   = max(1, (int) $l['refund_cycle']);

            // which installment does this month represent? (1 = disbursement month)
            $n = 1;
            if ($loanDate !== null) {
                $ly = (int) substr($loanDate, 0, 4);
                $lm = (int) substr($loanDate, 5, 2);
                $my = (int) substr($monthEnd, 0, 4);
                $mm = (int) substr($monthEnd, 5, 2);
                $n  = ($my - $ly) * 12 + ($mm - $lm) + 1;
            }
            if ($n < 1 || $n > $cycles) {
                continue; // loan term finished (or future)
            }

            $alreadyPaid = min($amount, $perCycle * ($n - 1));
            $due         = round(min($perCycle, $amount - $alreadyPaid), 2);
            if ($due <= 0) {
                continue; // fully repaid
            }
            $installment += $due;
            $count++;
        }

        return ['installment' => round($installment, 2), 'count' => $count, 'advance_credit' => 0.0];
    }

    /**
     * One-off payroll adjustments whose issue date falls inside the month.
     *
     *   payroll_extraincrease  → earnings   (bonus, allowance, back-pay)
     *   payroll_reimbursement  → earnings   (expense claims)
     *   payroll_extradeduction → deductions (fines, recovery, other)
     *
     * @return array{increase:float,deduction:float,reimbursement:float,lines_earn:array,lines_ded:array}
     */
    private function adjustmentsForMonth(int $empId, int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start)) . ' 23:59:59';

        $inc = $this->db->table('payroll_extraincrease ei')
            ->select('ei.amount, ei.remark, sp.name AS type_name')
            ->join('payroll_specialpayment sp', 'sp.id = ei.special_type_id', 'left')
            ->where('ei.employee_id', $empId)->where('ei.status', 0)
            ->where('ei.issued_time >=', $start)->where('ei.issued_time <=', $end)
            ->get()->getResultArray();

        $ded = $this->db->table('payroll_extradeduction ed')
            ->select('ed.amount, ed.remark, sp.name AS type_name')
            ->join('payroll_specialpayment sp', 'sp.id = ed.special_type_id', 'left')
            ->where('ed.employee_id', $empId)->where('ed.status', 0)
            ->where('ed.issued_time >=', $start)->where('ed.issued_time <=', $end)
            ->get()->getResultArray();

        $rmb = $this->db->table('payroll_reimbursement')
            ->select('rmb_amount, rmb_remark')
            ->where('employee_id', $empId)
            ->where('rmb_time >=', $start)->where('rmb_time <=', $end)
            ->get()->getResultArray();

        $linesEarn = [];
        $incTotal  = 0.0;
        foreach ($inc as $r) {
            $incTotal   += (float) $r['amount'];
            $linesEarn[] = ['label' => $r['type_name'] ?: ($r['remark'] ?: 'Extra increase'), 'amount' => round((float) $r['amount'], 2)];
        }
        $rmbTotal = 0.0;
        foreach ($rmb as $r) {
            $rmbTotal   += (float) $r['rmb_amount'];
            $linesEarn[] = ['label' => $r['rmb_remark'] ? 'Reimbursement — ' . $r['rmb_remark'] : 'Reimbursement', 'amount' => round((float) $r['rmb_amount'], 2)];
        }
        $linesDed = [];
        $dedTotal = 0.0;
        foreach ($ded as $r) {
            $dedTotal  += (float) $r['amount'];
            $linesDed[] = ['label' => $r['type_name'] ?: ($r['remark'] ?: 'Extra deduction'), 'amount' => round((float) $r['amount'], 2)];
        }

        return [
            'increase'      => round($incTotal, 2),
            'deduction'     => round($dedTotal, 2),
            'reimbursement' => round($rmbTotal, 2),
            'lines_earn'    => $linesEarn,
            'lines_ded'     => $linesDed,
        ];
    }

    /** @return array{total:float,count:int} */
    /** Payroll Setup toggle: price OT/exception lines with formulas? */
    private function useFormulas(): bool
    {
        $row = $this->db->table('base_sysparam')->where('para_type', 'payroll')->where('para_name', 'use_formulas')->get()->getRowArray();

        return (int) ($row['para_value'] ?? 0) === 1;
    }

    /**
     * Evaluate every $type formula ATTACHED TO $salaryStructureId (ZKBioTime
     * scopes each formula to specific salary structures via the
     * payroll_salarystructure_{type}formula join table — an admin sets up
     * different formulas for different structures, they are not global) and
     * sum the results. Null when none are attached, so the caller can fall
     * back to its flat-rate calculation.
     *
     * @param string $type one of: overtime, exception, deduction, leave, increasement
     */
    private function sumFormulas(string $type, int $salaryStructureId, array $ctx): ?float
    {
        $fk = "{$type}formula_id";
        $rows = $this->db->table("payroll_{$type}formula f")
            ->select('f.formula')
            ->join("payroll_salarystructure_{$type}formula j", "j.{$fk} = f.id")
            ->where('j.salarystructure_id', $salaryStructureId)
            ->get()->getResultArray();
        if ($rows === []) {
            return null;
        }
        $sum = 0.0;
        foreach ($rows as $r) {
            $v = \App\Libraries\FormulaEval::run((string) $r['formula'], $ctx);
            if ($v !== null) {
                $sum += $v;
            }
        }

        return $sum;
    }

    private function advancesForMonth(int $empId, int $year, int $month): array
    {
        $ym  = sprintf('%04d-%02d', $year, $month);
        $rows = $this->db->table('payroll_salaryadvance')
            ->where('employee_id', $empId)
            ->get()->getResultArray();

        $total = 0.0;
        $count = 0;
        foreach ($rows as $r) {
            if (substr((string) $r['advance_time'], 0, 7) === $ym) {
                $total += (float) $r['advance_amount'];
                $count++;
            }
        }

        return ['total' => round($total, 2), 'count' => $count];
    }
}
