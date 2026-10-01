<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;

class Reports extends BaseController
{
    private function range(): array
    {
        // default range: 1st of the current month → today — per client
        // spec, a From/To filter defaults to current-month-to-date (a
        // single-date filter defaults to literal today; see e.g.
        // Attendance::latestPunchDate()). The engine now computes Absent
        // for an unpunched today rather than "No Data", so there's no
        // reason to fall back to an earlier "last day with data" anymore.
        $today = date('Y-m-d');
        $to    = $this->request->getGet('to')   ?: $today;
        $from  = $this->request->getGet('from') ?: date('Y-m-01', strtotime($to));

        return [$from, $to];
    }

    /** run the calculator for every employee across the range, one batched pass. */
    private function scan(string $from, string $to): array
    {
        return (new AttendanceCalculator())->forRange([], $from, $to);
    }

    public function index(): string
    {
        return view('admin/reports/index', [
            'title'    => 'Reports',
            'subtitle' => 'Attendance analytics',
            'active'   => 'reports',
        ]);
    }

    public function late(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['late_minutes'] > 0));
        usort($rows, fn ($a, $b) => $b['late_minutes'] <=> $a['late_minutes']);
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'Late Arrivals', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'late',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'late', 'pager' => $pager,
        ]);
    }

    public function absent(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['status'] === 'Absent'));
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'Absentees', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'absent',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'absent', 'pager' => $pager,
        ]);
    }

    public function overtime(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['ot_minutes'] > 0));
        usort($rows, fn ($a, $b) => $b['ot_minutes'] <=> $a['ot_minutes']);
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'Overtime', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'overtime',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'overtime', 'pager' => $pager,
        ]);
    }

    /** Daily Report ▸ Daily Early Out — every scheduled day left before the grace window. */
    public function earlyOut(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['early_minutes'] > 0));
        usort($rows, fn ($a, $b) => $b['early_minutes'] <=> $a['early_minutes']);
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'Daily Early Out', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'early',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'early', 'pager' => $pager,
        ]);
    }

    /** Daily Report ▸ Daily Worked Hours — worked / OT / short per person per punched day. */
    public function dailyWorked(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['punch_count'] > 0 || $d['on_leave']));
        usort($rows, static fn ($a, $b) => [$a['date'], $a['employee']['emp_code']] <=> [$b['date'], $b['employee']['emp_code']]);
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'Daily Worked Hours', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'worked',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'worked', 'pager' => $pager,
        ]);
    }

    /**
     * Transaction Report ▸ Time Card (ZKBio `time_card_report`) — one row per
     * employee per day with the full list of that day's punch times.
     */
    public function punchCard(): string
    {
        [$from, $to] = $this->range();
        $db   = db_connect();
        $q    = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = 50;

        $where  = "t.punch_time >= ?::date AND t.punch_time < (?::date + INTERVAL '1 day')";
        $params = [$from, $to];
        if ($q !== '') {
            $where .= " AND (t.emp_code ILIKE ? OR e.first_name ILIKE ? OR e.last_name ILIKE ?)";
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }

        // BaseController has already SET the session TIME ZONE, so ::date and
        // to_char() below resolve in the deployment's wall-clock zone.
        $base = "FROM iclock_transaction t
            LEFT JOIN personnel_employee e ON e.emp_code = t.emp_code
            LEFT JOIN personnel_department d ON d.id = e.department_id
            WHERE {$where}
            GROUP BY t.emp_code, t.punch_time::date, e.first_name, e.last_name, d.dept_name";

        $total = (int) ($db->query("SELECT count(*) c FROM (SELECT 1 {$base}) x", $params)->getRow()->c ?? 0);

        $rows = $db->query(
            "SELECT t.emp_code,
                    t.punch_time::date AS att_date,
                    NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                    d.dept_name,
                    COUNT(*) AS times,
                    STRING_AGG(to_char(t.punch_time, 'HH24:MI'), ', ' ORDER BY t.punch_time) AS time_set
             {$base}
             ORDER BY att_date DESC, t.emp_code
             LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        )->getResultArray();

        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $per, $total, 'tt', 0, 'default');

        return view('admin/reports/punchcard', [
            'title' => 'Time Card', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'punchcard',
            'from' => $from, 'to' => $to, 'q' => $q, 'rows' => $rows, 'pager' => $pager,
        ]);
    }

    /**
     * Daily Report ▸ Scheduled Log (ZKBio `scheduled_punch_report`) — every
     * punch as ZKBio's own calc engine recorded it: the device's raw
     * `punch_state` next to the `adjust_state` it was corrected to once
     * paired against the shift window (att_payloadeffectpunch — persisted
     * output of the real engine, not recomputed here).
     */
    public function scheduledLog(): string
    {
        [$from, $to] = $this->range();
        $db   = db_connect();
        $q    = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = 50;

        $where  = 'p.att_date >= ? AND p.att_date <= ?';
        $params = [$from, $to];
        if ($q !== '') {
            $where .= ' AND (e.emp_code ILIKE ? OR e.first_name ILIKE ? OR e.last_name ILIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $base = "FROM att_payloadeffectpunch p
            LEFT JOIN personnel_employee e ON e.id = p.emp_id
            WHERE {$where}";

        $total = (int) ($db->query("SELECT count(*) c {$base}", $params)->getRow()->c ?? 0);
        $rows  = $db->query(
            "SELECT e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                    p.att_date, p.work_code,
                    to_char(p.punch_datetime,'HH24:MI:SS') AS punch_time,
                    p.punch_state, p.adjust_state
             {$base}
             ORDER BY p.att_date DESC, e.emp_code, p.punch_datetime
             LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        )->getResultArray();

        $labels = config('Attendance')->stateLabels;
        foreach ($rows as &$r) {
            $r['weekday']       = date('D', strtotime($r['att_date']));
            $r['punch_label']   = $labels[(int) $r['punch_state']]  ?? ('#' . $r['punch_state']);
            $r['adjust_label']  = $labels[(int) $r['adjust_state']] ?? ('#' . $r['adjust_state']);
            $r['was_corrected'] = $r['punch_state'] !== $r['adjust_state'];
        }
        unset($r);

        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $per, $total, 'tt', 0, 'default');

        return view('admin/reports/scheduled_log', [
            'title' => 'Scheduled Log', 'subtitle' => "$from → $to · every punch as recorded vs. how it paired",
            'active' => 'reports', 'sub' => 'scheduled_log',
            'from' => $from, 'to' => $to, 'q' => $q, 'rows' => $rows, 'pager' => $pager,
        ]);
    }

    /**
     * Daily Report ▸ Paired Punch (ZKBio `punch_paring`) — one row per
     * matched clock-in/clock-out pair, straight from `att_payloadparing`
     * (the real engine's own pairing output — not recomputed here).
     */
    public function pairedPunch(): string
    {
        [$from, $to] = $this->range();
        $db   = db_connect();
        $q    = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = 50;

        $where  = 'p.att_date >= ? AND p.att_date <= ?';
        $params = [$from, $to];
        if ($q !== '') {
            $where .= ' AND (e.emp_code ILIKE ? OR e.first_name ILIKE ? OR e.last_name ILIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $base = "FROM att_payloadparing p
            LEFT JOIN personnel_employee e ON e.id = p.emp_id
            LEFT JOIN att_paycode pc ON pc.id = p.pay_code_id
            WHERE {$where}";

        $total = (int) ($db->query("SELECT count(*) c {$base}", $params)->getRow()->c ?? 0);
        $rows  = $db->query(
            "SELECT e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                    p.att_date, p.data_type, p.data_index,
                    to_char(p.clock_in,'HH24:MI:SS')  AS clock_in,
                    to_char(p.clock_out,'HH24:MI:SS') AS clock_out,
                    p.duration, p.worked_duration, p.workday, pc.name AS pay_code_name
             {$base}
             ORDER BY p.att_date DESC, e.emp_code, p.data_index
             LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        )->getResultArray();

        foreach ($rows as &$r) {
            $r['weekday'] = date('D', strtotime($r['att_date']));
        }
        unset($r);

        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $per, $total, 'tt', 0, 'default');

        return view('admin/reports/paired_punch', [
            'title' => 'Paired Punch', 'subtitle' => "$from → $to · how each clock-in matched its clock-out",
            'active' => 'reports', 'sub' => 'paired_punch',
            'from' => $from, 'to' => $to, 'q' => $q, 'rows' => $rows, 'pager' => $pager,
        ]);
    }

    /**
     * Daily Report ▸ Multiple Work Hours (ZKBio `daily_multiple_punch_paring`)
     * — every punch session on a day that had more than one, from
     * `att_payloadmulpunchset` (populated only for a day/employee with 2+
     * clock-in/clock-out sessions; empty is a correct, faithful result when
     * nobody has multi-session days in range, not a missing feature).
     */
    public function multipleWorkHours(): string
    {
        [$from, $to] = $this->range();
        $db   = db_connect();
        $q    = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = 50;

        $where  = 'm.att_date >= ? AND m.att_date <= ?';
        $params = [$from, $to];
        if ($q !== '') {
            $where .= ' AND (e.emp_code ILIKE ? OR e.first_name ILIKE ? OR e.last_name ILIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $base = "FROM att_payloadmulpunchset m
            LEFT JOIN personnel_employee e ON e.id = m.emp_id
            WHERE {$where}";

        $total = (int) ($db->query("SELECT count(*) c {$base}", $params)->getRow()->c ?? 0);
        $rows  = $db->query(
            "SELECT e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                    m.att_date, m.data_index, m.data_type,
                    to_char(m.clock_in,'HH24:MI:SS')  AS clock_in,
                    to_char(m.clock_out,'HH24:MI:SS') AS clock_out,
                    m.total_time, m.worked_time
             {$base}
             ORDER BY m.att_date DESC, e.emp_code, m.data_index
             LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        )->getResultArray();

        foreach ($rows as &$r) {
            $r['weekday'] = date('D', strtotime($r['att_date']));
        }
        unset($r);

        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $per, $total, 'tt', 0, 'default');

        return view('admin/reports/multiple_sessions', [
            'title' => 'Multiple Work Hours', 'subtitle' => "$from → $to · every session on a multi-punch day",
            'active' => 'reports', 'sub' => 'multi_work',
            'kind' => 'work', 'from' => $from, 'to' => $to, 'q' => $q, 'rows' => $rows, 'pager' => $pager,
        ]);
    }

    /**
     * Daily Report ▸ Multiple Break Time (ZKBio `daily_multiple_break_paring`)
     * — every break instance from `att_payloadbreak`, joined back to its
     * timecard for the date/employee (empty is correct/faithful when no
     * break punches exist in range, not a missing feature).
     */
    public function multipleBreakTime(): string
    {
        [$from, $to] = $this->range();
        $db   = db_connect();
        $q    = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $per  = 50;

        $where  = 't.att_date >= ? AND t.att_date <= ?';
        $params = [$from, $to];
        if ($q !== '') {
            $where .= ' AND (e.emp_code ILIKE ? OR e.first_name ILIKE ? OR e.last_name ILIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $base = "FROM att_payloadbreak b
            JOIN att_payloadtimecard t ON t.id::text = b.uuid
            LEFT JOIN personnel_employee e ON e.id = t.emp_id
            WHERE {$where}";

        $total = (int) ($db->query("SELECT count(*) c {$base}", $params)->getRow()->c ?? 0);
        $rows  = $db->query(
            "SELECT e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                    t.att_date,
                    to_char(b.break_out,'HH24:MI:SS') AS break_out,
                    to_char(b.break_in,'HH24:MI:SS')  AS break_in,
                    b.duration, b.taken, b.actual_duration, b.early_in, b.late_in, b.absent
             {$base}
             ORDER BY t.att_date DESC, e.emp_code
             LIMIT {$per} OFFSET " . (($page - 1) * $per),
            $params
        )->getResultArray();

        foreach ($rows as &$r) {
            $r['weekday'] = date('D', strtotime($r['att_date']));
        }
        unset($r);

        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $per, $total, 'tt', 0, 'default');

        return view('admin/reports/multiple_sessions', [
            'title' => 'Multiple Break Time', 'subtitle' => "$from → $to · every break instance",
            'active' => 'reports', 'sub' => 'multi_break',
            'kind' => 'break', 'from' => $from, 'to' => $to, 'q' => $q, 'rows' => $rows, 'pager' => $pager,
        ]);
    }

    public function leaveBalance(): string
    {
        $db   = db_connect();
        $year = (int) ($this->request->getGet('y') ?: date('Y'));

        $types = $db->query('SELECT id, code, name FROM att_paycode WHERE code_type = 3 ORDER BY display_order')->getResultArray();
        $emps  = $db->query(
            "SELECT e.id, e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name,
                    e.leave_group, g.name AS group_name
               FROM personnel_employee e
          LEFT JOIN att_leavegroup g ON g.id = e.leave_group
              WHERE e.is_active = true
           ORDER BY e.emp_code_digit"
        )->getResultArray();

        $lb  = new \App\Libraries\LeaveBalance($db);
        $all = $lb->forEmployees(array_column($emps, 'id'), array_column($types, 'id'), $year);
        $rows = [];
        foreach ($emps as $e) {
            $line = ['emp' => $e, 'cells' => []];
            foreach ($types as $t) {
                $line['cells'][$t['id']] = $e['leave_group']
                    ? ($all[(int) $e['id']][(int) $t['id']] ?? null)
                    : null;
            }
            $rows[] = $line;
        }
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/leave_balance', [
            'title' => 'Leave Balances', 'subtitle' => "Year {$year}", 'active' => 'reports',
            'sub' => 'leavebal', 'year' => $year, 'types' => $types, 'rows' => $rows, 'pager' => $pager,
        ]);
    }

    public function firstLast(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['punch_count'] > 0));
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'First & Last', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'firstlast',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'firstlast', 'pager' => $pager,
        ]);
    }

    /** First check-IN and last check-OUT per person per day (state-aware), with the span between. */
    public function firstInLastOut(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), fn ($d) => $d['punch_count'] > 0));
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'First In / Last Out', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'fio',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'fio', 'pager' => $pager,
        ]);
    }

    /** Every scheduled day with a late-in, early-out, absence or incomplete punch — ZKBio's "Exception" report. */
    public function exception(): string
    {
        [$from, $to] = $this->range();
        $rows = array_values(array_filter($this->scan($from, $to), static fn ($d) => in_array($d['status'], ['Late', 'Early Leave', 'Absent', 'Incomplete'], true)
            || $d['late_minutes'] > 0 || $d['early_minutes'] > 0));
        usort($rows, static fn ($a, $b) => [$a['date'], $a['employee']['emp_code']] <=> [$b['date'], $b['employee']['emp_code']]);
        [$rows, $pager] = $this->pagedArray($rows);

        return view('admin/reports/list', [
            'title' => 'Exceptions', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'exception',
            'from' => $from, 'to' => $to, 'rows' => $rows, 'kind' => 'exception', 'pager' => $pager,
        ]);
    }

    /**
     * ZKBioTime "Total Time Card" — one line per employee per day, aligned to
     * the real engine (att/calc_new/calc_interval):
     *   Duration        = timetable in_time → out_time
     *   Duty Duration   = interval_work_time = Duration − unpaid break
     *   Total Hrs       = out_punch − in_punch (raw span; 0 if incomplete)
     *   Regular         = Duty Duration − (Late In + Early Out + Absence) − Total Leave
     *   Remaining       = Duty Duration − Total Leave − Regular
     *   Worked Hrs      = Regular + Total OT − max(0, Break Taken − Break Allowance)
     * plus per-break Out/In/Hrs and the per-pay-code accumulator columns.
     * "Total Break Taken" is the break measured from the punches; only the part
     * OVER the timetable's break allowance is docked from Worked Hrs — the one
     * deliberate divergence from ZKBioTime. A timetable with no break linked
     * (or whose linked break isn't in effect on that date per its own
     * Start/End Date) never shows break time here, regardless of punch shape.
     */
    public function timeCard(): string
    {
        [$from, $to] = $this->range();
        $deptId    = (int) $this->request->getGet('dept') ?: null;
        $companyId = (int) $this->request->getGet('company') ?: null;
        $q      = trim((string) $this->request->getGet('q'));

        $tc   = $this->timeCardRows($from, $to, $deptId, $q, $companyId);
        $cols = $this->timeCardColumns($tc['defs'], $tc['payCodes']);
        [$rows, $pager] = $this->pagedArray($tc['rows']);

        $view = [];
        foreach ($rows as $r) {
            $flat = ['_status_class' => $r['status_class'], '_emp_id' => $r['emp']['id']];
            foreach ($cols as $k => $_) {
                $flat[$k] = $this->timeCardValue($r, $k, $tc['payCodes']);
            }
            $view[] = $flat;
        }

        return view('admin/reports/timecard', [
            'title'       => 'Total Time Card', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'timecard',
            'from'        => $from, 'to' => $to, 'dept' => $deptId, 'company' => $companyId, 'q' => $q,
            'rows'        => $view, 'columns' => $cols, 'pager' => $pager,
            'departments' => (new \App\Models\EmployeeModel())->departments(),
            'companies'   => (new \App\Models\EmployeeModel())->companies(),
        ]);
    }

    public function timeCardExport()
    {
        [$from, $to] = $this->range();
        $format  = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';
        $dept    = (int) $this->request->getGet('dept') ?: null;
        $company = (int) $this->request->getGet('company') ?: null;
        $tc     = $this->timeCardRows($from, $to, $dept, trim((string) $this->request->getGet('q')), $company);
        $cols   = $this->timeCardColumns($tc['defs'], $tc['payCodes']);

        // ?cols=a,b,c  → export only those columns (the ones ticked on screen),
        // in the model's own order. Absent → every column.
        $want = array_filter(array_map('trim', explode(',', (string) $this->request->getGet('cols'))));
        if ($want !== []) {
            $want = array_flip($want);
            $cols = array_filter($cols, static fn ($k) => isset($want[$k]), ARRAY_FILTER_USE_KEY);
        }
        if ($cols === []) {
            $cols = $this->timeCardColumns($tc['defs'], $tc['payCodes']);
        }

        $head = [];
        foreach ($cols as $c) {
            $head[] = $c[0];
        }

        $data = [];
        foreach ($tc['rows'] as $r) {
            $line = [];
            foreach ($cols as $k => $_) {
                $line[] = $this->timeCardValue($r, $k, $tc['payCodes']);
            }
            $data[] = $line;
        }

        $deptName = $dept ? (db_connect()->query('SELECT dept_name FROM personnel_department WHERE id = ?', [$dept])->getRow()->dept_name ?? '') : 'All departments';
        $meta = "{$from} → {$to}  ·  {$deptName}  ·  " . count($data) . ' rows  ·  Regular = Duty Duration − Late/Early/Absence − Leave  ·  Worked Hrs = Regular + Total OT';

        $this->audit('export.time_card', count($data) . " rows ({$format})");

        // no explicit widths → Xlsx auto-sizes every column to its content; data is centred
        return \App\Libraries\Export::stream($format, 'time_card_' . date('Ymd_His'), 'Total Time Card', $head, $data, [
            'title'  => 'Total Time Card',
            'meta'   => $meta,
            'freeze' => true,
        ]);
    }

    /**
     * The full ZKBioTime "Total Time Card" column model — shared by the
     * on-screen table and every export so they can never drift.
     *
     * Order: person → schedule → actual → totals → per-break Out/In/Hrs →
     * break totals → OT / leave / remaining totals → device / work-code /
     * location → exception → one accumulator column per att_paycode.
     *
     * @param list<string> $defs      break names (Break Time setup order)
     * @param list<array>  $payCodes  att_paycode rows (code, name, display_format)
     * @return array<string,array{0:string,1:string,2:bool}>  key => [label, align, visibleByDefault]
     */
    private function timeCardColumns(array $defs, array $payCodes): array
    {
        // Columns ticked on first open — everything else is available via the
        // Columns button but starts hidden.
        $on = ['emp_id', 'first_name', 'date', 'weekday', 'timetable', 'check_in', 'check_out',
            'duty', 'clock_in', 'clock_out', 'total', 'worked',
            'break_dur', 'break_taken'];
        foreach (array_keys($defs) as $i) {
            $on[] = "brk{$i}_out";
            $on[] = "brk{$i}_in";
            $on[] = "brk{$i}_hrs";
        }

        $defsList = [
            'emp_id'        => ['Employee ID',    ''],
            'first_name'    => ['First Name',     ''],
            'last_name'     => ['Last Name',      ''],
            'nickname'      => ['Nick Name',      ''],
            'gender'        => ['Gender',         ''],
            'dept_code'     => ['Department Code', ''],
            'department'    => ['Department',     ''],
            'position_code' => ['Position Code',  ''],
            'position'      => ['Position',       ''],
            'date'          => ['Date',           ''],
            'weekday'       => ['Weekday',        ''],
            'timetable'     => ['Timetable',      ''],
            'check_in'      => ['Check In',       ''],
            'check_out'     => ['Check Out',      ''],
            'duty'          => ['Duty Duration',  'end'],
            'break_dur'     => ['Break Duration', 'end'],
            'work_day'      => ['Work Day',       'end'],
            'clock_in'      => ['Clock In',       ''],
            'clock_out'     => ['Clock Out',      ''],
            'total'         => ['Total Hrs',      'end'],
            'worked'        => ['Worked Hrs',     'end'],
        ];
        foreach ($defs as $i => $name) {
            $defsList["brk{$i}_out"] = [$name . ' Out', 'center'];
            $defsList["brk{$i}_in"]  = [$name . ' In',  'center'];
            $defsList["brk{$i}_hrs"] = [$name . ' Hrs', 'end'];
        }
        $defsList['break_taken']     = ['Total Break Taken', 'end'];
        $defsList['total_ot']        = ['Total OT',          'end'];
        $defsList['total_leaves']    = ['Total Leaves',      'end'];
        $defsList['unscheduled']     = ['Unscheduled',       'end'];
        $defsList['remaining']       = ['Remaining',         'end'];
        $defsList['attendance']      = ['Attendance',        'end'];
        $defsList['device_in']       = ['Device[In]',        ''];
        $defsList['device_out']      = ['Device[Out]',       ''];
        $defsList['workcode_in']     = ['Work Code[In]',     ''];
        $defsList['workcode_out']    = ['Work Code[Out]',    ''];
        $defsList['location_in']     = ['Location[In]',      ''];
        $defsList['location_out']    = ['Location[Out]',     ''];
        $defsList['exception']       = ['Exception',         ''];
        foreach ($payCodes as $pc) {
            $unit = (int) $pc['display_format'] === 1 ? 'M' : 'H';
            $defsList['pc_' . strtolower($pc['code'])] = [$pc['name'] . "({$unit})", 'end'];
        }
        $defsList['status'] = ['Status', ''];

        $c = [];
        foreach ($defsList as $k => $meta) {
            $c[$k] = [$meta[0], $meta[1], in_array($k, $on, true)];
        }

        return $c;
    }

    /**
     * Plain-string value for one Time Card column in a raw row.
     * "(H)" columns render as decimal hours, "(M)" as whole minutes, every
     * other duration as hh:mm — matching ZKBioTime.
     */
    private function timeCardValue(array $r, string $key, array $payCodes): string
    {
        $d    = $r['raw'];
        $hhmm = static fn ($m) => $m > 0 ? AttendanceCalculator::hhmm((int) round($m)) : '';
        $hrs  = static fn ($m) => $m > 0 ? rtrim(rtrim(number_format($m / 60, 2), '0'), '.') : '';

        // --- per-break slot columns: brk{i}_out | brk{i}_in | brk{i}_hrs ---
        if (str_starts_with($key, 'brk')) {
            [$pre, $side] = explode('_', $key);
            $s = $r['slots'][(int) substr($pre, 3)] ?? null;
            if (! $s) {
                return '';
            }

            return $side === 'hrs' ? $hhmm($s['minutes'] ?? 0) : (string) ($s[$side] ?? '');
        }

        // --- per pay-code accumulator columns (ZKBio calc_interval split) ---
        if (str_starts_with($key, 'pc_')) {
            $code      = strtoupper(substr($key, 3));
            $kind      = $r['ot_kind'];
            $onLeave   = ! empty($d['on_leave']);
            $leaveName = (string) ($d['leave_type'] ?? '');

            $min = match ($code) {
                'REG'                     => $r['regular'],
                'L'                       => $r['late_min'],
                'E'                       => $r['early_min'],
                'A'                       => $r['absent_min'],
                'NOT'                     => $kind === 'normal'  ? $r['ot_min'] : 0,
                'WOT'                     => $kind === 'weekend' ? $r['ot_min'] : 0,
                'HOT'                     => $kind === 'holiday' ? $r['ot_min'] : 0,
                'OT1', 'OT2', 'OT3', 'CP' => 0,
                default                   => ($onLeave && $leaveName !== '' && $this->payCodeName($payCodes, $code) === $leaveName) ? $r['leave_min'] : 0,
            };
            if ($min <= 0) {
                return '';
            }

            return $this->payCodeUnit($payCodes, $code) === 'M' ? (string) (int) round($min) : $hrs($min);
        }

        $pn    = $d['punches'] ?? [];
        $wd    = (float) ($d['work_day_fraction'] ?? 0);
        // ZKBioTime "Work Day" column = the scheduled duty workday (1.0 for any
        // scheduled day, regardless of late/early/miss), NOT the worked fraction.
        $dutyWd = $d['scheduled']['work_day'] ?? ($d['flexible'] ?? false ? $wd : 0.0);
        $exSet = ['Late', 'Early Out', 'Late & Early', 'Absent', 'Incomplete', 'Unscheduled Punch'];

        return match ($key) {
            'emp_id'        => (string) $r['emp']['emp_code'],
            'first_name'    => (string) ($r['emp']['first_name'] ?? ''),
            'last_name'     => (string) ($r['emp']['last_name'] ?? ''),
            'nickname'      => (string) ($r['emp']['nickname'] ?? ''),
            'gender'        => ['M' => 'Male', 'F' => 'Female', 'O' => 'Other'][$r['emp']['gender'] ?? ''] ?? '',
            'dept_code'     => (string) ($r['emp']['dept_code'] ?? ''),
            'department'    => (string) ($r['emp']['dept_name'] ?? ''),
            'position_code' => (string) ($r['emp']['position_code'] ?? ''),
            'position'      => (string) ($r['emp']['position_name'] ?? ''),
            'date'          => $r['date'],
            'weekday'       => $r['weekday'],
            'timetable'     => $r['timetable'],
            'check_in'      => $r['check_in'],
            'check_out'     => $r['check_out'],
            'duty'          => $hhmm($r['duty_net']),            // Duty Duration = interval_work_time (net of unpaid break)
            'break_dur'     => $hhmm($r['sched_break']),
            'work_day'      => $dutyWd > 0 ? number_format($dutyWd, 1) : '',
            'clock_in'      => $r['clock_in'],
            'clock_out'     => $r['clock_out'],
            'total'         => $hhmm($r['total_hrs']),           // raw punch span
            'worked'        => $hhmm($r['worked_hrs']),          // Regular + Total OT
            'break_taken'   => $hhmm($r['taken_break']),         // actual break from the break punches
            'total_ot'      => $hhmm($r['ot_min']),
            'total_leaves'  => $hhmm($r['leave_min']),
            'unscheduled'   => empty($d['scheduled_day'])
                ? ($r['worked_hrs'] > 0 ? $hhmm($r['worked_hrs']) : '')
                : $hhmm($d['unschedule_minutes'] ?? 0),
            'remaining'     => $hhmm($r['remaining']),
            'attendance'    => $wd > 0 ? number_format($wd, 1) : '0',
            'device_in'     => (string) ($pn[0]['device'] ?? ''),
            'device_out'    => $pn ? (string) (end($pn)['device'] ?? '') : '',
            'workcode_in', 'workcode_out', 'location_in', 'location_out' => '',
            'exception'     => in_array($d['status'] ?? '', $exSet, true) ? $d['status'] : '',
            'status'        => $r['status'],
            default         => '',
        };
    }

    /** @param list<array> $payCodes */
    private function payCodeName(array $payCodes, string $code): string
    {
        foreach ($payCodes as $pc) {
            if (strcasecmp($pc['code'], $code) === 0) {
                return (string) $pc['name'];
            }
        }

        return '';
    }

    /** @param list<array> $payCodes */
    private function payCodeUnit(array $payCodes, string $code): string
    {
        foreach ($payCodes as $pc) {
            if (strcasecmp($pc['code'], $code) === 0) {
                return (int) $pc['display_format'] === 1 ? 'M' : 'H';
            }
        }

        return 'H';
    }

    /**
     * @return array{defs: list<string>, rows: list<array>}
     *   defs  = the per-break column names (from the Break Time setup, in start-time order,
     *           plus "Break N" for any extra pair a day shows beyond the configured set)
     *   rows  = each with 'slots' aligned to defs — slot = ['out'=>?, 'in'=>?, 'minutes'=>int] | null
     */
    private function timeCardRows(string $from, string $to, ?int $deptId, string $q, ?int $companyId = null): array
    {
        $db   = db_connect();

        // forRange() only takes a single department id, not a company — so
        // a Company filter (which can span several departments) is resolved
        // to an explicit employee-id list instead, which forRange() also
        // accepts and which composes with an also-picked Department filter.
        $empIds = [];
        if ($companyId || $deptId) {
            $b = $db->table('personnel_employee e')->select('e.id')
                ->join('personnel_department d', 'd.id = e.department_id', 'left');
            if ($companyId) {
                $b->where('d.company_id', $companyId);
            }
            if ($deptId) {
                $b->where('e.department_id', $deptId);
            }
            $empIds = array_column($b->get()->getResultArray(), 'id');
            if ($empIds === []) {
                // a real "no employees match this filter" — must not fall
                // through to forRange()'s own "[] = every employee" default.
                return ['defs' => [], 'payCodes' => $db->query('SELECT code, name, display_format FROM att_paycode ORDER BY display_order, id')->getResultArray(), 'rows' => []];
            }
        }
        $scan = (new AttendanceCalculator())->forRange($empIds, $from, $to);

        // per-timetable break links → the scheduled "Total Break" allowance,
        // each row carrying its own Start/End Date validity window.
        $byTt = [];
        foreach ($db->query(
            'SELECT l.timeinterval_id tt, b.duration, b.start_date, b.end_date
               FROM att_timeinterval_break_time l
               JOIN att_breaktime b ON b.id = l.breaktime_id'
        )->getResultArray() as $b) {
            $byTt[(int) $b['tt']][] = $b;
        }
        // breaks in $byTt[$ttId] still in effect on $date (blank Start/End = unbounded).
        $activeBreaks = static function (array $rows, string $date): array {
            return array_values(array_filter($rows, static function (array $b) use ($date): bool {
                if (! empty($b['start_date']) && $date < substr((string) $b['start_date'], 0, 10)) {
                    return false;
                }
                if (! empty($b['end_date']) && $date > substr((string) $b['end_date'], 0, 10)) {
                    return false;
                }

                return true;
            }));
        };

        // the Break Time setup itself — one column group per configured break
        $schedOffset = (int) (config('Attendance')->scheduleOffsetMinutes ?? 0);
        $conf       = $db->query('SELECT alias, period_start, duration FROM att_breaktime ORDER BY period_start, id')->getResultArray();
        $confStarts = array_map(fn ($b) => $this->hm(substr((string) $b['period_start'], 0, 5)) + $schedOffset, $conf);
        $allBreak   = array_sum(array_map(static fn ($b) => (int) $b['duration'], $conf));
        $defs       = array_map(static fn ($b) => $b['alias'], $conf);

        // pay-code accumulator columns (one per att_paycode, ZKBioTime order)
        $payCodes = $db->query('SELECT code, name, display_format FROM att_paycode ORDER BY display_order, id')->getResultArray();

        $rows     = [];
        $maxExtra = 0;
        foreach ($scan as $d) {
            $e = $d['employee'];
            if ($q !== '' && stripos($e['emp_code'] . ' ' . ($e['name'] ?? ''), $q) === false) {
                continue;
            }
            $hasPunch  = $d['punch_count'] > 0;
            $scheduled = $d['scheduled'] !== null;
            $isLeave   = in_array($d['status'], ['Leave', 'Half-day Leave'], true);
            // Weekend and Leave days stay visible as their own row (so the
            // report shows why nothing was worked instead of the date just
            // vanishing) — only genuinely nothing-to-report days drop out.
            if (! $hasPunch && ! $scheduled && ! $isLeave) {
                continue;
            }
            if (! $hasPunch && in_array($d['status'], ['Holiday', 'No Data'], true)) {
                continue;
            }

            $ttId  = $d['scheduled']['id'] ?? null;
            // A timetable with no break linked (or whose linked break isn't
            // in effect on this date per its Start/End Date) has NO break —
            // never treat a stray mid-shift punch as one on such a day.
            $ttBreaks    = $ttId !== null ? $activeBreaks($byTt[$ttId] ?? [], $d['date']) : [];
            $noBreakCfg  = $ttId !== null && $ttBreaks === [];
            // Break out/in pairs = the mid-shift punches (between clock-in and
            // clock-out), position-paired by the calculator — this is what
            // ZKBioTime's Break Out / Break In columns show. Fall back to
            // explicit state-2/3 punches when the calculator has none.
            $pairs = $noBreakCfg ? [] : (! empty($d['break_pairs']) ? $d['break_pairs'] : $this->breakPairs($d['punches']));
            $taken = $noBreakCfg ? 0 : ((int) ($d['break_taken_minutes'] ?? 0) ?: array_sum(array_column($pairs, 'minutes')));
            $gross = (int) ($d['total_minutes'] ?? 0) ?: $this->spanMinutes($d['first_in'], $d['last_out']);
            $slots = $noBreakCfg ? array_fill(0, count($conf), null) : $this->assignBreaks($pairs, $confStarts);
            $maxExtra = max($maxExtra, count($slots) - count($conf));

            // scheduled break allowance for the resolved timetable (ZKBio total_break_time)
            $sched = $ttId !== null
                ? array_sum(array_column($ttBreaks, 'duration'))
                : ($hasPunch ? $allBreak : 0);

            // ---- ZKBioTime interval maths — all from the calculator (calc_interval) ----
            $dutyGross = $scheduled ? (int) ($d['duty_gross_minutes'] ?: $d['scheduled']['work_minutes']) : 0;
            $dutyNet   = $scheduled ? (int) ($d['duty_net_minutes'] ?: $dutyGross) : 0;
            $onLeave   = ! empty($d['on_leave']);
            $late      = (int) ($d['late_minutes'] ?? 0);
            $early     = (int) ($d['early_minutes'] ?? 0);
            $ot        = (int) ($d['ot_minutes'] ?? 0);
            $leaveMin  = (int) ($d['leave_minutes'] ?? 0);
            $absentMin = (int) ($d['absent_minutes'] ?? 0);
            $incomplete = ! empty($d['is_incomplete']);
            $regular   = (int) ($d['regular_minutes'] ?? 0);
            $workedGross = (int) ($d['worked_minutes'] ?? ($regular + $ot));
            // "New logic" (business rule, not ZKBioTime): Worked Hours = Total
            // Hours (the raw clock-in→clock-out span) minus the full break
            // taken — every punched break minute comes off, not just the part
            // over the timetable's allowance. Regular / Duty / Remaining stay
            // ZKBioTime-aligned; only this column uses the business rule.
            $breakTaken  = (int) $taken;
            $totalHrs    = $incomplete ? 0 : $gross;
            $excessBreak = max(0, $breakTaken - (int) $sched);   // kept for the export column only
            $workedHrs   = max(0, $totalHrs - $breakTaken);

            $rows[] = [
                'emp'          => $e,
                'raw'          => $d,
                'date'         => $d['date'],
                'weekday'      => $d['weekday'],
                'timetable'    => $d['scheduled']['timetable'] ?? '',
                'check_in'     => $d['scheduled']['in'] ?? '',
                'check_out'    => $d['scheduled']['out'] ?? '',
                'duty_gross'   => $dutyGross,
                'duty_net'     => $dutyNet,
                'clock_in'     => $d['first_in'] ?? '',
                'clock_out'    => $d['last_out'] ?? '',
                'total_hrs'    => $incomplete ? 0 : $gross,     // raw punch span; blank when a punch is missing (ZKBioTime)
                'slots'        => $slots,
                'sched_break'  => $sched,
                'taken_break'  => $taken,
                'late_min'     => $late,
                'early_min'    => $early,
                'absent_min'   => $absentMin,
                'leave_min'    => $leaveMin,
                'ot_min'       => $ot,
                'ot_kind'      => $d['ot_kind'] ?? 'normal',
                'regular'      => $regular,
                'worked_gross' => $workedGross,                  // ZKBio: Regular + Total OT
                'worked_hrs'   => $workedHrs,                    // − break actually taken (business rule)
                'break_taken_min' => $breakTaken,
                'excess_break_min' => $excessBreak,
                'remaining'    => (int) ($d['remaining_minutes'] ?? max(0, $dutyNet - $leaveMin - $regular)),
                'present'      => (int) ($d['present'] ?? 0),
                'full_attendance' => ! empty($d['full_attendance']),
                'status'       => $d['status'],
                'status_class' => $d['status_class'],
            ];
        }

        for ($i = 1; $i <= $maxExtra; $i++) {
            $defs[] = 'Break ' . (count($conf) + $i);
        }
        $width = count($defs);
        foreach ($rows as &$r) {
            $r['slots'] = array_pad($r['slots'], $width, null);
        }
        unset($r);

        return ['defs' => $defs, 'rows' => $rows, 'payCodes' => $payCodes];
    }

    /**
     * Place the break pairs into the configured break columns IN ORDER — the
     * 1st break of the day → the 1st configured break, and so on (ZKBioTime
     * shows the day's first break in its single "Break Out"/"Break In" column).
     * Extra pairs beyond the configured breaks spill into appended slots.
     *
     * @param array<int,array{out:string,in:?string,minutes:int}> $pairs
     * @param list<int> $confStarts  configured break start times (unused; kept for signature stability)
     * @return list<array|null>
     */
    private function assignBreaks(array $pairs, array $confStarts): array
    {
        $n   = count($confStarts);
        $out = array_fill(0, max($n, 0), null);
        foreach (array_values($pairs) as $i => $pr) {
            if ($i < $n) {
                $out[$i] = $pr;
            } else {
                $out[] = $pr;
            }
        }

        return $out;
    }

    /** Minutes-of-day from "HH:MM". */
    private function hm(?string $t): int
    {
        if (! $t) {
            return 0;
        }
        [$h, $m] = array_map('intval', explode(':', substr($t, 0, 5)));

        return $h * 60 + $m;
    }

    /** Gross span first-in → last-out in minutes, handling an overnight clock-out. */
    private function spanMinutes(?string $in, ?string $out): int
    {
        if (! $in || ! $out || $in === $out) {
            return 0;
        }
        $a = $this->hm($in);
        $b = $this->hm($out);
        if ($b < $a) {
            $b += 1440;
        }

        return $b - $a;
    }

    /**
     * Break-out (state 2) / break-in (state 3) punch pairs. A dangling
     * break-out with no matching break-in is still listed (minutes 0).
     *
     * @param array<int,array{time:string,state:?int}> $punches
     * @return array<int,array{out:string,in:?string,minutes:int}>
     */
    private function breakPairs(array $punches): array
    {
        $pairs = [];
        $open  = null;
        foreach ($punches as $p) {
            if ($p['state'] === 2) {
                if ($open !== null) {
                    $pairs[] = ['out' => $open, 'in' => null, 'minutes' => 0];
                }
                $open = $p['time'];
            } elseif ($p['state'] === 3 && $open !== null) {
                $mo = $this->hm($open);
                $mi = $this->hm($p['time']);
                if ($mi < $mo) {
                    $mi += 1440;
                }
                $pairs[] = ['out' => $open, 'in' => $p['time'], 'minutes' => $mi - $mo];
                $open = null;
            }
        }
        if ($open !== null) {
            $pairs[] = ['out' => $open, 'in' => null, 'minutes' => 0];
        }

        return $pairs;
    }

    /** Raw device punches across a range — ZKBio's "Transaction" report. */
    public function transactions(): string
    {
        [$from, $to] = $this->range();
        $db = db_connect();
        $q  = trim((string) $this->request->getGet('q'));

        $builder = $db->table('iclock_transaction t')
            ->select("t.punch_time, t.emp_code, t.punch_state, t.verify_type, t.terminal_alias, t.terminal_sn, t.area_alias,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name, d.dept_name")
            ->join('personnel_employee e', 'e.emp_code = t.emp_code', 'left')
            ->join('personnel_department d', 'd.id = e.department_id', 'left')
            ->where('t.punch_time >=', $from . ' 00:00:00')
            ->where('t.punch_time <=', $to . ' 23:59:59');
        if ($q !== '') {
            $builder->groupStart()
                ->like('t.emp_code', $q)
                ->orLike('e.first_name', $q)
                ->orLike('e.last_name', $q)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults(false);
        $page  = max(1, (int) $this->request->getGet('page'));
        $per   = 100;
        $rows  = $builder->orderBy('t.punch_time', 'DESC')->limit($per, ($page - 1) * $per)->get()->getResultArray();

        return view('admin/reports/transactions', [
            'title' => 'Transactions', 'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'transactions',
            'from' => $from, 'to' => $to, 'q' => $q, 'rows' => $rows,
            'total' => $total, 'page' => $page, 'perPage' => $per,
            'states' => [0 => 'Check-In', 1 => 'Check-Out', 2 => 'Break-Out', 3 => 'Break-In', 4 => 'OT-In', 5 => 'OT-Out'],
        ]);
    }

    /** Roll-up of the daily scan by department or by employee — ZKBio's summary reports. */
    public function summary(): string
    {
        [$from, $to] = $this->range();
        $by   = $this->request->getGet('by') === 'employee' ? 'employee' : 'department';
        $scan = $this->scan($from, $to);

        $groups = [];
        foreach ($scan as $d) {
            $e   = $d['employee'];
            $key = $by === 'employee' ? $e['emp_code'] : ($e['dept_name'] ?? '—');
            $lbl = $by === 'employee' ? emp_name($e) . ' (' . $e['emp_code'] . ')' : ($e['dept_name'] ?? 'Unassigned');
            $g   = &$groups[$key];
            $g['label'] ??= $lbl;
            $g['work_days']   = ($g['work_days'] ?? 0) + (($d['scheduled'] !== null && ! $d['is_weekend'] && ! $d['is_holiday']) ? 1 : 0);
            $g['present']     = ($g['present'] ?? 0) + (AttendanceCalculator::isPresent($d['status']) ? 1 : 0);
            $g['absent']      = ($g['absent'] ?? 0) + ($d['status'] === 'Absent' ? 1 : 0);
            $g['leave']       = ($g['leave'] ?? 0) + ($d['on_leave'] ? 1 : 0);
            $g['late']        = ($g['late'] ?? 0) + ($d['late_minutes'] > 0 ? 1 : 0);
            $g['late_min']    = ($g['late_min'] ?? 0) + $d['late_minutes'];
            $g['early']       = ($g['early'] ?? 0) + ($d['early_minutes'] > 0 ? 1 : 0);
            $g['worked_min']  = ($g['worked_min'] ?? 0) + $d['worked_minutes'];
            $g['ot_min']      = ($g['ot_min'] ?? 0) + $d['ot_minutes'];
            unset($g);
        }
        ksort($groups);

        // grand totals over the whole result set (before paginating the display)
        $totals = array_fill_keys(['work_days', 'present', 'absent', 'leave', 'late', 'late_min', 'early', 'worked_min', 'ot_min'], 0);
        foreach ($groups as $g) {
            foreach ($totals as $k => $_) {
                $totals[$k] += $g[$k] ?? 0;
            }
        }

        [$groups, $pager] = $this->pagedArray(array_values($groups));

        return view('admin/reports/summary', [
            'title' => $by === 'employee' ? 'Employee Summary' : 'Department Summary',
            'subtitle' => "$from → $to", 'active' => 'reports', 'sub' => 'summary' . $by,
            'from' => $from, 'to' => $to, 'by' => $by, 'groups' => $groups, 'totals' => $totals, 'pager' => $pager,
        ]);
    }
}
