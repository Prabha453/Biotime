<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Payroll settings.
 *
 * The payroll run is *attendance-driven*: it takes the monthly totals the
 * AttendanceCalculator derives from raw biometric punches (worked days,
 * absent days, late minutes, overtime minutes) and turns them into a
 * payslip stored in ZKBioTime's own `payroll_payrollpayload` table.
 *
 * Base salary comes from `payroll_salarystructure` (effective-dated),
 * loans from `payroll_emploan`, one-off advances from
 * `payroll_salaryadvance`, bank details from `payroll_emppayrollprofile`.
 */
class Payroll extends BaseConfig
{
    /** Currency symbol shown on payslips / lists. */
    public string $currency = 'AED';

    /**
     * Divisor that turns a monthly salary into a day rate:
     *   dayRate = baseSalary / standardMonthlyDays
     * 26 = the common "30 days minus ~4 weekly offs" convention.
     */
    public int $standardMonthlyDays = 26;

    /** Hours in a standard working day (dayRate / this = hourRate). */
    public float $standardDailyHours = 8.0;

    /** Deduct a day's pay for every "Absent" day in the register. */
    public bool $deductAbsentDays = true;

    /** Deduct pro-rata pay for accumulated late minutes. */
    public bool $deductLateMinutes = false;

    /**
     * Overtime multipliers. The calculator now splits OT minutes into
     * normal / weekend / holiday buckets so each can be priced separately:
     *   otPay = Σ  otHours[kind] * hourRate * multiplier[kind]
     */
    public float $overtimeMultiplier   = 1.25;  // normal weekday OT
    public float $weekendOtMultiplier  = 1.5;
    public float $holidayOtMultiplier  = 2.0;

    /**
     * Payment mode written to payroll_payrollpayload.payment_mode
     * (1 = bank transfer, 2 = cash, 3 = cheque — ZKBioTime convention).
     */
    public int $defaultPaymentMode = 1;
}
