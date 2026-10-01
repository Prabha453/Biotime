<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;
use App\Libraries\Export;

/**
 * ZKBioTime's "Monthly Report" group: Status · Check-in&out · Worked Hrs ·
 * Overtime · Absence — five separate pages, matching the real app's own
 * menu and the exact column layout of its Excel export (verified against
 * live exports: Monthly Status/Check-in&out/Worked Hrs/Overtime/Absence).
 *
 * All five share one per-employee/per-day computation (rows()) built
 * directly from AttendanceCalculator::forEmployee() — the same
 * pay-code accumulator logic Reports::timeCardValue() uses for the Total
 * Time Card report (REG/L/E/A/NOT/WOT/HOT/OT1-3 + one column per
 * configured leave-type att_paycode), just summed across the whole month
 * instead of shown per day.
 */
class MonthlyReports extends BaseController
{
    /** ?y= / ?m= / ?dept= — defaults to the current month (every module defaults to today/this month). */
    private function period(): array
    {
        $year  = (int) ($this->request->getGet('y') ?: date('Y'));
        $month = (int) ($this->request->getGet('m') ?: date('n'));
        $dept  = (int) $this->request->getGet('dept') ?: null;

        return [$year, $month, $dept];
    }

    /**
     * Same mechanism as Total Time Card's export: the Columns button
     * (tt-grid.js) appends ?cols= (a comma list of data-col keys) to every
     * export link before it navigates, so a hidden column is left out of
     * the download too. $colDefs is [key => label] in display order;
     * returns the subset requested, or everything when cols is absent,
     * empty, or matches nothing (e.g. a stale link from before the report
     * changed).
     */
    private function filterCols(array $colDefs, string $requested): array
    {
        $want = array_filter(array_map('trim', explode(',', $requested)));
        if ($want === []) {
            return $colDefs;
        }
        $want     = array_flip($want);
        $filtered = array_intersect_key($colDefs, $want);

        return $filtered === [] ? $colDefs : $filtered;
    }

    /**
     * One row per employee: raw per-day data (for the Status/Check-in&out/
     * Worked Hrs/Overtime day columns) + a monthly pay-code accumulator
     * (for every report's summary columns) + present/absent/holiday day
     * counts (for Absence).
     */
    private function rows(int $year, int $month, ?int $dept): array
    {
        $db   = db_connect();
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $dim  = (int) date('t', strtotime($from));

        $payCodes = $db->query('SELECT code, name, display_format FROM att_paycode ORDER BY display_order, id')->getResultArray();
        $calc     = new AttendanceCalculator();

        // One batched pass for every employee — forRange() fetches
        // punches/holidays/leaves ONCE total. Looping forEmployee() per
        // employee instead would re-run all three queries per employee
        // (83 employees = 83x the round trips for identical holiday data).
        $byEmp = [];
        foreach ($calc->forRange([], $from, $to, $dept) as $d) {
            $byEmp[(int) $d['employee']['id']][(int) substr($d['date'], 8, 2)] = $d;
        }

        $rows = [];
        foreach ($calc->employees($dept) as $e) {
            $byDay = $byEmp[(int) $e['id']] ?? [];
            $pc    = array_fill_keys(array_map(static fn ($p) => strtoupper($p['code']), $payCodes), 0);
            $present = $absentDays = $holidayDays = 0;
            foreach ($byDay as $d) {
                $kind      = $d['ot_kind'] ?? 'normal';
                $onLeave   = ! empty($d['on_leave']);
                $leaveName = (string) ($d['leave_type'] ?? '');
                foreach ($payCodes as $p) {
                    $code = strtoupper($p['code']);
                    $min  = match ($code) {
                        'REG'                     => $d['regular_minutes'] ?? 0,
                        'L'                        => $d['late_minutes'] ?? 0,
                        'E'                        => $d['early_minutes'] ?? 0,
                        'A'                        => $d['absent_minutes'] ?? 0,
                        'NOT'                      => $kind === 'normal'  ? ($d['ot_minutes'] ?? 0) : 0,
                        'WOT'                      => $kind === 'weekend' ? ($d['ot_minutes'] ?? 0) : 0,
                        'HOT'                      => $kind === 'holiday' ? ($d['ot_minutes'] ?? 0) : 0,
                        'OT1', 'OT2', 'OT3', 'CP'  => 0,
                        default                    => ($onLeave && $leaveName !== '' && strcasecmp($p['name'], $leaveName) === 0) ? ($d['leave_minutes'] ?? 0) : 0,
                    };
                    $pc[$code] += $min;
                }
                if (AttendanceCalculator::isPresent($d['status'])) {
                    $present++;
                }
                if ($d['status'] === 'Absent') {
                    $absentDays++;
                }
                if (! empty($d['is_holiday'])) {
                    $holidayDays++;
                }
            }
            $rows[] = [
                'emp'         => $e,
                'days'        => $byDay,
                'pc'          => $pc,
                'present'     => $present,
                'absentDays'  => $absentDays,
                'holidayDays' => $holidayDays,
            ];
        }

        return ['from' => $from, 'to' => $to, 'dim' => $dim, 'rows' => $rows, 'payCodes' => $payCodes];
    }

    /** M-unit paycodes (Late In) show raw minutes; every other is hours. */
    private function payUnit(array $payCodes, string $code): string
    {
        foreach ($payCodes as $p) {
            if (strcasecmp($p['code'], $code) === 0) {
                return (int) $p['display_format'] === 1 ? 'M' : 'H';
            }
        }

        return 'H';
    }

    private function payLabel(array $payCodes, string $code): string
    {
        foreach ($payCodes as $p) {
            if (strcasecmp($p['code'], $code) === 0) {
                return $p['name'];
            }
        }

        return $code;
    }

    /** A pay-code minute total, formatted for the summary columns — always a number (never blank), 1 decimal. */
    private function pcVal(array $data, string $code): string
    {
        $min  = (int) ($data['pc'][$code] ?? 0);
        $unit = $this->unitFor($code, $data['payCodes']);

        return $unit === 'M' ? number_format($min, 1) : number_format($min / 60, 1);
    }

    /**
     * ZKBioTime always shows Late In in minutes and every other fixed
     * summary column (including Early Out) in hours, regardless of that
     * pay code's own display_format — only the configured leave-type
     * columns take their unit from att_paycode.
     */
    private function unitFor(string $code, array $payCodes): string
    {
        $fixedUnits = ['REG' => 'H', 'L' => 'M', 'E' => 'H', 'A' => 'H', 'NOT' => 'H', 'WOT' => 'H', 'HOT' => 'H', 'OT1' => 'H', 'OT2' => 'H', 'OT3' => 'H'];

        return $fixedUnits[$code] ?? $this->payUnit($payCodes, $code);
    }

    /** ZKBioTime's Monthly Status letter code: base P/A/H/W/V + Late/Early flags. */
    private function statusCode(array $d): string
    {
        return match ($d['status']) {
            'Leave', 'Half-day Leave' => 'V',
            'Holiday'                 => 'H',
            'Weekend'                 => 'W',
            'Absent'                  => 'A',
            'Late'                    => 'P L',
            'Early Out'               => 'P E',
            'Late & Early'            => 'P L E',
            'No Data'                 => '',
            default                   => $d['punch_count'] > 0 ? 'P' : '',
        };
    }

    private function inOutCode(array $d): string
    {
        $in  = (string) ($d['first_in'] ?? '');
        $out = (string) ($d['last_out'] ?? '');
        if ($in !== '' && $out !== '') {
            return $in === $out ? $in : "{$in}-{$out}";
        }

        return $in !== '' ? $in : $out;
    }

    private function hoursCode(array $d, string $field): string
    {
        $min = (int) ($d[$field] ?? 0);

        return $min > 0 ? number_format($min / 60, 1) : '';
    }

    // ------------------------------------------------------------------
    //  Monthly Status — one letter-code cell per day (P/A/L/E/H/W/V).
    // ------------------------------------------------------------------

    public function status(): string
    {
        [$year, $month, $dept] = $this->period();
        $data = $this->rows($year, $month, $dept);
        [$pageRows, $pager] = $this->pagedArray($data['rows'], 25);

        return view('admin/reports/monthly_status', [
            'title' => 'Monthly Status', 'subtitle' => date('F Y', strtotime($data['from'])),
            'active' => 'reports.monthlyStatus',
            'year' => $year, 'month' => $month, 'dept' => $dept,
            'dim' => $data['dim'], 'rows' => $pageRows, 'pager' => $pager,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
            'codeOf' => fn (array $d) => $this->statusCode($d),
        ]);
    }

    public function statusExport()
    {
        [$year, $month, $dept] = $this->period();
        $data   = $this->rows($year, $month, $dept);
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';

        $colDefs = ['emp_id' => 'Employee ID', 'name' => 'First Name', 'dept' => 'Department'];
        for ($i = 1; $i <= $data['dim']; $i++) {
            $colDefs['d' . $i] = sprintf('%02d', $i);
        }
        $colDefs = $this->filterCols($colDefs, (string) $this->request->getGet('cols'));
        $keys    = array_keys($colDefs);

        $out = [];
        foreach ($data['rows'] as $r) {
            $full = ['emp_id' => $r['emp']['emp_code'], 'name' => emp_name($r['emp']), 'dept' => $r['emp']['dept_name'] ?? ''];
            for ($i = 1; $i <= $data['dim']; $i++) {
                $full['d' . $i] = isset($r['days'][$i]) ? $this->statusCode($r['days'][$i]) : '';
            }
            $out[] = array_map(static fn ($k) => $full[$k], $keys);
        }
        $this->audit('export.monthly_status', count($out) . " rows ({$format})");

        return Export::stream($format, 'monthly_status_' . date('Ymd_His'), 'Monthly Status', array_values($colDefs), $out, [
            'title' => 'Monthly Status', 'meta' => date('F Y', strtotime($data['from'])), 'freeze' => true,
        ]);
    }

    // ------------------------------------------------------------------
    //  Monthly Check-in&out — punch times per day + the full pay-code summary.
    // ------------------------------------------------------------------

    private function checkInOutColumns(): array
    {
        return ['REG' => 'Regular', 'L' => 'Late In', 'E' => 'Early Out', 'A' => 'Absence',
            'NOT' => 'Normal OT', 'WOT' => 'Weekend OT', 'HOT' => 'Holiday OT', 'OT1' => 'OT1', 'OT2' => 'OT2', 'OT3' => 'OT3'];
    }

    public function checkInOut(): string
    {
        [$year, $month, $dept] = $this->period();
        $data = $this->rows($year, $month, $dept);
        $summaryCodes = array_merge(array_keys($this->checkInOutColumns()), $this->leaveCodes($data['payCodes']));
        [$pageRows, $pager] = $this->pagedArray($data['rows'], 25);

        return view('admin/reports/monthly_checkinout', [
            'title' => 'Monthly Check-in&out', 'subtitle' => date('F Y', strtotime($data['from'])),
            'active' => 'reports.monthlyCheckInOut',
            'year' => $year, 'month' => $month, 'dept' => $dept,
            'dim' => $data['dim'], 'rows' => $pageRows, 'pager' => $pager, 'payCodes' => $data['payCodes'],
            'summaryCodes' => $summaryCodes,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
            'cellOf'  => fn (array $d) => $this->inOutCode($d),
            'labelOf' => fn (string $c) => $this->summaryLabel($c, $data['payCodes']),
            'valOf'   => fn (array $row, string $c) => $this->pcVal(['pc' => $row['pc'], 'payCodes' => $data['payCodes']], $c),
        ]);
    }

    public function checkInOutExport()
    {
        [$year, $month, $dept] = $this->period();
        $data   = $this->rows($year, $month, $dept);
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';
        $summaryCodes = array_merge(array_keys($this->checkInOutColumns()), $this->leaveCodes($data['payCodes']));

        $colDefs = ['emp_id' => 'Employee ID', 'name' => 'First Name', 'dept' => 'Department'];
        for ($i = 1; $i <= $data['dim']; $i++) {
            $colDefs['d' . $i] = sprintf('%02d', $i);
        }
        foreach ($summaryCodes as $c) {
            $colDefs['pc_' . $c] = $this->summaryLabel($c, $data['payCodes']);
        }
        $colDefs = $this->filterCols($colDefs, (string) $this->request->getGet('cols'));
        $keys    = array_keys($colDefs);

        $out = [];
        foreach ($data['rows'] as $r) {
            $full = ['emp_id' => $r['emp']['emp_code'], 'name' => emp_name($r['emp']), 'dept' => $r['emp']['dept_name'] ?? ''];
            for ($i = 1; $i <= $data['dim']; $i++) {
                $full['d' . $i] = isset($r['days'][$i]) ? $this->inOutCode($r['days'][$i]) : '';
            }
            foreach ($summaryCodes as $c) {
                $full['pc_' . $c] = $this->pcVal(['pc' => $r['pc'], 'payCodes' => $data['payCodes']], $c);
            }
            $out[] = array_map(static fn ($k) => $full[$k], $keys);
        }
        $this->audit('export.monthly_checkinout', count($out) . " rows ({$format})");

        return Export::stream($format, 'monthly_checkinout_' . date('Ymd_His'), 'Monthly Check-in&out', array_values($colDefs), $out, [
            'title' => 'Monthly Check-in&out', 'meta' => date('F Y', strtotime($data['from'])), 'freeze' => true,
        ]);
    }

    // ------------------------------------------------------------------
    //  Monthly Worked Hrs — worked hours per day + the same pay-code summary.
    // ------------------------------------------------------------------

    public function workedHrs(): string
    {
        [$year, $month, $dept] = $this->period();
        $data = $this->rows($year, $month, $dept);
        $summaryCodes = array_merge(array_keys($this->checkInOutColumns()), $this->leaveCodes($data['payCodes']));
        [$pageRows, $pager] = $this->pagedArray($data['rows'], 25);

        return view('admin/reports/monthly_worked', [
            'title' => 'Monthly Worked Hrs', 'subtitle' => date('F Y', strtotime($data['from'])),
            'active' => 'reports.monthlyWorkedHrs',
            'year' => $year, 'month' => $month, 'dept' => $dept,
            'dim' => $data['dim'], 'rows' => $pageRows, 'pager' => $pager, 'payCodes' => $data['payCodes'],
            'summaryCodes' => $summaryCodes,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
            'cellOf'  => fn (array $d) => $this->hoursCode($d, 'worked_minutes'),
            'labelOf' => fn (string $c) => $this->summaryLabel($c, $data['payCodes']),
            'valOf'   => fn (array $row, string $c) => $this->pcVal(['pc' => $row['pc'], 'payCodes' => $data['payCodes']], $c),
        ]);
    }

    public function workedHrsExport()
    {
        [$year, $month, $dept] = $this->period();
        $data   = $this->rows($year, $month, $dept);
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';
        $summaryCodes = array_merge(array_keys($this->checkInOutColumns()), $this->leaveCodes($data['payCodes']));

        $colDefs = ['emp_id' => 'Employee ID', 'name' => 'First Name', 'dept' => 'Department'];
        for ($i = 1; $i <= $data['dim']; $i++) {
            $colDefs['d' . $i] = sprintf('%02d', $i);
        }
        foreach ($summaryCodes as $c) {
            $colDefs['pc_' . $c] = $this->summaryLabel($c, $data['payCodes']);
        }
        $colDefs = $this->filterCols($colDefs, (string) $this->request->getGet('cols'));
        $keys    = array_keys($colDefs);

        $out = [];
        foreach ($data['rows'] as $r) {
            $full = ['emp_id' => $r['emp']['emp_code'], 'name' => emp_name($r['emp']), 'dept' => $r['emp']['dept_name'] ?? ''];
            for ($i = 1; $i <= $data['dim']; $i++) {
                $full['d' . $i] = isset($r['days'][$i]) ? $this->hoursCode($r['days'][$i], 'worked_minutes') : '';
            }
            foreach ($summaryCodes as $c) {
                $full['pc_' . $c] = $this->pcVal(['pc' => $r['pc'], 'payCodes' => $data['payCodes']], $c);
            }
            $out[] = array_map(static fn ($k) => $full[$k], $keys);
        }
        $this->audit('export.monthly_worked', count($out) . " rows ({$format})");

        return Export::stream($format, 'monthly_worked_hrs_' . date('Ymd_His'), 'Monthly Worked Hrs', array_values($colDefs), $out, [
            'title' => 'Monthly Worked Hrs', 'meta' => date('F Y', strtotime($data['from'])), 'freeze' => true,
        ]);
    }

    // ------------------------------------------------------------------
    //  Monthly Overtime — OT hours per day + the 6 OT-type summary columns.
    // ------------------------------------------------------------------

    private function overtimeColumns(): array
    {
        return ['NOT' => 'Normal OT', 'WOT' => 'Weekend OT', 'HOT' => 'Holiday OT', 'OT1' => 'OT1', 'OT2' => 'OT2', 'OT3' => 'OT3'];
    }

    public function overtime(): string
    {
        [$year, $month, $dept] = $this->period();
        $data = $this->rows($year, $month, $dept);
        [$pageRows, $pager] = $this->pagedArray($data['rows'], 25);

        return view('admin/reports/monthly_overtime', [
            'title' => 'Monthly Overtime', 'subtitle' => date('F Y', strtotime($data['from'])),
            'active' => 'reports.monthlyOvertime',
            'year' => $year, 'month' => $month, 'dept' => $dept,
            'dim' => $data['dim'], 'rows' => $pageRows, 'pager' => $pager, 'payCodes' => $data['payCodes'],
            'summaryCols' => $this->overtimeColumns(),
            'departments' => (new \App\Models\EmployeeModel())->departments(),
            'cellOf' => fn (array $d) => $this->hoursCode($d, 'ot_minutes'),
            'valOf'  => fn (array $row, string $c) => $this->pcVal(['pc' => $row['pc'], 'payCodes' => $data['payCodes']], $c),
        ]);
    }

    public function overtimeExport()
    {
        [$year, $month, $dept] = $this->period();
        $data   = $this->rows($year, $month, $dept);
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';

        $colDefs = ['emp_id' => 'Employee ID', 'name' => 'First Name', 'dept' => 'Department'];
        for ($i = 1; $i <= $data['dim']; $i++) {
            $colDefs['d' . $i] = sprintf('%02d', $i);
        }
        foreach ($this->overtimeColumns() as $code => $label) {
            $colDefs['pc_' . $code] = $label . '(H)';
        }
        $colDefs = $this->filterCols($colDefs, (string) $this->request->getGet('cols'));
        $keys    = array_keys($colDefs);

        $out = [];
        foreach ($data['rows'] as $r) {
            $full = ['emp_id' => $r['emp']['emp_code'], 'name' => emp_name($r['emp']), 'dept' => $r['emp']['dept_name'] ?? ''];
            for ($i = 1; $i <= $data['dim']; $i++) {
                $full['d' . $i] = isset($r['days'][$i]) ? $this->hoursCode($r['days'][$i], 'ot_minutes') : '';
            }
            foreach (array_keys($this->overtimeColumns()) as $c) {
                $full['pc_' . $c] = $this->pcVal(['pc' => $r['pc'], 'payCodes' => $data['payCodes']], $c);
            }
            $out[] = array_map(static fn ($k) => $full[$k], $keys);
        }
        $this->audit('export.monthly_overtime', count($out) . " rows ({$format})");

        return Export::stream($format, 'monthly_overtime_' . date('Ymd_His'), 'Monthly Overtime', array_values($colDefs), $out, [
            'title' => 'Monthly Overtime', 'meta' => date('F Y', strtotime($data['from'])), 'freeze' => true,
        ]);
    }

    // ------------------------------------------------------------------
    //  Monthly Absence — one summary row per employee, no day columns.
    // ------------------------------------------------------------------

    public function absence(): string
    {
        [$year, $month, $dept] = $this->period();
        $data = $this->rows($year, $month, $dept);
        [$pageRows, $pager] = $this->pagedArray($data['rows'], 25);

        return view('admin/reports/monthly_absence', [
            'title' => 'Monthly Absence', 'subtitle' => date('F Y', strtotime($data['from'])),
            'active' => 'reports.monthlyAbsence',
            'year' => $year, 'month' => $month, 'dept' => $dept,
            'rows' => $pageRows, 'pager' => $pager,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
        ]);
    }

    public function absenceExport()
    {
        [$year, $month, $dept] = $this->period();
        $data   = $this->rows($year, $month, $dept);
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';

        $colDefs = $this->filterCols([
            'emp_id' => 'Employee ID', 'name' => 'First Name', 'dept' => 'Department',
            'need' => 'Need Present Days', 'present' => 'Present', 'absence' => 'Absence',
            'holiday' => 'Holiday Days', 'present_pct' => 'Present Percentage', 'absence_pct' => 'Absence Percentage',
        ], (string) $this->request->getGet('cols'));
        $keys = array_keys($colDefs);

        $out = [];
        foreach ($data['rows'] as $r) {
            $need = $r['present'] + $r['absentDays'];
            $full = [
                'emp_id' => $r['emp']['emp_code'], 'name' => emp_name($r['emp']), 'dept' => $r['emp']['dept_name'] ?? '',
                'need' => $need ?: '', 'present' => $r['present'] ?: '', 'absence' => $r['absentDays'] ?: '', 'holiday' => $r['holidayDays'] ?: '',
                'present_pct' => number_format($need ? $r['present'] / $need * 100 : 0, 2) . '%',
                'absence_pct' => number_format($need ? $r['absentDays'] / $need * 100 : 0, 2) . '%',
            ];
            $out[] = array_map(static fn ($k) => $full[$k], $keys);
        }
        $this->audit('export.monthly_absence', count($out) . " rows ({$format})");

        return Export::stream($format, 'monthly_absence_' . date('Ymd_His'), 'Monthly Absence', array_values($colDefs), $out, [
            'title' => 'Monthly Absence', 'meta' => date('F Y', strtotime($data['from'])), 'freeze' => true,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Every att_paycode row that isn't one of the fixed REG/L/E/A/OT
     * columns — i.e. the configured leave types (including Compensatory,
     * which — like OT1-3 — always computes to 0 since it's not
     * implemented, but still gets its own column, matching both the
     * reference export and the existing Total Time Card report).
     */
    private function leaveCodes(array $payCodes): array
    {
        $fixed = ['REG', 'L', 'E', 'A', 'NOT', 'WOT', 'HOT', 'OT1', 'OT2', 'OT3'];

        return array_values(array_filter(
            array_map(static fn ($p) => strtoupper($p['code']), $payCodes),
            static fn ($c) => ! in_array($c, $fixed, true)
        ));
    }

    private function summaryLabel(string $code, array $payCodes): string
    {
        $fixed = $this->checkInOutColumns();
        $label = $fixed[$code] ?? $this->payLabel($payCodes, $code);

        return $label . '(' . $this->unitFor($code, $payCodes) . ')';
    }
}
