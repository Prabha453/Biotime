<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;

class Attendance extends BaseController
{
    /**
     * The default "day status" date is always literal today — per client
     * spec, every module defaults to the current date/month. The engine
     * now computes Absent for an unpunched today rather than "No Data",
     * so there's no reason to fall back to an earlier "last day with
     * data" when today simply hasn't had any punches synced in yet.
     */
    private function latestPunchDate(): string
    {
        return date('Y-m-d');
    }

    // ---- Daily register ------------------------------------------------
    public function daily()
    {
        $calc = new AttendanceCalculator();
        $date = $this->request->getGet('date') ?: $this->latestPunchDate();
        $dept = (int) $this->request->getGet('dept') ?: null;
        $rows = $calc->forDate($date, $dept);

        // rows already arrive in emp_code_digit order from the calculator
        $summary = ['present' => 0, 'late' => 0, 'early' => 0, 'absent' => 0, 'incomplete' => 0, 'leave' => 0, 'off' => 0];
        foreach ($rows as $r) {
            $summary[AttendanceCalculator::bucket($r['status'])]++;
        }
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/attendance/daily', [
            'title'       => 'Daily Register',
            'subtitle'    => date('l, j M Y', strtotime($date)),
            'active'      => 'att.daily',
            'date'        => $date,
            'dept'        => $dept,
            'rows'        => $rows,
            'pager'       => $pager,
            'summary'     => $summary,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
        ]);
    }

    public function dailyExport()
    {
        $calc = new AttendanceCalculator();
        $date = $this->request->getGet('date') ?: $this->latestPunchDate();
        $rows = $calc->forDate($date, (int) $this->request->getGet('dept') ?: null);

        $out = ["Code,Name,Department,Scheduled In,Scheduled Out,First In,Last Out,Worked,Late (min),Early (min),OT (min),Status"];
        foreach ($rows as $r) {
            $e = $r['employee'];
            $out[] = implode(',', array_map([$this, 'csv'], [
                $e['emp_code'], emp_name($e), $e['dept_name'] ?? '',
                $r['scheduled']['in'] ?? '', $r['scheduled']['out'] ?? '',
                $r['first_in'] ?? '', $r['last_out'] ?? '',
                AttendanceCalculator::hhmm($r['worked_minutes']),
                $r['late_minutes'], $r['early_minutes'], $r['ot_minutes'], $r['status'],
            ]));
        }

        return $this->response->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', "attachment; filename=daily-{$date}.csv")
            ->setBody(implode("\n", $out));
    }

    // ---- Monthly timesheet ------------------------------------------------
    public function monthly()
    {
        $calc  = new AttendanceCalculator();
        $latest = $this->latestPunchDate();
        $year  = (int) ($this->request->getGet('y') ?: substr($latest, 0, 4));
        $month = (int) ($this->request->getGet('m') ?: substr($latest, 5, 2));
        $dept  = (int) $this->request->getGet('dept') ?: null;

        $from  = sprintf('%04d-%02d-01', $year, $month);
        $to    = date('Y-m-t', strtotime($from));
        $daysInMonth = (int) date('t', strtotime($from));

        // paginate the EMPLOYEE list before the per-employee day-by-day
        // calculation below, which is otherwise an O(all employees × days)
        // pass on every load.
        [$employees, $pager] = $this->pagedArray($calc->employees($dept), 25);

        // one batched forRange() call for this page's employees — looping
        // forEmployee() per employee instead re-fetches punches/holidays/
        // leaves fresh for each one (holidays especially is identical data
        // every time, so that's pure waste).
        $byEmp = [];
        foreach ($calc->forRange(array_map(static fn ($e) => (int) $e['id'], $employees), $from, $to) as $d) {
            $byEmp[(int) $d['employee']['id']][(int) substr($d['date'], 8, 2)] = $d;
        }

        $grid = [];
        foreach ($employees as $e) {
            $days = $byEmp[(int) $e['id']] ?? [];
            $tot  = ['present' => 0, 'absent' => 0, 'late' => 0, 'late_min' => 0, 'ot' => 0, 'worked' => 0, 'leave' => 0];
            $cells = [];
            foreach ($days as $d) {
                $cells[(int) substr($d['date'], 8, 2)] = $this->cellCode($d);
                $tot['late_min'] += $d['late_minutes'];
                $tot['ot']       += $d['ot_minutes'];
                $tot['worked']   += $d['worked_minutes'];
                if (AttendanceCalculator::isPresent($d['status'])) {
                    $tot['present']++;
                }
                if ($d['status'] === 'Absent') {
                    $tot['absent']++;
                }
                if ($d['late_minutes'] > 0) {
                    $tot['late']++;
                }
                if ($d['on_leave']) {
                    $tot['leave']++;
                }
            }
            $grid[] = ['emp' => $e, 'cells' => $cells, 'tot' => $tot];
        }

        return view('admin/attendance/monthly', [
            'title'       => 'Monthly Timesheet',
            'subtitle'    => date('F Y', strtotime($from)),
            'active'      => 'att.monthly',
            'year'        => $year,
            'month'       => $month,
            'dept'        => $dept,
            'daysInMonth' => $daysInMonth,
            'from'        => $from,
            'grid'        => $grid,
            'pager'       => $pager,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
        ]);
    }

    public function monthlyExport()
    {
        $calc  = new AttendanceCalculator();
        $latest = $this->latestPunchDate();
        $year  = (int) ($this->request->getGet('y') ?: substr($latest, 0, 4));
        $month = (int) ($this->request->getGet('m') ?: substr($latest, 5, 2));
        $from  = sprintf('%04d-%02d-01', $year, $month);
        $to    = date('Y-m-t', strtotime($from));
        $dim   = (int) date('t', strtotime($from));

        $header = ['Code', 'Name'];
        for ($i = 1; $i <= $dim; $i++) {
            $header[] = $i;
        }
        $header = array_merge($header, ['Present', 'Absent', 'Late', 'Late min', 'OT min', 'Worked min']);
        $out    = [implode(',', $header)];

        $dept = (int) $this->request->getGet('dept') ?: null;
        // one batched forRange() call for every employee, instead of
        // re-fetching punches/holidays/leaves per employee via forEmployee().
        $byEmp = [];
        foreach ($calc->forRange([], $from, $to, $dept) as $d) {
            $byEmp[(int) $d['employee']['id']][(int) substr($d['date'], 8, 2)] = $d;
        }

        foreach ($calc->employees($dept) as $e) {
            $days = $byEmp[(int) $e['id']] ?? [];
            $byDay = [];
            $p = $a = $l = $lm = $ot = $w = 0;
            foreach ($days as $d) {
                $byDay[(int) substr($d['date'], 8, 2)] = $this->cellCode($d);
                $lm += $d['late_minutes']; $ot += $d['ot_minutes']; $w += $d['worked_minutes'];
                if (AttendanceCalculator::isPresent($d['status'])) $p++;
                if ($d['status'] === 'Absent') $a++;
                if ($d['late_minutes'] > 0) $l++;
            }
            $line = [$this->csv($e['emp_code']), $this->csv(emp_name($e))];
            for ($i = 1; $i <= $dim; $i++) {
                $line[] = $byDay[$i] ?? '';
            }
            $line = array_merge($line, [$p, $a, $l, $lm, $ot, $w]);
            $out[] = implode(',', $line);
        }

        return $this->response->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', "attachment; filename=timesheet-{$year}-{$month}.csv")
            ->setBody(implode("\n", $out));
    }

    public function timesheet(int $id)
    {
        return redirect()->to(route_to('employee.view', $id)
            . '?m=' . (int) $this->request->getGet('m') . '&y=' . (int) $this->request->getGet('y'));
    }

    // ---- helpers ------------------------------------------------
    private function cellCode(array $d): string
    {
        if ($d['on_leave'])   return 'V';
        if ($d['is_holiday']) return 'H';
        if ($d['is_weekend']) return $d['punch_count'] ? 'P' : 'W';
        return match ($d['status']) {
            'Present', 'Early Out'          => 'P',
            'Late', 'Late & Early'          => 'L',
            'Incomplete'                    => 'I',
            'Absent'                        => 'A',
            default                         => $d['punch_count'] ? 'P' : 'N',
        };
    }

    private function csv($v): string
    {
        $v = (string) $v;

        return str_contains($v, ',') || str_contains($v, '"') ? '"' . str_replace('"', '""', $v) . '"' : $v;
    }
}
