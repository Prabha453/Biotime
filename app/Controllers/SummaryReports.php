<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;
use App\Libraries\Export;

/**
 * ZKBioTime's "Summary Report" group: Employee Attendance/Overtime/Leave,
 * Department Attendance/Overtime, Group Attendance/Overtime — seven
 * separate pages (Leave Balance, the 8th, already exists as its own
 * report). One row per employee/department/group summarising a date
 * range, verified against the real app's Group Attendance columns
 * (Code · Name · Employee Qty. · Late/Early/Absent(Times) · Regular(H) ·
 * Late In(M) · Early Out(H) · Absence(H) · OT-type(H) columns).
 *
 * Shares the exact pay-code accumulator logic MonthlyReports/Total Time
 * Card use (REG/L/E/A/NOT/WOT/HOT/OT1-3 + one column per configured
 * leave-type att_paycode) — summed across the date range per employee,
 * then optionally re-summed again across every employee in a department
 * or group.
 */
class SummaryReports extends BaseController
{
    /** ?from= / ?to= (defaults to current-month-to-date, like every other report) + ?dept= / ?group= filters. */
    private function period(): array
    {
        $today = date('Y-m-d');
        $to    = $this->request->getGet('to')   ?: $today;
        $from  = $this->request->getGet('from') ?: date('Y-m-01', strtotime($to));
        $dept  = (int) $this->request->getGet('dept')  ?: null;
        $group = (int) $this->request->getGet('group') ?: null;

        return [$from, $to, $dept, $group];
    }

    /**
     * Same mechanism as Total Time Card / Monthly Reports' export: the
     * Columns button appends ?cols= (comma list of data-col keys); this
     * narrows a [key=>label] map to that subset, preserving display
     * order, and falls back to everything when absent/empty/stale.
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
     * One row per employee across the date range: the full pay-code
     * minute accumulator (same logic as MonthlyReports::rows()) plus day
     * counts for Late/Early/Absent — the "(Times)" columns the real app
     * shows that a minute-accumulator alone doesn't cover.
     */
    private function employeeRows(string $from, string $to, ?int $dept, ?int $group): array
    {
        $db       = db_connect();
        $payCodes = $db->query('SELECT code, name, display_format FROM att_paycode ORDER BY display_order, id')->getResultArray();
        $calc     = new AttendanceCalculator();

        $employees = $calc->employees($dept);
        if ($group) {
            $employees = array_values(array_filter($employees, static fn ($e) => (int) ($e['att_group_id'] ?? 0) === $group));
        }
        if ($employees === []) {
            return ['rows' => [], 'payCodes' => $payCodes];
        }
        $empIds = array_map(static fn ($e) => (int) $e['id'], $employees);

        $byEmp = [];
        foreach ($calc->forRange($empIds, $from, $to) as $d) {
            $byEmp[(int) $d['employee']['id']][] = $d;
        }

        $rows = [];
        foreach ($employees as $e) {
            $pc = array_fill_keys(array_map(static fn ($p) => strtoupper($p['code']), $payCodes), 0);
            $lateTimes = $earlyTimes = $absentTimes = 0;
            foreach ($byEmp[(int) $e['id']] ?? [] as $d) {
                $kind      = $d['ot_kind'] ?? 'normal';
                $onLeave   = ! empty($d['on_leave']);
                $leaveName = (string) ($d['leave_type'] ?? '');
                foreach ($payCodes as $p) {
                    $code = strtoupper($p['code']);
                    $min  = match ($code) {
                        'REG'                     => $d['regular_minutes'] ?? 0,
                        'L'                       => $d['late_minutes'] ?? 0,
                        'E'                       => $d['early_minutes'] ?? 0,
                        'A'                       => $d['absent_minutes'] ?? 0,
                        'NOT'                     => $kind === 'normal'  ? ($d['ot_minutes'] ?? 0) : 0,
                        'WOT'                     => $kind === 'weekend' ? ($d['ot_minutes'] ?? 0) : 0,
                        'HOT'                     => $kind === 'holiday' ? ($d['ot_minutes'] ?? 0) : 0,
                        'OT1', 'OT2', 'OT3', 'CP' => 0,
                        default                   => ($onLeave && $leaveName !== '' && strcasecmp($p['name'], $leaveName) === 0) ? ($d['leave_minutes'] ?? 0) : 0,
                    };
                    $pc[$code] += $min;
                }
                if (($d['late_minutes'] ?? 0) > 0) {
                    $lateTimes++;
                }
                if (($d['early_minutes'] ?? 0) > 0) {
                    $earlyTimes++;
                }
                if ($d['status'] === 'Absent') {
                    $absentTimes++;
                }
            }
            $rows[] = [
                'emp' => $e, 'pc' => $pc,
                'lateTimes' => $lateTimes, 'earlyTimes' => $earlyTimes, 'absentTimes' => $absentTimes,
            ];
        }

        return ['rows' => $rows, 'payCodes' => $payCodes];
    }

    /** Re-sums employeeRows() by department_id or att_group_id — one row per department/group + how many employees fell into it. */
    private function aggregate(array $employeeRows, string $field): array
    {
        $out = [];
        foreach ($employeeRows as $r) {
            $key = (int) ($r['emp'][$field] ?? 0);
            if (! isset($out[$key])) {
                $out[$key] = [
                    'emp' => $r['emp'], 'qty' => 0,
                    'pc' => array_fill_keys(array_keys($r['pc']), 0),
                    'lateTimes' => 0, 'earlyTimes' => 0, 'absentTimes' => 0,
                ];
            }
            $out[$key]['qty']++;
            foreach ($r['pc'] as $code => $min) {
                $out[$key]['pc'][$code] += $min;
            }
            $out[$key]['lateTimes']   += $r['lateTimes'];
            $out[$key]['earlyTimes']  += $r['earlyTimes'];
            $out[$key]['absentTimes'] += $r['absentTimes'];
        }

        return array_values($out);
    }

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

    /** ZKBioTime always shows Late In in minutes and every other fixed column (incl. Early Out) in hours, regardless of that pay code's own display_format. */
    private function unitFor(string $code, array $payCodes): string
    {
        $fixed = ['REG' => 'H', 'L' => 'M', 'E' => 'H', 'A' => 'H', 'NOT' => 'H', 'WOT' => 'H', 'HOT' => 'H', 'OT1' => 'H', 'OT2' => 'H', 'OT3' => 'H'];

        return $fixed[$code] ?? $this->payUnit($payCodes, $code);
    }

    private function pcVal(array $pc, string $code, array $payCodes): string
    {
        $min  = (int) ($pc[$code] ?? 0);
        $unit = $this->unitFor($code, $payCodes);

        return $unit === 'M' ? number_format($min, 1) : number_format($min / 60, 1);
    }

    /** Every att_paycode row that isn't a fixed REG/L/E/A/OT column — the configured leave types. */
    private function leaveCodes(array $payCodes): array
    {
        $fixed = ['REG', 'L', 'E', 'A', 'NOT', 'WOT', 'HOT', 'OT1', 'OT2', 'OT3'];

        return array_values(array_filter(
            array_map(static fn ($p) => strtoupper($p['code']), $payCodes),
            static fn ($c) => ! in_array($c, $fixed, true)
        ));
    }

    private function attendanceColumns(): array
    {
        return ['REG' => 'Regular', 'L' => 'Late In', 'E' => 'Early Out', 'A' => 'Absence',
            'NOT' => 'Normal OT', 'WOT' => 'Weekend OT', 'HOT' => 'Holiday OT', 'OT1' => 'OT1', 'OT2' => 'OT2', 'OT3' => 'OT3'];
    }

    private function overtimeColumns(): array
    {
        return ['NOT' => 'Normal OT', 'WOT' => 'Weekend OT', 'HOT' => 'Holiday OT', 'OT1' => 'OT1', 'OT2' => 'OT2', 'OT3' => 'OT3'];
    }

    // ------------------------------------------------------------------
    //  Shared render — one grid for all 7 reports, parameterised by
    //  grouping level (employee/department/group) and column mode
    //  (attendance/overtime/leave).
    // ------------------------------------------------------------------

    private function render(string $level, string $mode, string $title, string $activeKey, string $exportRoute): string
    {
        [$from, $to, $dept, $group] = $this->period();
        $emp = $this->employeeRows($from, $to, $dept, $group);

        if ($level === 'employee') {
            $rows    = $emp['rows'];
            $idLbl   = 'Employee ID';
            $idOf    = static fn ($r) => $r['emp']['emp_code'];
            $nameOf  = static fn ($r) => emp_name($r['emp']);
            $subOf   = static fn ($r) => $r['emp']['dept_name'] ?? '';
            $subLbl  = 'Department';
            $showQty = false;
        } else {
            $field  = $level === 'department' ? 'department_id' : 'att_group_id';
            $rows   = $this->aggregate($emp['rows'], $field);
            $idLbl  = $level === 'department' ? 'Department Code' : 'Group Code';
            $idOf   = $level === 'department'
                ? static fn ($r) => $r['emp']['dept_code'] ?? ''
                : fn ($r) => $this->groupCode((int) ($r['emp']['att_group_id'] ?? 0));
            $nameOf = $level === 'department'
                ? static fn ($r) => $r['emp']['dept_name'] ?? '—'
                : fn ($r) => $this->groupName((int) ($r['emp']['att_group_id'] ?? 0));
            $subOf   = null;
            $subLbl  = null;
            $showQty = true;
        }

        $summaryCols = $mode === 'overtime' ? $this->overtimeColumns()
            : ($mode === 'leave' ? array_combine($this->leaveCodes($emp['payCodes']), array_map(fn ($c) => $this->payLabel($emp['payCodes'], $c), $this->leaveCodes($emp['payCodes'])))
            : $this->attendanceColumns());
        $showTimes = $mode === 'attendance';

        $q = http_build_query(['from' => $from, 'to' => $to, 'dept' => $dept, 'group' => $group]);
        [$rows, $pager] = $this->pagedArray($rows, 25);

        return view('admin/reports/summary_grid', [
            'title' => $title, 'subtitle' => "$from → $to", 'active' => $activeKey,
            'from' => $from, 'to' => $to, 'dept' => $dept, 'group' => $group,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
            'groups' => $this->allGroups(), 'pager' => $pager,
            'rows' => $rows, 'level' => $level, 'showQty' => $showQty, 'showTimes' => $showTimes,
            'idLbl' => $idLbl, 'idOf' => $idOf,
            'nameOf' => $nameOf, 'subOf' => $subOf, 'subLbl' => $subLbl,
            'summaryCols' => $summaryCols, 'payCodes' => $emp['payCodes'],
            'valOf' => fn ($r, $c) => $this->pcVal($r['pc'], $c, $emp['payCodes']),
            'unitOf' => fn ($c) => $this->unitFor($c, $emp['payCodes']),
            'exportUrl' => export_dropdown(route_to($exportRoute) . '?' . $q),
        ]);
    }

    private function exportCommon(string $level, string $mode): \CodeIgniter\HTTP\ResponseInterface
    {
        [$from, $to, $dept, $group] = $this->period();
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';
        $emp    = $this->employeeRows($from, $to, $dept, $group);

        if ($level === 'employee') {
            $rows = $emp['rows'];
        } else {
            $field = $level === 'department' ? 'department_id' : 'att_group_id';
            $rows  = $this->aggregate($emp['rows'], $field);
        }

        $summaryCols = $mode === 'overtime' ? $this->overtimeColumns()
            : ($mode === 'leave' ? array_combine($this->leaveCodes($emp['payCodes']), array_map(fn ($c) => $this->payLabel($emp['payCodes'], $c), $this->leaveCodes($emp['payCodes'])))
            : $this->attendanceColumns());
        $showTimes = $mode === 'attendance';

        $colDefs = ['id' => $level === 'employee' ? 'Employee ID' : ($level === 'department' ? 'Department' : 'Group'), 'name' => $level === 'employee' ? 'Employee Name' : 'Name'];
        if ($level === 'employee') {
            $colDefs['dept'] = 'Department';
        } else {
            $colDefs['qty'] = 'Employee Qty.';
        }
        if ($showTimes) {
            $colDefs['late_times'] = 'Late(Times)';
            $colDefs['early_times'] = 'Early Leave(Times)';
            $colDefs['absent_times'] = 'Absent(Times)';
        }
        foreach ($summaryCols as $code => $label) {
            $colDefs['pc_' . $code] = $label . '(' . $this->unitFor($code, $emp['payCodes']) . ')';
        }
        $colDefs = $this->filterCols($colDefs, (string) $this->request->getGet('cols'));
        $keys    = array_keys($colDefs);

        $out = [];
        foreach ($rows as $r) {
            $full = [
                'id'   => $level === 'employee' ? $r['emp']['emp_code'] : ($level === 'department' ? ($r['emp']['dept_code'] ?? '') : $this->groupCode((int) ($r['emp']['att_group_id'] ?? 0))),
                'name' => $level === 'employee' ? emp_name($r['emp']) : ($level === 'department' ? ($r['emp']['dept_name'] ?? '') : $this->groupName((int) ($r['emp']['att_group_id'] ?? 0))),
            ];
            if ($level === 'employee') {
                $full['dept'] = $r['emp']['dept_name'] ?? '';
            } else {
                $full['qty'] = $r['qty'];
            }
            if ($showTimes) {
                $full['late_times']   = $r['lateTimes'];
                $full['early_times']  = $r['earlyTimes'];
                $full['absent_times'] = $r['absentTimes'];
            }
            foreach ($summaryCols as $code => $label) {
                $full['pc_' . $code] = $this->pcVal($r['pc'], $code, $emp['payCodes']);
            }
            $out[] = array_map(static fn ($k) => $full[$k], $keys);
        }
        $this->audit('export.summary_' . $level . '_' . $mode, count($out) . " rows ({$format})");

        return Export::stream($format, 'summary_' . $level . '_' . $mode . '_' . date('Ymd_His'), 'Summary', array_values($colDefs), $out, [
            'title' => ucfirst($level) . ' ' . ucfirst($mode), 'meta' => "$from → $to", 'freeze' => true,
        ]);
    }

    private function allGroups(): array
    {
        return db_connect()->query('SELECT id, code, name FROM att_attgroup ORDER BY name')->getResultArray();
    }

    private function groupName(int $id): string
    {
        foreach ($this->allGroups() as $g) {
            if ((int) $g['id'] === $id) {
                return $g['name'];
            }
        }

        return '—';
    }

    private function groupCode(int $id): string
    {
        foreach ($this->allGroups() as $g) {
            if ((int) $g['id'] === $id) {
                return $g['code'];
            }
        }

        return '';
    }

    // ------------------------------------------------------------------

    public function employeeAttendance(): string { return $this->render('employee', 'attendance', 'Employee Attendance', 'summary.employeeAttendance', 'summary.employeeAttendance.export'); }
    public function employeeAttendanceExport() { return $this->exportCommon('employee', 'attendance'); }

    public function employeeOvertime(): string { return $this->render('employee', 'overtime', 'Employee Overtime', 'summary.employeeOvertime', 'summary.employeeOvertime.export'); }
    public function employeeOvertimeExport() { return $this->exportCommon('employee', 'overtime'); }

    public function employeeLeave(): string { return $this->render('employee', 'leave', 'Employee Leave', 'summary.employeeLeave', 'summary.employeeLeave.export'); }
    public function employeeLeaveExport() { return $this->exportCommon('employee', 'leave'); }

    public function departmentAttendance(): string { return $this->render('department', 'attendance', 'Department Attendance', 'summary.departmentAttendance', 'summary.departmentAttendance.export'); }
    public function departmentAttendanceExport() { return $this->exportCommon('department', 'attendance'); }

    public function departmentOvertime(): string { return $this->render('department', 'overtime', 'Department Overtime', 'summary.departmentOvertime', 'summary.departmentOvertime.export'); }
    public function departmentOvertimeExport() { return $this->exportCommon('department', 'overtime'); }

    public function groupAttendance(): string { return $this->render('group', 'attendance', 'Group Attendance', 'summary.groupAttendance', 'summary.groupAttendance.export'); }
    public function groupAttendanceExport() { return $this->exportCommon('group', 'attendance'); }

    public function groupOvertime(): string { return $this->render('group', 'overtime', 'Group Overtime', 'summary.groupOvertime', 'summary.groupOvertime.export'); }
    public function groupOvertimeExport() { return $this->exportCommon('group', 'overtime'); }
}
