<?php

namespace App\Commands;

use App\Libraries\PayrollCalculator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Dev helper: compute (not persist) one employee's payslip for a month so
 * PayrollCalculator changes can be eyeballed.
 *
 *   php spark payroll:check <empId> <year> <month>
 */
class PayrollCheck extends BaseCommand
{
    protected $group       = 'Payroll';
    protected $name        = 'payroll:check';
    protected $description  = 'Print a computed (unsaved) payslip for an employee/month.';
    protected $usage        = 'payroll:check [empId] [year] [month]';

    public function run(array $params)
    {
        $empId = (int) ($params[0] ?? 0);
        $year  = (int) ($params[1] ?? date('Y'));
        $month = (int) ($params[2] ?? date('n'));
        if ($empId === 0) {
            CLI::error('Usage: php spark payroll:check <empId> <year> <month>');

            return;
        }

        $s = (new PayrollCalculator())->compute($empId, $year, $month);
        CLI::write('Payslip · #' . ($s['employee']['emp_code'] ?? '?') . ' ' . ($s['employee']['name'] ?? '')
            . '  ' . sprintf('%04d-%02d', $year, $month), 'green');
        CLI::newLine();

        foreach ([
            'base_salary', 'day_rate', 'hour_rate',
            'worked_days', 'absent_days', 'paid_leave_days', 'unpaid_leave_days', 'incomplete_days',
            'ot_normal_hours', 'ot_weekend_hours', 'ot_holiday_hours', 'ot_pay',
            'absence_deduction', 'unpaid_leave_deduction', 'late_deduction',
            'loan_deduction', 'advance_deduction',
            'total_earnings', 'total_deductions', 'net_pay',
        ] as $k) {
            CLI::write('  ' . str_pad($k, 24) . (is_array($s[$k] ?? null) ? json_encode($s[$k]) : ($s[$k] ?? '')));
        }

        CLI::newLine();
        CLI::write('Earnings:', 'yellow');
        foreach ($s['earnings'] as $e) {
            CLI::write('  ' . str_pad($e['label'], 32) . number_format($e['amount'], 2));
        }
        CLI::write('Deductions:', 'yellow');
        foreach ($s['deductions'] as $d) {
            CLI::write('  ' . str_pad($d['label'], 32) . number_format($d['amount'], 2));
        }
    }
}
