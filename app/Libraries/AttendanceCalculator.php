<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Attendance as AttendanceConfig;
use App\Libraries\AttPolicy;

/**
 * Turns raw ZKTeco biometric punches (iclock_transaction) into daily
 * attendance rows: scheduled vs. actual in/out, late, early-out, short
 * time, overtime and a status.
 *
 * Schedule resolution for an (employee, date):
 *   1. personal schedule  – att_attschedule
 *   2. department schedule – att_departmentschedule via employee.department_id
 *   3. group schedule      – att_groupschedule via att_attemployee.group_id
 * The resolved shift's timetable is read from att_shiftdetail for that
 * week-day; when a shift has no detail rows (as in this dataset) the
 * company's single att_timeinterval is used for every working day.
 */
class AttendanceCalculator
{
    private BaseConnection $db;
    private AttendanceConfig $cfg;
    private AttPolicy $pol;

    /** @var array<string,array<int,array>>|null cache: approved changeschedule rows by "empId-date" */
    private ?array $changeSchedCache = null;
    /** @var array<int,array<string,array>>|null cache: approved overtime rows  empId => date => rows */
    private ?array $otReqCache = null;

    /** @var array<int,array> cache: timetable id => row */
    private array $timetables = [];
    /** @var array<int,array<int>> cache: timetable id => [unpaid break minutes...] */
    private array $breakCache = [];
    /** @var array<int,array> cache: empId => personal schedule rows */
    private array $schedCache = [];
    /** @var array<int,array>|null cache: dept schedule rows */
    private ?array $deptSchedCache = null;
    /** @var array<int,array>|null cache: company schedule rows */
    private ?array $companySchedCache = null;
    /** @var array<int,?int> cache: department_id => company_id */
    private array $deptCompanyCache = [];
    /** @var array<int,array<int,int>> cache: shift id => day_index => timetable id */
    private array $shiftDetailCache = [];
    /** last calendar date that has any punch – days after this are "No Data". */
    private ?string $horizon = null;

    public function __construct(?BaseConnection $db = null, ?AttendanceConfig $cfg = null)
    {
        $this->db  = $db ?? db_connect();
        $this->cfg = $cfg ?? config('Attendance');
        $this->pol = AttPolicy::load($this->db);
        // Make wall-clock comparisons match the shift definition.
        try {
            $this->db->query("SET TIME ZONE '" . $this->pol->timezone . "'");
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    /**
     * The cut-off after which a day is "No Data" rather than computed.
     * Per client spec, every module defaults to (and computes) the current
     * date — an employee with no punch today is Absent, not blank. Only
     * genuinely future dates (tomorrow onward) stay "No Data" unless they
     * already carry punches (e.g. a pre-logged leave/holiday).
     */
    public function horizon(): string
    {
        return $this->horizon ??= date('Y-m-d');
    }

    // ------------------------------------------------------------------
    //  Public API
    // ------------------------------------------------------------------

    /**
     * Daily rows for one employee across an inclusive date range.
     *
     * @return array<int,array> one row per calendar day
     */
    public function forEmployee(int $empId, string $from, string $to): array
    {
        $emp = $this->employee($empId);
        if ($emp === null) {
            return [];
        }

        $pool      = $this->punchPool([$empId], $from, $to);
        $holidays  = $this->holidays($from, $to);
        $leaves    = $this->leaves([$empId], $from, $to);

        $rows   = [];
        $cursor = strtotime($from);
        $end    = strtotime($to);
        while ($cursor <= $end) {
            $date  = date('Y-m-d', $cursor);
            $rows[] = $this->buildDay(
                $emp,
                $date,
                $pool[$empId] ?? [],
                $holidays,
                $leaves[$empId] ?? []
            );
            $cursor = strtotime('+1 day', $cursor);
        }

        return $rows;
    }

    /**
     * One row per employee for a single date (optionally a department).
     *
     * @return array<int,array>  each row = day row + 'employee' key
     */
    public function forDate(string $date, ?int $deptId = null): array
    {
        $employees = $this->employees($deptId);
        if ($employees === []) {
            return [];
        }
        $ids       = array_column($employees, 'id');
        $pool      = $this->punchPool($ids, $date, $date);
        $holidays  = $this->holidays($date, $date);
        $leaves    = $this->leaves($ids, $date, $date);

        $out = [];
        foreach ($employees as $emp) {
            $row             = $this->buildDay(
                $emp,
                $date,
                $pool[$emp['id']] ?? [],
                $holidays,
                $leaves[$emp['id']] ?? []
            );
            $row['employee'] = $emp;
            $out[]           = $row;
        }

        return $out;
    }

    /**
     * Daily rows for MANY employees across a range in one batch (no N+1).
     * Punches / holidays / leaves are loaded once and reused.
     *
     * @param int[] $empIds  empty = every active-list employee
     * @return array<int,array>  each row = day row + 'employee' key
     */
    public function forRange(array $empIds, string $from, string $to, ?int $deptId = null): array
    {
        $employees = $empIds === []
            ? $this->employees($deptId)
            : array_values(array_filter(array_map(fn ($id) => $this->employee((int) $id), $empIds)));
        if ($employees === []) {
            return [];
        }
        $ids      = array_column($employees, 'id');
        $pool     = $this->punchPool($ids, $from, $to);
        $holidays = $this->holidays($from, $to);
        $leaves   = $this->leaves($ids, $from, $to);

        $out    = [];
        $end    = strtotime($to);
        foreach ($employees as $emp) {
            $cursor = strtotime($from);
            while ($cursor <= $end) {
                $date = date('Y-m-d', $cursor);
                $row  = $this->buildDay(
                    $emp,
                    $date,
                    $pool[$emp['id']] ?? [],
                    $holidays,
                    $leaves[$emp['id']] ?? []
                );
                $row['employee'] = $emp;
                $out[]           = $row;
                $cursor          = strtotime('+1 day', $cursor);
            }
        }

        return $out;
    }

    /**
     * Month grid + totals for one employee.
     */
    public function monthly(int $empId, int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));
        $days = $this->forEmployee($empId, $from, $to);

        $t = [
            'work_days'        => 0,
            'present_days'     => 0,
            'absent_days'      => 0,
            'late_days'        => 0,
            'early_days'       => 0,
            'incomplete'       => 0,  // legacy alias
            'incomplete_days'  => 0,
            'leave_days'       => 0,
            'paid_leave_days'  => 0.0,
            'unpaid_leave_days'=> 0.0,
            'holiday_days'     => 0,
            'weekend_days'     => 0,
            'worked_minutes'   => 0,
            'ot_minutes'       => 0,
            'ot_normal'        => 0,
            'ot_weekend'       => 0,
            'ot_holiday'       => 0,
            'late_minutes'     => 0,
            'early_minutes'    => 0,
            'short_minutes'    => 0,
            'worked_day_fraction' => 0.0,  // ZKBioTime "workday" sum — paid-day equivalent
        ];
        foreach ($days as $d) {
            $t['worked_minutes'] += $d['worked_minutes'];
            $t['ot_minutes']     += $d['ot_minutes'];
            $t['ot_' . ($d['ot_kind'] ?? 'normal')] += $d['ot_minutes'];
            $t['late_minutes']   += $d['late_minutes'];
            $t['early_minutes']  += $d['early_minutes'];
            $t['short_minutes']  += $d['short_minutes'];
            $t['worked_day_fraction'] += $d['work_day_fraction'] ?? 0;
            if (($d['scheduled_day'] ?? $d['scheduled'] !== null) && ! $d['is_weekend'] && ! $d['is_holiday']) {
                $t['work_days']++;
            }
            if (self::isPresent($d['status'])) {
                $t['present_days']++;
            }
            if ($d['status'] === 'Absent') {
                $t['absent_days']++;
            }
            if ($d['late_minutes'] > 0) {
                $t['late_days']++;
            }
            if ($d['early_minutes'] > 0) {
                $t['early_days']++;
            }
            if ($d['status'] === 'Incomplete') {
                $t['incomplete_days']++;
            }
            if ($d['on_leave']) {
                $frac = $d['half_day'] ? round($d['leave_fraction'], 2) : 1;
                $t['leave_days'] += $frac;
                if ($d['is_paid_leave'] ?? true) {
                    $t['paid_leave_days'] += $frac;
                } else {
                    $t['unpaid_leave_days'] += $frac;
                }
            }
            if ($d['is_holiday']) {
                $t['holiday_days']++;
            }
            if ($d['is_weekend']) {
                $t['weekend_days']++;
            }
        }

        // An Incomplete day (single punch) is paid per the configured policy so
        // payroll no longer silently ignores it.
        $inc = $t['incomplete_days'];
        $t['incomplete'] = $inc; // keep legacy key in sync
        if ($inc > 0) {
            if ($this->pol->incompletePolicy === 'present') {
                $t['present_days'] += $inc;
            } elseif ($this->pol->incompletePolicy === 'absent') {
                $t['absent_days'] += $inc;
            } else { // half
                $t['present_days'] += (int) floor($inc / 2);
                $t['absent_days']  += (int) ceil($inc / 2);
            }
        }
        $t['worked_day_fraction'] = round($t['worked_day_fraction'], 2);
        $t['paid_leave_days']   = round($t['paid_leave_days'], 2);
        $t['unpaid_leave_days'] = round($t['unpaid_leave_days'], 2);

        return ['from' => $from, 'to' => $to, 'days' => $days, 'totals' => $t];
    }

    // ------------------------------------------------------------------
    //  Day builder
    // ------------------------------------------------------------------

    private function buildDay(array $emp, string $date, array $pool, array $holidays, array $leaves): array
    {
        $ts       = strtotime($date);
        $weekday  = (int) date('w', $ts); // 0 Sun .. 6 Sat
        $isWeekend = in_array($weekday, $this->pol->weekendDays, true);

        $holiday     = $this->matchHoliday($holidays, $date, $emp);
        $isHoliday   = $holiday !== null;
        $leave       = $this->matchLeave($leaves, $date);
        $onLeave     = $leave !== null;

        // An approved schedule-change request (att_changeschedule) overrides the
        // resolved timetable for this one date.
        $override = $this->changeScheduleFor((int) $emp['id'], $date);
        $sched = $override !== null
            ? ['timetable' => $this->timetable($override), 'flexible' => false, 'scheduled' => true]
            : $this->resolveSchedule((int) $emp['id'], (int) ($emp['department_id'] ?? 0), $date, $weekday);
        $timetable = $sched['timetable'];
        $flexible  = $sched['flexible'];   // ZKBioTime "flexible day" — span-only, no shift maths
        $scheduled = $sched['scheduled'];  // employee is expected to work this date

        // Match punches to THIS scheduled day the way ZKBioTime's punch_match
        // does — by the timetable's check-in/check-out windows, so a night
        // shift's next-morning clock-out lands on the day the shift started.
        $punches = $this->selectDayPunches($pool, $date, $timetable, $flexible, $scheduled);

        // Days past the last device report are "No Data" — UNLESS a holiday or
        // approved leave is already known for that date, which we still render.
        if ($date > $this->horizon() && $punches === [] && ! $isHoliday && ! $onLeave) {
            return $this->emptyRow($date, $ts, 'No Data', 'muted');
        }
        // fraction of the day covered by leave: 1.0 = full day, 0.5 = half day
        $leaveFraction = 0.0;
        if ($onLeave) {
            $span = max(1, (int) round((strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400) + 1);
            $leaveFraction = $leave['leave_day'] !== null && $leave['leave_day'] > 0
                ? min(1.0, max(0.1, (float) $leave['leave_day'] / $span))
                : 1.0;
        }
        $fullLeave = $onLeave && $leaveFraction >= 0.999;

        // ---- normalise punches ----
        usort($punches, static fn ($a, $b) => strcmp($a['punch_time'], $b['punch_time']));
        $mapped = array_map(function ($p) {
            $state = $p['punch_state'] === null ? null : (int) $p['punch_state'];
            return [
                'time'   => substr($p['punch_time'], 11, 5),
                'full'   => $p['punch_time'],
                'state'  => $state,
                'label'  => $this->pol->stateLabels[$state] ?? 'Punch',
                'device' => $p['terminal_alias'] ?? $p['terminal_sn'] ?? '—',
            ];
        }, $punches);

        $row = [
            'date'          => $date,
            'weekday'       => date('D', $ts),
            'is_weekend'    => $isWeekend,
            'is_holiday'    => $isHoliday,
            'holiday_name'  => $holiday['alias'] ?? null,
            'on_leave'      => $onLeave,
            'leave_fraction'=> $leaveFraction,
            'half_day'      => $onLeave && ! $fullLeave,
            'leave_type'    => $leave['pay_code'] ?? ($onLeave ? 'Leave' : null),
            'is_paid_leave' => ! $onLeave || ($leave['is_paid'] ?? true),
            'scheduled'     => $timetable === null ? null : [
                'id'            => $timetable['id'],
                'in'            => $timetable['in'],
                'out'           => $timetable['out'],
                'work_minutes'  => $timetable['work_minutes'],
                'work_net'      => $timetable['work_time_duration'],
                'work_day'      => $timetable['work_day'],
                'timetable'     => $timetable['alias'],
                'allow_late'    => $timetable['allow_late'],
                'allow_early'   => $timetable['allow_early'],
            ],
            'punches'       => $mapped,
            'punch_count'   => count($mapped),
            'first_in'      => null,
            'last_out'      => null,
            'worked_minutes'=> 0,
            'total_minutes' => 0,
            'break_minutes' => 0,
            'break_taken_minutes' => 0,
            'break_pairs'   => [],
            'late_minutes'  => 0,
            'early_minutes' => 0,
            'short_minutes' => 0,
            'unschedule_minutes' => 0,
            'in_ot_minutes' => 0,
            'out_ot_minutes' => 0,
            'miss_minutes'  => 0,
            'regular_minutes' => 0,
            'absent_minutes' => 0,
            'leave_minutes' => 0,
            'remaining_minutes' => 0,
            'duty_gross_minutes' => $timetable !== null ? (int) $timetable['work_minutes'] : 0,
            'duty_net_minutes' => $timetable !== null ? (int) ($timetable['work_time_duration'] ?: $timetable['work_minutes']) : 0,
            'break_sched_minutes' => 0,
            'is_incomplete' => false,
            'present'       => 0,
            'full_attendance' => false,
            'ot_minutes'    => 0,
            'status'        => 'Not Scheduled',
            'status_class'  => 'muted',
        ];

        // ---- pair punches to check-in / check-out ----
        // Fixed shift → ZKBioTime punch_match: the check-in is the earliest
        // punch in the shift's first half, the check-out the latest punch that
        // reaches the check-out window (out_time − out_ahead_margin). A punch
        // that never reaches that window is NOT a check-out — the day is a
        // miss_out. Flexible / unscheduled days keep the state-based rule.
        $firstInFull = $lastOutFull = null;
        $inP = $outP = null;
        $sIn = null;   // shift check-in, seconds from midnight of $ts — reused below to anchor break windows
        if ($mapped !== []) {
            if ($timetable !== null && $scheduled) {
                $sIn  = $this->minutes($timetable['in']) * 60;
                $sOut = $this->minutes($timetable['out']) * 60;
                if ($sOut <= $sIn) {
                    $sOut += 86400;
                }
                // ZKBioTime pairing windows: a check-in must land in
                // [in − in_ahead_margin, in + in_above_margin] and a check-out in
                // [out − out_ahead_margin, out + out_above_margin]. A punch
                // outside its window is NOT that clock event (→ miss_in / miss_out).
                $inLo  = $sIn  - (int) ($timetable['in_ahead_margin']  ?? 0) * 60;
                $inHi  = $sIn  + (int) ($timetable['in_above_margin']  ?? 0) * 60;
                $outLo = $sOut - (int) ($timetable['out_ahead_margin'] ?? 0) * 60;
                $outHi = $sOut + (int) ($timetable['out_above_margin'] ?? 0) * 60;
                foreach ($mapped as $p) {
                    $sec = strtotime($p['full']) - $ts;
                    if ($sec >= $inLo && $sec <= $inHi) {
                        $inP ??= $p;
                    }
                    if ($sec >= $outLo && $sec <= $outHi && $p !== $inP) {
                        $outP = $p;
                    }
                }
            } else {
                $ins  = array_filter($mapped, static fn ($p) => in_array($p['state'], [0, 4], true));
                $outs = array_filter($mapped, static fn ($p) => in_array($p['state'], [1, 5], true));
                $inP  = $ins  !== [] ? reset($ins)  : $mapped[0];
                $outP = $outs !== [] ? end($outs)   : end($mapped);
                if ($inP === $outP && count($mapped) >= 2) {
                    $outP = end($mapped);
                }
            }
            if ($inP !== null) {
                $row['first_in'] = $inP['time'];
                $firstInFull     = $inP['full'];
            }
            if ($outP !== null && $outP !== $inP) {
                $row['last_out'] = $outP['time'];
                $lastOutFull     = $outP['full'];
            }
        }

        $hasBothPunches = $inP !== null && $outP !== null && $inP !== $outP;

        // Break out/in — window-based, mirroring the Timetable's Check-In /
        // Check-Out pairing exactly: each active break defines its own
        // Break-Out window (around period_start) and Break-In window (around
        // period_start + duration), each a ± margin pair. A punch outside
        // every active break's windows is not a break punch at all — so a
        // timetable with no break linked (or whose break isn't in effect on
        // $date per its Start/End Date) never shows break time, and a stray
        // mid-shift punch that lands nowhere near a configured window is
        // left alone rather than guessed at positionally.
        $activeBreaks = ($timetable !== null && $scheduled && $sIn !== null)
            ? $this->activeBreakRows($timetable['id'] ?? null, $date)
            : [];
        if (($inP !== null || $outP !== null) && $activeBreaks !== []) {
            usort($activeBreaks, static fn ($a, $b) => strcmp((string) $a['period_start'], (string) $b['period_start']));
            $claimed = [];
            $bt      = 0;
            $bpairs  = [];
            foreach ($activeBreaks as $b) {
                // anchor the break's own start-of-day time-of-day into this
                // shift's timeline — same rollover rule as $sOut above, for
                // an overnight shift whose break falls after midnight.
                $boAnchor = $this->minutes(substr((string) $b['period_start'], 0, 5)) * 60;
                if ($boAnchor < $sIn) {
                    $boAnchor += 86400;
                }
                $biAnchor = $boAnchor + (int) $b['duration'] * 60;
                $boLo = $boAnchor - (int) $b['break_out_ahead_margin'] * 60;
                $boHi = $boAnchor + (int) $b['break_out_above_margin'] * 60;
                $biLo = $biAnchor - (int) $b['break_in_ahead_margin'] * 60;
                $biHi = $biAnchor + (int) $b['break_in_above_margin'] * 60;

                $boP = $biP = null;
                foreach ($mapped as $p) {
                    if ($p === $inP || $p === $outP || in_array($p, $claimed, true)) {
                        continue;
                    }
                    $sec = strtotime($p['full']) - $ts;
                    if ($sec >= $boLo && $sec <= $boHi) {
                        $boP ??= $p;
                    }
                    if ($sec >= $biLo && $sec <= $biHi && $p !== $boP) {
                        $biP = $p;
                    }
                }
                if ($boP !== null) {
                    $claimed[] = $boP;
                }
                if ($biP !== null) {
                    $claimed[] = $biP;
                }
                if ($boP !== null || $biP !== null) {
                    $mins = ($boP !== null && $biP !== null)
                        ? max(0, intdiv(strtotime($biP['full']) - strtotime($boP['full']), 60))
                        : 0;
                    $bt      += $mins;
                    $bpairs[] = ['out' => $boP['time'] ?? null, 'in' => $biP['time'] ?? null, 'minutes' => $mins];
                }
            }
            $row['break_taken_minutes'] = $bt;
            $row['break_pairs']         = $bpairs;
        }

        if ($hasBothPunches) {
            $inMin  = $this->minutes($row['first_in']);
            $outMin = $this->minutes($row['last_out']);
            if ($outMin < $inMin) {
                $outMin += 1440; // overnight
            }
            // ZKBioTime floors the span to whole minutes using the punch
            // seconds (07:30:12 → 21:00:07 = 809m, not 810). Match that.
            $gross = ($firstInFull && $lastOutFull)
                ? max(0, intdiv(strtotime($lastOutFull) - strtotime($firstInFull), 60))
                : $outMin - $inMin;
            $row['total_minutes'] = $gross;   // ZKBioTime "Total Hrs" — raw span, seconds floored

            // ZKBioTime never deducts breaks from the raw span itself —
            // calc_flex_att_time (flexible days) has no break handling at
            // all, and for a scheduled day the paid/unpaid split is applied
            // in the Duty Duration formula below (interval_duration −
            // total_break_time + paid_break), which replaces worked_minutes
            // with Regular there. This gross value is the FINAL worked
            // minutes only for a flexible day (no timetable to apply that
            // formula against).
            $row['worked_minutes'] = $gross;
        }

        // ---- comparisons against the schedule (ZKBioTime calc_interval) ----
        if ($timetable !== null && $scheduled) {
            $schedIn  = $this->minutes($timetable['in']);
            $schedOut = $this->minutes($timetable['out']);
            if ($schedOut <= $schedIn) {
                $schedOut += 1440;
            }
            // ZKBioTime grace = the timetable's Allow Late-In / Allow Early-Out
            // ONLY (the Check-In/Out windows are a pairing net, not a grace),
            // plus an optional admin nudge (0 by default).
            $lateGrace  = $timetable['allow_late']  + $this->pol->extraLateGrace;
            $earlyGrace = $timetable['allow_early'] + $this->pol->extraEarlyGrace;

            // ZKBioTime calc_interval:
            //   interval_duration  = flex ? work_time_duration : duration   (raw span)
            //   total_break_time   = Σ all linked breaks' duration
            //   paid_break         = Σ (with_salary) breaks' duration
            //   interval_work_time = interval_duration − total_break_time + paid_break
            [$totalBreak, $paidBreak] = $this->breakTotals($timetable['id'] ?? null, $date);
            $intervalDuration = (int) ($flexible
                ? ($timetable['work_time_duration'] ?: $timetable['work_minutes'])
                : $timetable['work_minutes']);
            $intervalWorkTime = max(0, $intervalDuration - $totalBreak + $paidBreak);
            $dutyNet = $intervalWorkTime;
            $row['duty_gross_minutes']  = $intervalDuration;
            $row['duty_net_minutes']    = $isHoliday ? 0 : $intervalWorkTime;
            $row['break_sched_minutes'] = $totalBreak;

            // total_leave_time — minutes of the interval covered by approved leave
            $leaveMin = $row['on_leave']
                ? min($intervalWorkTime, (int) round($leaveFraction * $intervalWorkTime))
                : 0;

            $lateToAbsent = $earlyToAbsent = 0;   // late/early minutes redirected to Absence
            $isAbsent = $isIncomplete = false;

            if ($hasBothPunches) {
                // Work in seconds-from-midnight off the real punch timestamps and
                // floor to whole minutes, the way ZKBioTime does — so 08:37:38
                // vs a 09:00 clock-out is 22 minutes early, not 23.
                $inSec  = $firstInFull ? strtotime($firstInFull) - $ts : $this->minutes($row['first_in']) * 60;
                $outSec = $lastOutFull ? strtotime($lastOutFull)  - $ts : $this->minutes($row['last_out'])  * 60;
                if ($outSec < $inSec) {
                    $outSec += 86400;
                }
                $schedInS  = $schedIn  * 60;
                $schedOutS = $schedOut * 60;
                // lateness first, then Compensate forgives up to N minutes of it
                // AND pushes the expected clock-out out by the same amount.
                $rawLate     = max(0, intdiv(max(0, $inSec - ($schedInS + $lateGrace * 60)), 60));
                $compensated = min($rawLate, $timetable['compensate']);
                $row['late_minutes']  = $rawLate - $compensated;
                $row['early_minutes'] = max(0, intdiv(max(0, ($schedOutS + ($compensated - $earlyGrace) * 60) - $outSec), 60));
                // ZKBioTime "Unscheduled" = worked time OUTSIDE the scheduled
                // window (arrived early + left late), not credited as Regular.
                // Kept split (calc_att_time's in_ot / out_ot) because ZKBioTime
                // gates unscheduled-OT separately per side — see the OT block.
                $row['in_ot_minutes']  = max(0, intdiv(max(0, $schedInS - $inSec), 60));
                $row['out_ot_minutes'] = max(0, intdiv(max(0, $outSec - $schedOutS), 60));
                $row['unschedule_minutes'] = $row['in_ot_minutes'] + $row['out_ot_minutes'];
                // late_in2absence / early_out2absence — over the threshold the
                // penalty is booked as Absence, not Late/Early (calc_att_time).
                if ($this->pol->lateToAbsence > 0 && $row['late_minutes'] > $this->pol->lateToAbsence) {
                    $lateToAbsent = $row['late_minutes'];
                    $row['late_minutes'] = 0;
                }
                if ($this->pol->earlyToAbsence > 0 && $row['early_minutes'] > $this->pol->earlyToAbsence) {
                    $earlyToAbsent = $row['early_minutes'];
                    $row['early_minutes'] = 0;
                }
            } elseif ($inP !== null || $outP !== null) {
                // one side never reached its window — ZKBioTime miss_in / miss_out.
                //   mode 1 → book late_in_hrs / early_out_hrs as Late-In / Early-Out
                //   mode 2 → the day is Absent
                //   mode 0 → the day is Incomplete
                $solo    = $outP ?? $inP;
                $onlySec = strtotime($solo['full']) - $ts;
                $missingIn = $inP === null;
                if ($missingIn) {
                    $mode     = $this->pol->missInMode;
                    $earlyOut = max(0, intdiv(max(0, $schedOut * 60 - $onlySec), 60));
                    if ($mode === 2) {
                        $isAbsent = true;
                    } elseif ($mode === 0) {
                        $isIncomplete = true;
                    } else {
                        $row['miss_minutes'] = $this->pol->missInMinutes;
                        $row['late_minutes'] = $this->pol->missInMinutes;   // paycode_2
                    }
                    $row['early_minutes']      = $earlyOut;                 // left early as well
                    $row['unschedule_minutes'] = max(0, intdiv(max(0, $onlySec - $schedOut * 60), 60));
                    $row['first_in'] = null;
                    $row['last_out'] = $solo['time'];
                    $firstInFull = null;
                    $lastOutFull = $solo['full'];
                } else {
                    $mode   = $this->pol->missOutMode;
                    $lateIn = max(0, intdiv(max(0, $onlySec - $schedIn * 60), 60));
                    if ($mode === 2) {
                        $isAbsent = true;
                    } elseif ($mode === 0) {
                        $isIncomplete = true;
                    } else {
                        $row['miss_minutes']  = $this->pol->missOutMinutes;
                        $row['early_minutes'] = $this->pol->missOutMinutes;  // paycode_3
                    }
                    $row['late_minutes']       = $lateIn;                    // arrived late as well
                    $row['unschedule_minutes'] = max(0, intdiv(max(0, $schedIn * 60 - $onlySec), 60));
                    $row['first_in'] = $solo['time'];
                    $row['last_out'] = null;
                    $lastOutFull = null;
                    $firstInFull = $solo['full'];
                }
            } elseif ($mapped !== []) {
                // punches exist but none reached either pairing window → Absent
                $row['first_in'] = $row['last_out'] = null;
                $firstInFull = $lastOutFull = null;
                $row['no_pair'] = true;
                $isAbsent = true;
            } else {
                // scheduled day, no punches at all → Absent
                $isAbsent = true;
            }

            // ---- ZKBioTime calc_interval paycode assembly ----
            // absent minutes = interval_duration − total_break_time − total_leave
            //   (so a paid break still credits Regular even on an absent day)
            $absentMin = $isAbsent
                ? max(0, $intervalDuration - $totalBreak - $leaveMin)
                : 0;
            $absentMin += $lateToAbsent + $earlyToAbsent;

            $exception = $absentMin + $row['late_minutes'] + $row['early_minutes'];

            if ($isIncomplete) {
                $row['regular_minutes'] = 0;
                $row['total_minutes']   = 0;
                $absentMin              = 0;
            } else {
                $row['regular_minutes'] = max(0, $intervalWorkTime - $exception - $leaveMin);
            }
            $row['absent_minutes']    = $absentMin;
            $row['leave_minutes']     = $leaveMin;
            $row['is_incomplete']     = $isIncomplete;
            $row['worked_minutes']    = $row['regular_minutes'];   // OT added below
            $row['short_minutes']     = max(0, $intervalWorkTime - $row['regular_minutes']);
            $row['remaining_minutes'] = max(0, $row['duty_net_minutes'] - $leaveMin - $row['regular_minutes']);

            // present / full_attendance (calc_full_attendance_present)
            $hasUnpaidLeave = $row['on_leave'] && ! ($row['is_paid_leave'] ?? true);
            $row['present'] = ($absentMin > 0 || $hasUnpaidLeave) ? -1 : 1;
            $row['full_attendance'] = $hasBothPunches
                && $row['late_minutes'] === 0 && $row['early_minutes'] === 0
                && $absentMin === 0 && ! $hasUnpaidLeave;
        }

        // ---- overtime (ZKBioTime calc_att_time / calc_interval — no global
        //      "OT threshold" or "OT rounding" exists in the real engine;
        //      every gate below is a per-timetable field) ----
        $row['ot_kind'] = 'normal';
        $reqOt          = $this->overtimeRequestMinutes((int) $emp['id'], $date); // approved att_overtime for the day
        $maxOt          = ($timetable !== null && $timetable['enable_max_ot']) ? $timetable['max_ot_limit'] : 0;

        // att_attpolicy.overtime_policy: -1 = Ignore overtime (nothing below
        // credits anything, including approved requests), 1 = Needs approval
        // (only an approved att_overtime request counts — the automatic
        // per-side / weekend / holiday gates never fire on their own),
        // 2 = Auto-approve worked overtime (the automatic gates apply, same
        // as before this switch existed).
        $otPolicy      = $this->pol->overtimePolicy;
        $autoOtAllowed = $this->pol->overtimeEnabled && $otPolicy === 2;
        $reqOtAllowed  = $this->pol->overtimeEnabled && $otPolicy !== -1;

        if ($hasBothPunches && $timetable !== null && ! $flexible && ($isWeekend || $isHoliday)) {
            // Weekend/holiday work — the whole worked span is OT candidate,
            // gated by the policy's weekend_ot/holiday_ot switch, no minimum.
            $target = max(0, $timetable['work_minutes'] - $this->scheduledUnpaidTotal($timetable['id'], $date));
            if ($row['half_day']) {
                $target = (int) round($target * (1 - $leaveFraction));
            }
            $dayGate = $isHoliday ? $this->pol->holidayOtEnabled : $this->pol->weekendOtEnabled;
            if ($autoOtAllowed && $dayGate) {
                $base = max(0, $row['worked_minutes'] - $target);
                if ($maxOt > 0) {
                    $base = min($base, $maxOt);
                }
                if ($base > 0) {
                    $row['ot_minutes'] = $base;
                    $row['ot_kind']    = $isHoliday ? 'holiday' : 'weekend';
                }
            }
        } elseif ($hasBothPunches && $timetable !== null && ! $flexible) {
            // Normal scheduled day — unscheduled OT is gated PER SIDE by the
            // timetable's own enable_early_in/min_early_in and
            // enable_late_out/min_late_out (calc_att_time in_ot / out_ot),
            // plus the policy's own Daily OT switch.
            $credited = 0;
            $dailyGate = $autoOtAllowed && $this->pol->dailyOtEnabled;
            if ($dailyGate && $timetable['enable_early_in']
                && $row['in_ot_minutes'] >= $timetable['min_early_in']) {
                $credited += $timetable['count_early_in_interval']
                    ? $row['in_ot_minutes']
                    : max(0, $row['in_ot_minutes'] - $timetable['min_early_in']);
            }
            if ($dailyGate && $timetable['enable_late_out']
                && $row['out_ot_minutes'] >= $timetable['min_late_out']) {
                $credited += $timetable['count_late_out_interval']
                    ? $row['out_ot_minutes']
                    : max(0, $row['out_ot_minutes'] - $timetable['min_late_out']);
            }
            if ($maxOt > 0) {
                $credited = min($credited, $maxOt);
            }
            if ($credited > 0) {
                $row['ot_minutes'] = $credited;
                $row['unschedule_minutes'] = max(0, (int) $row['unschedule_minutes'] - $credited);
                $row['worked_minutes']    += $credited;
            }
        }

        // An approved OT request (att_overtime, a TimeTrack Requests-workflow
        // feature with no direct calc_att_time equivalent) authorises at
        // least its requested minutes regardless of the gates above.
        if ($reqOtAllowed && $reqOt > 0 && $hasBothPunches) {
            $extra = max(0, min($reqOt, (int) ($row['unschedule_minutes'] ?? 0)) - $row['ot_minutes']);
            if ($extra > 0) {
                $row['ot_minutes']        += $extra;
                $row['unschedule_minutes'] = max(0, (int) $row['unschedule_minutes'] - $extra);
                $row['worked_minutes']    += $extra;
            }
            if ($row['ot_minutes'] > 0 && $row['ot_kind'] === 'normal' && ! $isWeekend && ! $isHoliday) {
                $row['ot_kind'] = 'normal';
            }
        }

        // ---- workday fraction (ZKBioTime "workday" column) ----
        // flexible: worked / att_attpolicy.max_hrs, to 1 dp (ZKBioTime).
        // fixed shift: worked minutes / scheduled span, to 1 dp — matches the
        // "workday" ZKBioTime writes on paycode_1 (e.g. 660/720 → 0.9).
        $ttWorkDay = $timetable['work_day'] ?? 1.0;
        if ($row['worked_minutes'] > 0) {
            if ($flexible) {
                $row['work_day_fraction'] = round($row['worked_minutes'] / max(1, $this->pol->flexibleDayMinutes), 1);
            } else {
                $span = max(1, (int) ($timetable['work_minutes'] ?? 0));
                $row['work_day_fraction'] = round(min(1.0, $row['worked_minutes'] / $span) * $ttWorkDay, 1);
            }
        } elseif ($onLeave) {
            $row['work_day_fraction'] = round(($row['half_day'] ? $leaveFraction : 1.0) * ($timetable ? $ttWorkDay : 1.0), 2);
        } else {
            $row['work_day_fraction'] = 0.0;
        }

        // ---- status ----
        $row['flexible']     = $flexible;
        $row['scheduled_day']= $scheduled;
        $mappedForStatus     = ! empty($row['no_pair']) ? [] : $mapped; // unpaired punches ⇒ Absent
        $row['status']       = $this->status($row, $timetable, $isWeekend, $isHoliday, $onLeave, $mappedForStatus, $hasBothPunches, $scheduled);
        $row['status_class'] = $this->statusClass($row['status']);

        return $row;
    }

    private function emptyRow(string $date, int $ts, string $status, string $class): array
    {
        return [
            'date' => $date, 'weekday' => date('D', $ts), 'is_weekend' => false, 'is_holiday' => false,
            'holiday_name' => null, 'on_leave' => false, 'leave_fraction' => 0.0, 'half_day' => false,
            'leave_type' => null, 'scheduled' => null,
            'punches' => [], 'punch_count' => 0, 'first_in' => null, 'last_out' => null,
            'worked_minutes' => 0, 'total_minutes' => 0, 'break_minutes' => 0, 'break_taken_minutes' => 0,
            'break_pairs' => [], 'late_minutes' => 0, 'early_minutes' => 0,
            'short_minutes' => 0, 'unschedule_minutes' => 0, 'in_ot_minutes' => 0, 'out_ot_minutes' => 0, 'miss_minutes' => 0,
            'regular_minutes' => 0, 'absent_minutes' => 0, 'leave_minutes' => 0, 'remaining_minutes' => 0,
            'duty_gross_minutes' => 0, 'duty_net_minutes' => 0, 'break_sched_minutes' => 0,
            'is_incomplete' => false, 'present' => 0, 'full_attendance' => false,
            'ot_minutes' => 0, 'ot_kind' => 'normal',
            'work_day_fraction' => 0.0, 'flexible' => false, 'scheduled_day' => false,
            'is_paid_leave' => true, 'status' => $status, 'status_class' => $class,
        ];
    }

    private function status(array $row, ?array $tt, bool $weekend, bool $holiday, bool $leave, array $punches, bool $both, bool $scheduled = false): string
    {
        if ($leave && ! $row['half_day']) {
            return 'Leave';
        }
        if ($leave && $row['half_day'] && $punches === []) {
            return 'Half-day Leave';
        }
        // half-day leave WITH punches falls through to the normal
        // present/late/early classification against the pro-rated schedule
        if ($holiday) {
            return $punches === [] ? 'Holiday' : 'Holiday Work';
        }
        if ($weekend) {
            return $punches === [] ? 'Weekend' : 'Weekend Work';
        }
        if ($tt === null) {
            // flexible day (ZKBioTime): scheduled to work but no fixed shift
            if ($scheduled) {
                if ($punches === []) {
                    return 'Absent';
                }
                return $both ? 'Present' : 'Incomplete';
            }
            // No shift resolvable at all (no personal / department / group
            // schedule) — still counted Absent when there's no punch, rather
            // than hidden behind "Not Scheduled", so an employee who was
            // simply never assigned a shift doesn't silently disappear from
            // the attendance count.
            return $punches === [] ? 'Absent' : 'Unscheduled Punch';
        }
        if ($punches === []) {
            return 'Absent';
        }
        if (! $both && ($row['miss_minutes'] ?? 0) <= 0) {
            return 'Incomplete';
        }
        // A one-sided punch under miss_in / miss_out policy is booked by
        // ZKBioTime as a Late In / Early Out, not left Incomplete.
        $late  = $row['late_minutes']  > 0;
        $early = $row['early_minutes'] > 0;
        if ($late && $early) {
            return 'Late & Early';
        }
        if ($late) {
            return 'Late';
        }
        if ($early) {
            return 'Early Out';
        }
        return 'Present';
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'Present', 'Weekend Work', 'Holiday Work' => 'success',
            'Late', 'Early Out', 'Late & Early', 'Incomplete', 'Unscheduled Punch' => 'warning',
            'Absent' => 'danger',
            'Leave', 'Half-day Leave' => 'info',
            'Holiday', 'Weekend' => 'muted',
            default => 'muted',
        };
    }

    // ------------------------------------------------------------------
    //  Schedule / timetable resolution
    // ------------------------------------------------------------------

    /**
     * Resolve the day's schedule the way ZKBioTime does:
     *   - no schedule covering the date        → not a working day
     *   - schedule present, shift has timetable → fixed-shift maths
     *   - schedule present, NO att_shiftdetail  → "flexible day": the
     *     employee is expected to work but there is no fixed timetable, so
     *     ZKBioTime just banks the span between first and last punch.
     *
     * @return array{timetable: ?array, flexible: bool, scheduled: bool}
     */
    private function resolveSchedule(int $empId, int $deptId, string $date, int $weekday): array
    {
        $shiftId = $this->shiftForEmployee($empId, $deptId, $date);
        if ($shiftId === null) {
            return ['timetable' => null, 'flexible' => false, 'scheduled' => false];
        }

        if (! isset($this->shiftDetailCache[$shiftId])) {
            $rows = $this->db->table('att_shiftdetail')
                ->select('day_index, time_interval_id')
                ->where('shift_id', $shiftId)
                ->get()->getResultArray();
            $map = [];
            foreach ($rows as $r) {
                $map[(int) $r['day_index']] = (int) $r['time_interval_id'];
            }
            $this->shiftDetailCache[$shiftId] = $map;
        }
        $map = $this->shiftDetailCache[$shiftId];

        if ($map === []) {
            return ['timetable' => null, 'flexible' => true, 'scheduled' => true];
        }

        $ttId = $map[$weekday] ?? reset($map);

        return ['timetable' => $this->timetable((int) $ttId), 'flexible' => false, 'scheduled' => true];
    }

    /** Back-compat wrapper for the override path. */
    private function resolveTimetable(int $empId, int $deptId, string $date, int $weekday): ?array
    {
        return $this->resolveSchedule($empId, $deptId, $date, $weekday)['timetable'];
    }

    private function shiftForEmployee(int $empId, int $deptId, string $date): ?int
    {
        if (! isset($this->schedCache[$empId])) {
            $this->schedCache[$empId] = $this->db->table('att_attschedule')
                ->select('shift_id, start_date, end_date')
                ->where('employee_id', $empId)
                ->orderBy('start_date', 'DESC')
                ->get()->getResultArray();
        }
        foreach ($this->schedCache[$empId] as $s) {
            if ($date >= $s['start_date'] && $date <= $s['end_date']) {
                return (int) $s['shift_id'];
            }
        }

        if ($deptId) {
            if ($this->deptSchedCache === null) {
                $this->deptSchedCache = $this->db->table('att_departmentschedule')
                    ->select('department_id, shift_id, start_date, end_date')
                    ->orderBy('start_date', 'DESC')
                    ->get()->getResultArray();
            }
            foreach ($this->deptSchedCache as $s) {
                if ((int) $s['department_id'] === $deptId && $date >= $s['start_date'] && $date <= $s['end_date']) {
                    return (int) $s['shift_id'];
                }
            }
        }

        // Company schedule — this app's own addition (no ZKBioTime
        // equivalent), one tier below Department: a whole-company default
        // that a specific department's own schedule still overrides.
        if ($deptId) {
            if (! array_key_exists($deptId, $this->deptCompanyCache)) {
                $row = $this->db->table('personnel_department')->select('company_id')->where('id', $deptId)->get()->getRowArray();
                $this->deptCompanyCache[$deptId] = $row && $row['company_id'] !== null ? (int) $row['company_id'] : null;
            }
            $companyId = $this->deptCompanyCache[$deptId];
            if ($companyId) {
                if ($this->companySchedCache === null) {
                    $this->companySchedCache = $this->db->table('att_companyschedule')
                        ->select('company_id, shift_id, start_date, end_date')
                        ->orderBy('start_date', 'DESC')
                        ->get()->getResultArray();
                }
                foreach ($this->companySchedCache as $s) {
                    if ((int) $s['company_id'] === $companyId && $date >= $s['start_date'] && $date <= $s['end_date']) {
                        return (int) $s['shift_id'];
                    }
                }
            }
        }

        $group = $this->db->query(
            'SELECT gs.shift_id
               FROM att_attemployee ae
               JOIN att_groupschedule gs ON gs.group_id = ae.group_id
              WHERE ae.emp_id = ? AND gs.start_date <= ? AND gs.end_date >= ?
              ORDER BY gs.start_date DESC LIMIT 1',
            [$empId, $date, $date]
        )->getRowArray();

        return $group ? (int) $group['shift_id'] : null;
    }

    private function fallbackTimetableId(int $shiftId): ?int
    {
        // any timetable already attached to the shift, else the company's first
        $row = $this->db->query(
            'SELECT time_interval_id FROM att_shiftdetail WHERE shift_id = ? ORDER BY day_index LIMIT 1',
            [$shiftId]
        )->getRowArray();
        if ($row) {
            return (int) $row['time_interval_id'];
        }
        $row = $this->db->table('att_timeinterval')->select('id')->orderBy('id')->get(1)->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function timetable(int $id): ?array
    {
        if (! isset($this->timetables[$id])) {
            $r = $this->db->table('att_timeinterval')->where('id', $id)->get()->getRowArray();
            if (! $r) {
                return $this->timetables[$id] = null;
            }
            // schedule clock values are naive; shift them into the display zone
            $inMin  = ($this->minutes(substr((string) $r['in_time'], 0, 5))
                + $this->pol->scheduleOffsetMinutes) % 1440;
            if ($inMin < 0) {
                $inMin += 1440;
            }
            $work   = (int) $r['duration'];
            $outMin = ($inMin + $work) % 1440;
            $b      = static fn ($v) => in_array($v, [true, 't', '1', 1], true);
            $this->timetables[$id] = [
                'id'           => $id,
                'alias'        => $r['alias'],
                'in'           => sprintf('%02d:%02d', intdiv($inMin, 60), $inMin % 60),
                'out'          => sprintf('%02d:%02d', intdiv($outMin, 60), $outMin % 60),
                'work_minutes' => $work,
                // grace = the timetable's Allow Late-In / Allow Early-Out only
                // (the Check-In/Out Start/End *windows* are for punch pairing,
                //  NOT a grace — matching ZKBioTime calc_att_time).
                'allow_late'   => (int) $r['allow_late'],
                'allow_early'  => (int) $r['allow_leave_early'],
                'in_ahead_margin'    => (int) ($r['in_ahead_margin']    ?? 0),
                'in_above_margin'    => (int) ($r['in_above_margin']    ?? 0),
                'out_ahead_margin'   => (int) ($r['out_ahead_margin']   ?? 0),
                'out_above_margin'   => (int) ($r['out_above_margin']   ?? 0),
                'work_time_duration' => (int) ($r['work_time_duration'] ?: $work),
                'work_day'           => (float) ($r['work_day'] ?? 1),
                'in_required'        => (int) ($r['in_required']  ?? 1) === 1,
                'out_required'       => (int) ($r['out_required'] ?? 1) === 1,
                'multiple_punch'     => (int) ($r['multiple_punch'] ?? 0) === 1,
                'compensate'         => (int) ($r['compensate_duration'] ?? 0),
                'day_change'         => substr((string) ($r['day_change'] ?? '00:00:00'), 0, 5),
                'enable_overtime'    => $b($r['enable_overtime'] ?? true),
                'enable_max_ot'      => $b($r['enable_max_ot_limit'] ?? false),
                'max_ot_limit'       => (int) ($r['max_ot_limit'] ?? 0),
                // Unscheduled OT gates (ZKBioTime calc_att_time in_ot / out_ot) —
                // per-timetable, no global "OT threshold" exists in the real engine.
                'enable_early_in'          => $b($r['enable_early_in'] ?? false),
                'min_early_in'             => (int) ($r['min_early_in'] ?? 0),
                'count_early_in_interval'  => $b($r['count_early_in_interval'] ?? true),
                'enable_late_out'          => $b($r['enable_late_out'] ?? false),
                'min_late_out'             => (int) ($r['min_late_out'] ?? 0),
                'count_late_out_interval'  => $b($r['count_late_out_interval'] ?? true),
            ];
        }

        return $this->timetables[$id];
    }

    /** @return array<int,array{period_start:string,duration:string,with_salary:string,start_date:?string,end_date:?string,break_out_ahead_margin:string,break_out_above_margin:string,break_in_ahead_margin:string,break_in_above_margin:string}> */
    private function breakRows(?int $ttId): array
    {
        if ($ttId === null) {
            return [];
        }

        return $this->breakCache[$ttId] ??= $this->db->query(
            'SELECT b.period_start, b.duration, b.with_salary, b.start_date, b.end_date,
                    b.break_out_ahead_margin, b.break_out_above_margin,
                    b.break_in_ahead_margin, b.break_in_above_margin
               FROM att_timeinterval_break_time l
               JOIN att_breaktime b ON b.id = l.breaktime_id
              WHERE l.timeinterval_id = ?',
            [$ttId]
        )->getResultArray();
    }

    /**
     * A timetable's linked breaks that are actually in effect on $date — i.e.
     * still within their own Start Date / End Date validity window (either
     * side null = unbounded). A timetable with no break linked, or whose
     * linked break(s) haven't started yet / already expired for this date,
     * resolves to an empty list — callers must then treat the day as having
     * no break at all.
     *
     * @return array<int,array{period_start:string,duration:string,with_salary:string}>
     */
    private function activeBreakRows(?int $ttId, string $date): array
    {
        return array_values(array_filter($this->breakRows($ttId), static function (array $b) use ($date): bool {
            if (! empty($b['start_date']) && $date < substr((string) $b['start_date'], 0, 10)) {
                return false;
            }
            if (! empty($b['end_date']) && $date > substr((string) $b['end_date'], 0, 10)) {
                return false;
            }

            return true;
        }));
    }

    /** Total unpaid break minutes the timetable defines for $date (span-independent). */
    private function scheduledUnpaidTotal(?int $ttId, string $date): int
    {
        $t = 0;
        foreach ($this->activeBreakRows($ttId, $date) as $b) {
            if ((int) $b['with_salary'] !== 1) {
                $t += (int) $b['duration'];
            }
        }

        return $t;
    }

    /**
     * ZKBioTime break totals for a timetable on $date (calc_interval lines 267-273).
     *
     * @return array{0:int,1:int}  [total_break_time (Σ all durations), paid_break (Σ with_salary durations)]
     */
    private function breakTotals(?int $ttId, string $date): array
    {
        $total = $paid = 0;
        foreach ($this->activeBreakRows($ttId, $date) as $b) {
            $dur = (int) $b['duration'];
            $total += $dur;
            if ((int) $b['with_salary'] === 1) {
                $paid += $dur;
            }
        }

        return [$total, $paid];
    }

    // ------------------------------------------------------------------
    //  Data loaders
    // ------------------------------------------------------------------

    public function employee(int $id): ?array
    {
        return $this->db->query($this->employeeSelect() . ' WHERE e.id = ?', [$id])->getRowArray();
    }

    /** @return array<int,array> */
    public function employees(?int $deptId = null): array
    {
        $sql = $this->employeeSelect();
        $bind = [];
        if ($deptId) {
            $sql .= ' WHERE e.department_id = ?';
            $bind[] = $deptId;
        }
        $sql .= ' ORDER BY e.emp_code_digit NULLS LAST, e.emp_code';

        return $this->db->query($sql, $bind)->getResultArray();
    }

    private function employeeSelect(): string
    {
        return "SELECT e.id, e.emp_code,
                       NULLIF(TRIM(CONCAT(e.first_name, ' ', e.last_name)), '') AS name,
                       e.first_name, e.last_name, e.nickname, e.photo, e.hire_date, e.gender,
                       e.mobile, e.email, e.department_id, e.position_id, e.leave_group,
                       ae.group_id AS att_group_id,
                       d.dept_name, d.dept_code, p.position_name, p.position_code,
                       (SELECT r.resign_date FROM personnel_resign r WHERE r.employee_id = e.id) AS resign_date
                  FROM personnel_employee e
             LEFT JOIN personnel_department d  ON d.id = e.department_id
             LEFT JOIN personnel_position   p  ON p.id = e.position_id
             LEFT JOIN att_attemployee      ae ON ae.emp_id = e.id";
    }

    /**
     * All punches for the given employees across the range (plus a margin so a
     * night shift's next-morning clock-out is available), as one flat,
     * chronologically-sorted list per employee. buildDay() then matches the
     * slice that belongs to each scheduled day via selectDayPunches().
     *
     * @param int[] $empIds
     * @return array<int,list<array>>  empId => punch rows (sorted)
     */
    private function punchPool(array $empIds, string $from, string $to): array
    {
        if ($empIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $empIds));
        // fetch a little past `to` so an after-midnight clock-out still lands
        // on the working day it belongs to.
        $rows = $this->db->query(
            "SELECT t.emp_id,
                    to_char(t.punch_time, 'YYYY-MM-DD HH24:MI:SS') AS punch_time,
                    term.alias AS terminal_alias, t.terminal_sn, t.punch_state
               FROM iclock_transaction t
          LEFT JOIN iclock_terminal term ON term.id = t.terminal_id
              WHERE t.emp_id IN ($in)
                AND t.punch_time >= (?::date - INTERVAL '1 day')
                AND t.punch_time <  (?::date + INTERVAL '1 day 12 hours')",
            [$from, $to]
        )->getResultArray();

        // Approved manual-punch corrections (att_manuallog) are folded in as
        // real punches so an approved fix repairs an Absent / Incomplete day
        // without ever touching iclock_transaction.
        $manual = $this->db->query(
            "SELECT w.employee_id AS emp_id,
                    to_char(m.punch_time, 'YYYY-MM-DD HH24:MI:SS') AS punch_time,
                    'Manual'::text AS terminal_alias, NULL::varchar AS terminal_sn,
                    NULLIF(m.punch_state,'')::int AS punch_state
               FROM att_manuallog m
               JOIN workflow_workflowinstance w ON w.id = m.workflowinstance_ptr_id
              WHERE w.employee_id IN ($in)
                AND w.approval_status = 1
                AND m.punch_time >= (?::date - INTERVAL '1 day')
                AND m.punch_time <  (?::date + INTERVAL '1 day 12 hours')",
            [$from, $to]
        )->getResultArray();
        $rows = array_merge($rows, $manual);
        usort($rows, static fn ($a, $b) => strcmp($a['punch_time'], $b['punch_time']));

        // att_attpolicy.punch_period — a repeat punch from the same employee
        // within this many minutes of the last one KEPT is a duplicate device
        // event (retry / double-scan), not a second real clock event.
        $dupWindow = $this->pol->dupPunchWindowMinutes * 60;
        $byEmp = [];
        $lastTs = [];
        foreach ($rows as $r) {
            $empId = (int) $r['emp_id'];
            $ts    = strtotime($r['punch_time']);
            if ($dupWindow > 0 && isset($lastTs[$empId]) && ($ts - $lastTs[$empId]) < $dupWindow) {
                continue;
            }
            $lastTs[$empId] = $ts;
            $byEmp[$empId][] = $r;
        }

        return $byEmp;
    }

    /**
     * The punches that belong to one scheduled day.
     *
     * Fixed shift → ZKBioTime's punch_match: every punch from the check-in
     * window's start (in_time − in_ahead_margin) to the check-out window's end
     * (out_time + out_above_margin). For a night shift that band runs past
     * midnight, so the next-morning clock-out is filed under the day the shift
     * started, exactly as ZKBioTime does. The band is clamped to the mid-point
     * of the off-shift gap so it can never reach an adjacent shift.
     *
     * Flexible / unscheduled → calendar day (shifted by day_change) with the
     * session-gap grouping so a cross-midnight run stays on its start day.
     *
     * @param list<array> $pool
     * @return list<array>
     */
    private function selectDayPunches(array $pool, string $date, ?array $tt, bool $flexible, bool $scheduled): array
    {
        if ($pool === []) {
            return [];
        }
        $dayTs = strtotime($date . ' 00:00:00');

        if ($tt !== null && $scheduled) {
            $inMin  = $this->minutes($tt['in']);
            $outMin = $this->minutes($tt['out']);
            if ($outMin <= $inMin) {
                $outMin += 1440;
            }
            $gapHalf  = $outMin > 1440 ? intdiv(($outMin - 1440) + $inMin, 2) : 0;
            $startMin = max($inMin - (int) ($tt['in_ahead_margin'] ?? 0), $gapHalf);
            $endMin   = $outMin + (int) ($tt['out_above_margin'] ?? 0);
            if ($outMin > 1440) {
                $endMin = min($endMin, 1440 + $gapHalf);
            }
            $winStart = $dayTs + $startMin * 60;
            $winEnd   = $dayTs + $endMin * 60;
            $sel = [];
            foreach ($pool as $p) {
                $t = strtotime($p['punch_time']);
                if ($t >= $winStart && $t < $winEnd) {
                    $sel[] = $p;
                }
            }
            return $sel;
        }

        // flexible / unscheduled
        $dayOf = function (int $ts): string {
            return (int) date('G', $ts) < $this->pol->dayChangeHour
                ? date('Y-m-d', strtotime('-1 day', $ts))
                : date('Y-m-d', $ts);
        };
        $sessionGap = 6 * 3600;
        $hardGap    = 18 * 3600;
        $sessionDay = null;
        $lastTs     = null;
        $sel        = [];
        foreach ($pool as $p) {
            $ts    = strtotime($p['punch_time']);
            $state = $p['punch_state'] === null ? null : (int) $p['punch_state'];
            $isIn  = in_array($state, [0, 4], true);
            $newSess = $sessionDay === null
                || ($ts - $lastTs) > $hardGap
                || ($isIn && ($ts - $lastTs) > $sessionGap);
            if ($newSess) {
                $sessionDay = $dayOf($ts);
            }
            if ($sessionDay === $date) {
                $sel[] = $p;
            }
            $lastTs = $ts;
        }
        return $sel;
    }

    /** @return array<int,array> */
    private function holidays(string $from, string $to): array
    {
        return $this->db->query(
            'SELECT id, alias, start_date, end_date, department_id, att_group_id
               FROM att_holiday
              WHERE start_date <= ? AND end_date >= ?',
            [$to, $from]
        )->getResultArray();
    }

    /** A holiday applies when it is company-wide, or scoped to the employee's dept / att-group. */
    private function matchHoliday(array $holidays, string $date, array $emp): ?array
    {
        $deptId  = (int) ($emp['department_id'] ?? 0);
        $groupId = (int) ($emp['att_group_id'] ?? 0);
        foreach ($holidays as $h) {
            if ($date < $h['start_date'] || $date > $h['end_date']) {
                continue;
            }
            $scoped = $h['department_id'] !== null || $h['att_group_id'] !== null;
            if (! $scoped
                || ($h['department_id'] !== null && (int) $h['department_id'] === $deptId)
                || ($h['att_group_id'] !== null && (int) $h['att_group_id'] === $groupId)) {
                return $h;
            }
        }

        return null;
    }

    /**
     * @param int[] $empIds
     * @return array<int,array<int,array>>
     */
    private function leaves(array $empIds, string $from, string $to): array
    {
        if ($empIds === []) {
            return [];
        }
        $in   = implode(',', array_map('intval', $empIds));
        // Approved leave AND approved training are both "authorised paid-ish
        // absence" — training defaults to paid and shows its own label.
        $rows = $this->db->query(
            "SELECT w.employee_id,
                    to_char(l.start_time,'YYYY-MM-DD') AS start_date,
                    to_char(l.end_time,'YYYY-MM-DD')   AS end_date,
                    l.leave_day,
                    pc.name AS pay_code,
                    COALESCE(pc.is_paid, true) AS is_paid
               FROM att_leave l
               JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
          LEFT JOIN att_paycode pc ON pc.id = l.pay_code_id
              WHERE w.employee_id IN ($in)
                AND w.approval_status = 1
                AND l.start_time <  (?::date + INTERVAL '1 day')
                AND l.end_time   >= ?::date
             UNION ALL
             SELECT w.employee_id,
                    to_char(tr.start_time,'YYYY-MM-DD') AS start_date,
                    to_char(tr.end_time,'YYYY-MM-DD')   AS end_date,
                    (tr.end_time::date - tr.start_time::date + 1)::numeric AS leave_day,
                    COALESCE(pc.name, 'Training') AS pay_code,
                    COALESCE(pc.is_paid, true) AS is_paid
               FROM att_training tr
               JOIN workflow_workflowinstance w ON w.id = tr.workflowinstance_ptr_id
          LEFT JOIN att_paycode pc ON pc.id = tr.pay_code_id
              WHERE w.employee_id IN ($in)
                AND w.approval_status = 1
                AND tr.start_time <  (?::date + INTERVAL '1 day')
                AND tr.end_time   >= ?::date",
            [$to, $from, $to, $from]
        )->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $r['is_paid']                     = in_array($r['is_paid'], [true, 't', '1', 1], true);
            $out[(int) $r['employee_id']][]   = $r;
        }

        return $out;
    }

    private function matchLeave(array $leaves, string $date): ?array
    {
        foreach ($leaves as $l) {
            if ($date >= $l['start_date'] && $date <= $l['end_date']) {
                return $l;
            }
        }

        return null;
    }

    /**
     * Timetable id from an APPROVED schedule-change request (att_changeschedule)
     * for this employee/date, or null when there is none.
     */
    private function changeScheduleFor(int $empId, string $date): ?int
    {
        if ($this->changeSchedCache === null) {
            $this->changeSchedCache = [];
            // Approved schedule-change requests (att_changeschedule)…
            $rows = $this->db->query(
                "SELECT w.employee_id, to_char(c.att_date,'YYYY-MM-DD') AS d, c.timeinterval_id
                   FROM att_changeschedule c
                   JOIN workflow_workflowinstance w ON w.id = c.workflowinstance_ptr_id
                  WHERE w.approval_status = 1"
            )->getResultArray();
            foreach ($rows as $r) {
                if ($r['timeinterval_id'] !== null) {
                    $this->changeSchedCache[$r['employee_id'] . '|' . $r['d']] = (int) $r['timeinterval_id'];
                }
            }
            // …and admin-set temporary schedules (att_temporaryschedule) — these
            // win, being a deliberate one-day override with no approval step.
            $temp = $this->db->query(
                "SELECT employee_id, to_char(att_date,'YYYY-MM-DD') AS d, time_interval_id
                   FROM att_temporaryschedule
                  WHERE status = 0 AND time_interval_id IS NOT NULL"
            )->getResultArray();
            foreach ($temp as $r) {
                $this->changeSchedCache[$r['employee_id'] . '|' . $r['d']] = (int) $r['time_interval_id'];
            }
        }

        return $this->changeSchedCache[$empId . '|' . $date] ?? null;
    }

    /**
     * Total minutes of APPROVED overtime requested (att_overtime) that overlap
     * this employee/date. 0 when there is no approved request.
     */
    private function overtimeRequestMinutes(int $empId, string $date): int
    {
        if ($this->otReqCache === null) {
            $this->otReqCache = [];
            $rows = $this->db->query(
                "SELECT w.employee_id,
                        to_char(o.start_time,'YYYY-MM-DD') AS d,
                        EXTRACT(EPOCH FROM (o.end_time - o.start_time))/60 AS mins
                   FROM att_overtime o
                   JOIN workflow_workflowinstance w ON w.id = o.workflowinstance_ptr_id
                  WHERE w.approval_status = 1"
            )->getResultArray();
            foreach ($rows as $r) {
                $this->otReqCache[(int) $r['employee_id']][$r['d']]
                    = ($this->otReqCache[(int) $r['employee_id']][$r['d']] ?? 0) + (int) round($r['mins']);
            }
        }

        return $this->otReqCache[$empId][$date] ?? 0;
    }

    // ------------------------------------------------------------------
    //  helpers
    // ------------------------------------------------------------------

    private function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));

        return $h * 60 + $m;
    }

    public static function hhmm(int $minutes): string
    {
        $minutes = max(0, (int) round($minutes));

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    // ------------------------------------------------------------------
    //  Shared status classification (used by every list / dashboard so
    //  the counts always agree)
    // ------------------------------------------------------------------

    /** Statuses that mean "the employee showed up and worked". */
    public const PRESENT_STATUSES = [
        'Present', 'Late', 'Early Out', 'Late & Early', 'Weekend Work', 'Holiday Work',
    ];

    public static function isPresent(string $status): bool
    {
        return in_array($status, self::PRESENT_STATUSES, true);
    }

    /**
     * Collapse a day status into one summary bucket:
     * present · late · early · absent · incomplete · leave · off.
     */
    public static function bucket(string $status): string
    {
        return match ($status) {
            'Present', 'Weekend Work', 'Holiday Work' => 'present',
            'Late', 'Late & Early'                    => 'late',
            'Early Out'                               => 'early',
            'Absent'                                  => 'absent',
            'Incomplete', 'Unscheduled Punch'         => 'incomplete',
            'Leave', 'Half-day Leave'                 => 'leave',
            default                                   => 'off',
        };
    }
}
