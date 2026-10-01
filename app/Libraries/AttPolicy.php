<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Attendance as AttendanceConfig;

/**
 * Runtime attendance-engine settings, resolved from the database so the
 * Settings screen and the AttendanceCalculator always agree.
 *
 *  - ZK-native fields (weekend days, day-change, OT on/off) come from the
 *    single `att_attpolicy` row.
 *  - TimeTrack-only knobs (grace minutes, OT rounding/threshold/cap, the
 *    Incomplete-day pay policy, break-punch handling) are stored as
 *    key/value rows in `base_sysparam` with `para_type = 'timetrack'`.
 *
 * Anything not set in the DB falls back to `Config\Attendance`, so a fresh
 * install behaves exactly as the shipped config until an admin edits it.
 */
final class AttPolicy
{
    /** @var int[] 0 = Sunday … 6 = Saturday */
    public array $weekendDays;
    public int $dayChangeHour;
    public bool $overtimeEnabled;
    public int $extraLateGrace;
    public int $extraEarlyGrace;
    /** minutes added to naive schedule clock values (see Config\Attendance) */
    public int $scheduleOffsetMinutes;
    /** how an Incomplete day (one punch only) is paid: present | half | absent */
    public string $incompletePolicy;
    public string $timezone;
    /** @var array<int,string> */
    public array $stateLabels;
    public ?int $otPayCodeId;

    /* ---- ZKBioTime flexible-day model (no resolvable timetable) ---- */
    /** day length used as the workday-fraction denominator = att_attpolicy.max_hrs */
    public int $flexibleDayMinutes;
    /** a "full day" of work = base_sysparam rule_0 minutes_work_day */
    public int $standardWorkMinutes;
    /** daily overtime master switch from att_attpolicy.daily_ot (flexible mode never credits OT) */
    public bool $dailyOtEnabled;
    /** att_attpolicy.weekend_ot / holiday_ot — separate switches from daily_ot for those day kinds */
    public bool $weekendOtEnabled;
    public bool $holidayOtEnabled;
    /** att_attpolicy.overtime_policy: -1 = ignore overtime entirely, 1 = only an
     *  approved att_overtime request counts, 2 = auto-credit worked overtime (default) */
    public int $overtimePolicy;
    /** att_attpolicy.punch_period — repeat punches from the same employee within
     *  this many minutes of the previous one are merged (device retry / duplicate). */
    public int $dupPunchWindowMinutes;
    /** minutes late/early that flip a day to Absent (att_attpolicy late_in2absence / early_out2absence) */
    public int $lateToAbsence;
    public int $earlyToAbsence;
    /** ZKBioTime miss_in / miss_out: a required punch missing on one side is
     *  penalised by late_in_hrs / early_out_hrs minutes (deducted from Regular). */
    public bool $missInEnabled;
    public bool $missOutEnabled;
    public int $missInMinutes;
    public int $missOutMinutes;
    /** raw att_attpolicy.miss_in / miss_out mode: 0 = Incomplete, 1 = penalty (late_in_hrs), 2 = Absent */
    public int $missInMode;
    public int $missOutMode;

    private static ?self $cached = null;

    public static function load(?BaseConnection $db = null): self
    {
        return self::$cached ??= self::build($db ?? db_connect());
    }

    /** Drop the process cache (call after Settings saves). */
    public static function flush(): void
    {
        self::$cached = null;
    }

    private static function build(BaseConnection $db): self
    {
        $cfg = config('Attendance');
        $p   = new self();

        $p->stateLabels = $cfg->stateLabels;

        // ---- key/value overrides (base_sysparam, para_type = 'timetrack') ----
        $kv = [];
        try {
            foreach ($db->table('base_sysparam')->where('para_type', 'timetrack')->get()->getResultArray() as $r) {
                $kv[$r['para_name']] = $r['para_value'];
            }
        } catch (\Throwable $e) {
            // table always exists in this DB, but stay defensive
        }

        // ---- ZKBioTime's own default attendance rule (base_sysparam, para_type = 'rule_0') ----
        $rule0 = [];
        try {
            foreach ($db->table('base_sysparam')->where('para_type', 'rule_0')->get()->getResultArray() as $r) {
                $rule0[$r['para_name']] = $r['para_value'];
            }
        } catch (\Throwable $e) {
        }

        // Display timezone — DYNAMIC, derived from this database's own data,
        // not a hardcoded zone name. att_timeinterval.in_time is a naive
        // clock value with no timezone attached; att_payloadtimecard.check_in
        // is the absolute instant ZKBioTime's own engine already computed for
        // it. Comparing the two — in raw UTC, no zone guess involved — gives
        // the exact UTC offset this install's schedules were authored under,
        // as a plain "+HH:MM" string Postgres accepts directly. Re-derived
        // fresh every load, so pointing at a different database (or a DB
        // whose install timezone changes) just works, nothing to edit here.
        // An explicit `timezone` kv override wins if ever set;
        // Config\Attendance::$timezone is the last-resort fallback for a
        // brand-new DB with no calculated history yet to sample.
        $p->timezone = $kv['timezone'] ?? (self::detectTimezoneOffset($db) ?? $cfg->timezone);

        // ---- ZK policy row ----
        $pol = null;
        try {
            $pol = $db->table('att_attpolicy')->orderBy('id')->get(1)->getRowArray();
        } catch (\Throwable $e) {
        }

        // weekend days: prefer the kv override, else the ZK policy, else config
        if (isset($kv['weekend_days'])) {
            $p->weekendDays = array_values(array_filter(
                array_map('intval', explode(',', $kv['weekend_days'])),
                static fn ($d) => $d >= 0 && $d <= 6
            ));
        } elseif ($pol !== null) {
            $p->weekendDays = array_values(array_unique(array_filter(
                [(int) $pol['weekend1'], (int) $pol['weekend2']],
                static fn ($d) => $d >= 0 && $d <= 6           // 7 = "none" in ZK
            )));
        } else {
            $p->weekendDays = $cfg->weekendDays;
        }

        // Day boundary: an explicit kv override wins; otherwise the ZK policy
        // row's `day_change` time (e.g. "05:00:00" → 5); config is the last resort.
        $polDayChange = $pol !== null && ! empty($pol['day_change'])
            ? (int) substr((string) $pol['day_change'], 0, 2)
            : null;
        $p->dayChangeHour            = self::int($kv, 'day_change_hour', $polDayChange ?? $cfg->dayChangeHour);
        // Schedule display offset — DYNAMIC, not a hardcoded constant. A
        // schedule's naive clock value (att_timeinterval.in_time) was written
        // under whatever timezone the ZKBioTime install used at the time;
        // this app's own reports need to add the same offset the LIVE
        // ZKBioTime server would, so the numbers agree. We don't hardcode
        // that offset — we derive it from ZKBioTime's own already-computed
        // output: att_payloadtimecard.check_in is an absolute instant the
        // real engine wrote for a (timetable, date); reading it in our
        // display timezone and comparing it to the timetable's naive
        // in_time reveals the exact offset for THIS database, automatically
        // — works the same for `waves`, `waves_new`, or any future import,
        // no per-deployment constant to maintain. An explicit
        // `schedule_offset_minutes` kv override still wins if ever set;
        // Config\Attendance::$scheduleOffsetMinutes is the last-resort
        // fallback for a brand-new DB with no calculated history yet.
        $p->scheduleOffsetMinutes = isset($kv['schedule_offset_minutes'])
            ? self::int($kv, 'schedule_offset_minutes', 0)
            : (self::detectScheduleOffset($db, $p->timezone) ?? ($cfg->scheduleOffsetMinutes ?? 0));
        $p->extraLateGrace           = self::int($kv, 'extra_late_grace', $cfg->extraLateGrace);
        $p->extraEarlyGrace          = self::int($kv, 'extra_early_grace', $cfg->extraEarlyGrace);
        $p->incompletePolicy         = $kv['incomplete_policy'] ?? 'half';
        if (! in_array($p->incompletePolicy, ['present', 'half', 'absent'], true)) {
            $p->incompletePolicy = 'half';
        }

        // OT master switch: kv override wins, else the ZK use_ot flag, else config
        if (isset($kv['overtime_enabled'])) {
            $p->overtimeEnabled = self::bool($kv, 'overtime_enabled', $cfg->overtimeEnabled);
        } elseif ($pol !== null) {
            $p->overtimeEnabled = (int) $pol['use_ot'] === 1;
        } else {
            $p->overtimeEnabled = $cfg->overtimeEnabled;
        }

        $p->otPayCodeId = $pol !== null && $pol['ot_pay_code_id'] !== null ? (int) $pol['ot_pay_code_id'] : null;

        // ---- flexible-day model + late/early grace, from att_attpolicy ----
        $maxHrs = $pol !== null && isset($pol['max_hrs']) ? (float) $pol['max_hrs'] : 0.0;
        $p->flexibleDayMinutes  = self::int($kv, 'flexible_day_minutes',
            $maxHrs > 0 ? (int) round($maxHrs * 60) : $cfg->flexibleDayMinutes);
        // "Full day" target — DYNAMIC: ZKBioTime's own default attendance
        // rule row already carries this (base_sysparam rule_0.minutes_work_day);
        // a `standard_work_minutes` kv override still wins if ever set.
        $p->standardWorkMinutes = self::int($kv, 'standard_work_minutes',
            isset($rule0['minutes_work_day']) ? (int) $rule0['minutes_work_day'] : $cfg->standardWorkMinutes);

        $b = static fn ($v) => in_array($v, [true, 't', '1', 1], true);
        $p->dailyOtEnabled   = $pol !== null ? $b($pol['daily_ot'])   : $p->overtimeEnabled;
        $p->weekendOtEnabled = $pol !== null ? $b($pol['weekend_ot']) : $p->overtimeEnabled;
        $p->holidayOtEnabled = $pol !== null ? $b($pol['holiday_ot']) : $p->overtimeEnabled;
        $p->overtimePolicy   = $pol !== null && $pol['overtime_policy'] !== null ? (int) $pol['overtime_policy'] : 2;
        $p->dupPunchWindowMinutes = $pol !== null ? max(0, (int) $pol['punch_period']) : 0;

        $p->lateToAbsence  = $pol !== null ? (int) $pol['late_in2absence']   : 0;
        $p->earlyToAbsence = $pol !== null ? (int) $pol['early_out2absence'] : 0;

        // miss_in / miss_out — the penalty for a required punch missing on one
        // side (ZK stores the size in late_in_hrs / early_out_hrs, in minutes).
        $p->missInMode     = $pol !== null ? (int) $pol['miss_in']  : 0;
        $p->missOutMode    = $pol !== null ? (int) $pol['miss_out'] : 0;
        $p->missInEnabled  = $p->missInMode  === 1;
        $p->missOutEnabled = $p->missOutMode === 1;
        $p->missInMinutes  = self::int($kv, 'miss_in_minutes',  $pol !== null ? (int) $pol['late_in_hrs']  : 0);
        $p->missOutMinutes = self::int($kv, 'miss_out_minutes', $pol !== null ? (int) $pol['early_out_hrs'] : 0);

        return $p;
    }

    /**
     * Derive the display timezone as a plain UTC-offset string ("+04:00"),
     * with no IANA zone name guessed or hardcoded anywhere. Compares a
     * timetable's naive `in_time` directly against the same instant's raw
     * UTC reading in `att_payloadtimecard.check_in` (ZKBioTime's own
     * computed output) — the difference *is* the offset this install's
     * schedules were authored under. Returns null when the DB has no
     * calculated history to sample yet (a brand-new import).
     */
    private static function detectTimezoneOffset(BaseConnection $db): ?string
    {
        try {
            $row = $db->query(
                "SELECT ti.in_time,
                        EXTRACT(HOUR   FROM (p.check_in AT TIME ZONE 'UTC'))::int * 60
                      + EXTRACT(MINUTE FROM (p.check_in AT TIME ZONE 'UTC'))::int AS utc_minutes
                   FROM att_payloadtimecard p
                   JOIN att_timeinterval ti ON ti.id = p.time_table_id
                  WHERE p.check_in IS NOT NULL
               ORDER BY p.att_date DESC
                  LIMIT 1"
            )->getRowArray();
        } catch (\Throwable $e) {
            return null;
        }
        if ($row === null) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', substr((string) $row['in_time'], 0, 5)));
        $raw = $h * 60 + $m;
        // local (in_time) = UTC (utc_minutes) + offsetFromUTC
        $offset = ($raw - (int) $row['utc_minutes']) % 1440;
        // a schedule is authored in a real-world zone, i.e. within ±14h of UTC
        if ($offset > 840) {
            $offset -= 1440;
        } elseif ($offset <= -840) {
            $offset += 1440;
        }

        // PostgreSQL parses a bare numeric offset given to SET/AT TIME ZONE
        // using the POSIX sign convention, which is inverted from the normal
        // "+04:00 = 4 hours ahead of UTC" reading (SET TIME ZONE '+04:00'
        // actually yields UTC−4). Flip the sign so the string we hand back
        // produces the offset we actually computed.
        $sign = $offset < 0 ? '+' : '-';
        $abs  = abs($offset);

        return sprintf('%s%02d:%02d', $sign, intdiv($abs, 60), $abs % 60);
    }

    /**
     * Derive the schedule display offset (minutes) straight from the data:
     * compare a timetable's naive `in_time` against the wall-clock hour
     * ZKBioTime's own engine actually wrote for it in `att_payloadtimecard`.
     * Returns null when the DB has no calculated history to sample yet.
     */
    private static function detectScheduleOffset(BaseConnection $db, string $displayTz): ?int
    {
        try {
            $row = $db->query(
                "SELECT ti.in_time,
                        EXTRACT(HOUR   FROM (p.check_in AT TIME ZONE ?))::int * 60
                      + EXTRACT(MINUTE FROM (p.check_in AT TIME ZONE ?))::int AS disp_minutes
                   FROM att_payloadtimecard p
                   JOIN att_timeinterval ti ON ti.id = p.time_table_id
                  WHERE p.check_in IS NOT NULL
               ORDER BY p.att_date DESC
                  LIMIT 1",
                [$displayTz, $displayTz]
            )->getRowArray();
        } catch (\Throwable $e) {
            return null;
        }
        if ($row === null) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', substr((string) $row['in_time'], 0, 5)));
        $raw    = $h * 60 + $m;
        $offset = ((int) $row['disp_minutes'] - $raw) % 1440;
        if ($offset > 720) {
            $offset -= 1440;
        } elseif ($offset <= -720) {
            $offset += 1440;
        }

        return $offset;
    }

    private static function int(array $kv, string $k, int $default): int
    {
        return isset($kv[$k]) && $kv[$k] !== '' ? (int) $kv[$k] : $default;
    }

    private static function bool(array $kv, string $k, bool $default): bool
    {
        return isset($kv[$k]) ? in_array(strtolower((string) $kv[$k]), ['1', 'true', 'yes', 'on'], true) : $default;
    }
}
