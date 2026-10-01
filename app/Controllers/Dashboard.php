<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $db   = db_connect();
        $calc = new AttendanceCalculator($db);
        [$today, $dept, $isLatest] = $this->filters();
        $snap  = $this->snapshot($db, $calc, $today, $dept);

        // 14-day trend — one batched calculator pass (page load only; the
        // live-poll endpoint only ever needs to refresh today's own bar).
        // Anchored to the selected date, not always "today".
        $trendFrom = date('Y-m-d', strtotime("$today -13 day"));
        $byDate    = [];
        foreach ($calc->forRange([], $trendFrom, $today, $dept) as $r) {
            $b = &$byDate[$r['date']];
            $b['present'] = ($b['present'] ?? 0)
                + (AttendanceCalculator::isPresent($r['status']) ? 1 : 0);
            $b['absent']  = ($b['absent'] ?? 0) + ($r['status'] === 'Absent' ? 1 : 0);
            $b['late']    = ($b['late'] ?? 0) + (in_array($r['status'], ['Late', 'Late & Early'], true) ? 1 : 0);
            $b['early']   = ($b['early'] ?? 0) + (in_array($r['status'], ['Early Out', 'Late & Early'], true) ? 1 : 0);
        }
        unset($b);
        $trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("$today -$i day"));
            $trend[] = [
                'date'    => date('M j', strtotime($d)),
                'present' => $byDate[$d]['present'] ?? 0,
                'absent'  => $byDate[$d]['absent'] ?? 0,
                'late'    => $byDate[$d]['late'] ?? 0,
                'early'   => $byDate[$d]['early'] ?? 0,
            ];
        }

        return view('admin/dashboard', $snap + [
            'title'      => 'Dashboard',
            'subtitle'   => 'Attendance overview for ' . date('l, j M Y', strtotime($today)),
            'active'     => 'dashboard',
            'today'      => $today,
            'dept'       => $dept,
            'isLatest'   => $isLatest,
            'departments'=> (new \App\Models\EmployeeModel())->departments(),
            'trend'      => $trend,
            'stateLabels'=> config('Attendance')->stateLabels,
        ]);
    }

    /** Polled every few seconds from the dashboard — same numbers as index(), as JSON, no page reload. */
    public function live()
    {
        $db   = db_connect();
        $calc = new AttendanceCalculator($db);
        [$today, $dept] = $this->filters();
        $snap  = $this->snapshot($db, $calc, $today, $dept);
        $c     = $snap['counts'];
        $rate  = $snap['totalEmp'] > 0 ? round(($c['Present'] + $c['Late']) / $snap['totalEmp'] * 100) : 0;
        $certExpired  = (int) ($snap['certAlerts']['expired'] ?? 0);
        $certExpiring = (int) ($snap['certAlerts']['expiring'] ?? 0);

        return $this->response->setHeader('Cache-Control', 'no-store')->setJSON([
            'date' => $today,
            'stats' => [
                'employees' => ['value' => $snap['totalEmp'], 'sub' => $dept ? 'In this department' : 'All employees'],
                'present'   => ['value' => $c['Present'] + $c['Late'], 'sub' => $rate . '% of ' . $snap['totalEmp'] . ' employees'],
                'absent'    => ['value' => $c['Absent'], 'sub' => $c['Incomplete'] . ' incomplete · ' . $c['Leave'] . ' on leave'],
                'late'      => ['value' => $c['Late'], 'sub' => mins($snap['lateMinutes']) . ' lost'],
                'ot'        => ['value' => mins($snap['otMinutes']), 'sub' => 'Across all staff'],
                'punches'   => ['value' => number_format($snap['totalPunch']), 'sub' => 'Raw device events on selected date'],
                'approvals' => ['value' => $snap['pendingApprovals'], 'sub' => 'Leave · punch · OT · web punch'],
                'cert'      => ['value' => $certExpired + $certExpiring, 'sub' => $certExpired . ' expired · ' . $certExpiring . ' expiring'],
            ],
            'todayTrend'  => $snap['todayTrend'],
            'presentDonut'  => $snap['presentDonut'],
            'scheduleDonut' => $snap['scheduleDonut'],
            'approvalDonut' => $snap['approvalDonut'],
            'deviceDonut'   => $snap['deviceDonut'],
            'devicesHtml' => view('admin/_dashboard_devices', ['devices' => $snap['devices']]),
            'recentHtml'  => view('admin/_dashboard_recent', ['recent' => $snap['recent'], 'stateLabels' => config('Attendance')->stateLabels]),
        ]);
    }

    /**
     * ?date= (defaults to literal today — per client spec, every module
     * defaults to the current date/month) and ?dept= from the request. An
     * employee whose shift hasn't started yet legitimately shows "No Data"
     * for today; that's correct information, not a bug, and the date field
     * itself still reads "today" so it's never mistaken for a stuck page.
     * @return array{0:string,1:?int,2:bool} [date, deptId, isViewingTheLatestDate]
     */
    private function filters(): array
    {
        $latest = date('Y-m-d');
        $date   = (string) $this->request->getGet('date');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date || $date > date('Y-m-d')) {
            $date = $latest;
        }
        $dept = (int) $this->request->getGet('dept') ?: null;

        // "live" whenever viewing the default day or literal today — both
        // can still gain new punches / corrections between polls. Only an
        // explicitly-picked older date is a frozen, non-live snapshot.
        $isLatest = $date === $latest || $date === date('Y-m-d');

        return [$date, $dept, $isLatest];
    }

    /** Everything that can change between polls (i.e. everything except the 14-day trend and employee count). */
    private function snapshot($db, AttendanceCalculator $calc, string $today, ?int $dept = null): array
    {
        $rows = $calc->forDate($today, $dept);

        $counts = ['Present' => 0, 'Late' => 0, 'Absent' => 0, 'Incomplete' => 0, 'Leave' => 0, 'Other' => 0];
        $bucketMap = [
            'present' => 'Present', 'early' => 'Present',
            'late' => 'Late', 'absent' => 'Absent',
            'incomplete' => 'Incomplete', 'leave' => 'Leave', 'off' => 'Other',
        ];
        $lateMinutes = $otMinutes = 0;
        $todayPresent = $todayAbsent = $todayLate = $todayEarly = 0;
        $scheduledDay = $notScheduled = 0;
        foreach ($rows as $r) {
            $counts[$bucketMap[AttendanceCalculator::bucket($r['status'])]]++;
            $lateMinutes += $r['late_minutes'];
            $otMinutes   += $r['ot_minutes'];
            $todayPresent += AttendanceCalculator::isPresent($r['status']) ? 1 : 0;
            $todayAbsent  += $r['status'] === 'Absent' ? 1 : 0;
            $todayLate    += in_array($r['status'], ['Late', 'Late & Early'], true) ? 1 : 0;
            $todayEarly   += in_array($r['status'], ['Early Out', 'Late & Early'], true) ? 1 : 0;
            if ($r['scheduled_day']) {
                $scheduledDay++;
            } else {
                $notScheduled++;
            }
        }

        // ZKBioTime's "Today" gauges: Present vs Absent, Scheduled vs Not,
        // and Approved / Pending / Rejected — all live (all-time, not
        // date-scoped, matching the existing pending-approvals stat card).
        $presentDonut = ['present' => $counts['Present'] + $counts['Late'], 'absent' => $counts['Absent']];
        $scheduleDonut = ['scheduled' => $scheduledDay, 'unscheduled' => $notScheduled];

        $approvalRows = $db->query(
            "SELECT w.approval_status, count(*) c FROM workflow_workflowinstance w
              WHERE EXISTS (
                    SELECT 1 FROM att_leave t        WHERE t.workflowinstance_ptr_id = w.id
                    UNION ALL SELECT 1 FROM att_manuallog t     WHERE t.workflowinstance_ptr_id = w.id
                    UNION ALL SELECT 1 FROM att_overtime t      WHERE t.workflowinstance_ptr_id = w.id
                    UNION ALL SELECT 1 FROM att_training t      WHERE t.workflowinstance_ptr_id = w.id
                    UNION ALL SELECT 1 FROM att_changeschedule t WHERE t.workflowinstance_ptr_id = w.id
                    UNION ALL SELECT 1 FROM att_webpunch t       WHERE t.workflowinstance_ptr_id = w.id
                    UNION ALL SELECT 1 FROM visitor_reservation t WHERE t.workflowinstance_ptr_id = w.id
                )
              GROUP BY w.approval_status"
        )->getResultArray();
        $approvalDonut = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
        $pendingApprovals = 0;
        foreach ($approvalRows as $ar) {
            $n = (int) $ar['c'];
            switch ((int) $ar['approval_status']) {
                case 1: $approvalDonut['approved'] = $n; break;
                case 0: $approvalDonut['pending']  = $n; $pendingApprovals = $n; break;
                case 2: $approvalDonut['rejected'] = $n; break;
            }
        }

        $certAlerts = \App\Controllers\Certifications::alertCounts($db);

        $devices = $db->query(
            "SELECT id, alias, sn, ip_address, last_activity, user_count, transaction_count,
                    (now() - last_activity < interval '10 minutes') AS online
               FROM iclock_terminal ORDER BY alias"
        )->getResultArray();
        $onlineCount = 0;
        foreach ($devices as $dv) {
            if ($dv['online'] === true || $dv['online'] === 't') {
                $onlineCount++;
            }
        }
        $deviceDonut = ['online' => $onlineCount, 'offline' => count($devices) - $onlineCount];

        $recentB = $db->table('iclock_transaction t')
            ->select("to_char(t.punch_time,'YYYY-MM-DD HH24:MI') tm, t.emp_code, t.punch_state,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name, term.alias device")
            ->join('personnel_employee e', 'e.id = t.emp_id', 'left')
            ->join('iclock_terminal term', 'term.id = t.terminal_id', 'left')
            ->where('t.punch_time >=', $today . ' 00:00:00')
            ->where('t.punch_time <', date('Y-m-d', strtotime("$today +1 day")) . ' 00:00:00')
            ->orderBy('t.punch_time', 'DESC')
            ->orderBy('t.id', 'DESC')
            ->limit(12);
        if ($dept) {
            $recentB->where('e.department_id', $dept);
        }
        $recent = $recentB->get()->getResultArray();

        $nextDay = date('Y-m-d', strtotime("$today +1 day"));
        $punchB  = $db->table('iclock_transaction t')
            ->where('t.punch_time >=', $today . ' 00:00:00')
            ->where('t.punch_time <', $nextDay . ' 00:00:00');
        if ($dept) {
            $punchB->join('personnel_employee e', 'e.id = t.emp_id')->where('e.department_id', $dept);
        }

        return [
            'totalPunch' => (int) $punchB->countAllResults(),
            'counts'     => $counts,
            'totalEmp'   => count($rows),
            'scheduled'  => $scheduledDay,
            'lateMinutes'=> $lateMinutes,
            'otMinutes'  => $otMinutes,
            'todayTrend' => ['present' => $todayPresent, 'absent' => $todayAbsent, 'late' => $todayLate, 'early' => $todayEarly],
            'presentDonut'  => $presentDonut,
            'scheduleDonut' => $scheduleDonut,
            'approvalDonut' => $approvalDonut,
            'deviceDonut'   => $deviceDonut,
            'pendingApprovals' => $pendingApprovals,
            'certAlerts' => $certAlerts,
            'devices'    => $devices,
            'recent'     => $recent,
        ];
    }
}
