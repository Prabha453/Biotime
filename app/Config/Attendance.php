<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Attendance engine settings.
 *
 * The raw biometric punches live in `iclock_transaction` (pushed by the
 * ZKTeco terminal). Everything the calculator does is derived from those
 * punches plus the ZKBioTime schedule tables (att_attschedule,
 * att_departmentschedule, att_attshift, att_timeinterval, att_breaktime).
 *
 * Every field below is a last-resort FALLBACK only — `AttPolicy` derives the
 * live value from the database itself wherever ZKBioTime's own schema has an
 * equivalent, and only falls back to this file for a brand-new database with
 * no calculated attendance history yet to sample from. Fields that had no
 * such live source and no real effect on the calculator have been removed
 * rather than kept as dead switches — see zkbio-parity memory for the audit.
 */
class Attendance extends BaseConfig
{
    /**
     * Last-resort fallback ONLY. The real display timezone is derived at
     * runtime, per database, by `AttPolicy::detectTimezoneOffset()` — it
     * compares a timetable's naive `in_time` against the absolute instant
     * ZKBioTime's own engine already wrote for it in
     * `att_payloadtimecard.check_in`, and uses whatever UTC offset that
     * implies (e.g. "+04:00"). No zone is guessed or hardcoded; it self-
     * adjusts for `waves`, `waves_new`, or any future import. This value is
     * only used when a brand-new database has no calculated attendance
     * history yet to sample from — set `attendance.timezone` in `.env` (or
     * the `timezone` row in `base_sysparam`) if that fallback ever needs to
     * be something other than UTC for a specific install.
     */
    public string $timezone = 'UTC';

    /**
     * Fallback only — minutes added to naive schedule clock values when
     * `AttPolicy` cannot derive the real offset from the database itself
     * (see `AttPolicy::detectScheduleOffset()`, same data-driven approach as
     * the timezone above). Only applies to a brand-new database with no
     * calculated attendance history yet to sample.
     */
    public int $scheduleOffsetMinutes = 0;

    /**
     * ZKBioTime "flexible day" fallback. When an employee's shift has no
     * resolvable timetable (att_shiftdetail empty), ZKBioTime does not use a
     * fixed shift — it banks the raw span between the first and last punch
     * and expresses it as a fraction of att_attpolicy.max_hrs. These mirror
     * that: the day length (max_hrs, minutes, ← att_attpolicy.max_hrs, live)
     * and the "full day" target (minutes, ← base_sysparam rule_0
     * .minutes_work_day, live). No late / early / short is computed in
     * flexible mode, and — per ZKBioTime's own calc_flex_att_time, which has
     * no break-handling code path at all — breaks are never deducted from a
     * flexible day; that's fixed behaviour, not a setting.
     */
    public int $flexibleDayMinutes = 1200;      // att_attpolicy.max_hrs (20 h)
    public int $standardWorkMinutes = 480;      // rule_0 minutes_work_day (8 h)

    /**
     * Week days treated as weekend. 0 = Sunday … 6 = Saturday.
     * Fallback only — live value ← att_attpolicy.weekend1/weekend2.
     *
     * @var int[]
     */
    public array $weekendDays = [];

    /** Overtime master switch. Fallback only — live value ← att_attpolicy.use_ot. */
    public bool $overtimeEnabled = true;

    /**
     * Grace given on top of the timetable's own `allow_late` /
     * `allow_leave_early` values (minutes) — an optional admin nudge with no
     * ZKBioTime equivalent (its own grace is fully live, per timetable).
     * 0 = exactly ZKBioTime's own numbers.
     */
    public int $extraLateGrace  = 0;
    public int $extraEarlyGrace = 0;

    /**
     * Hour of the "day change", for the flexible-day punch grouping only (a
     * fixed shift is grouped by its own pairing windows, not this). Fallback
     * only — live value ← att_attpolicy.day_change.
     */
    public int $dayChangeHour = 5;

    /** Punch-state code => human label. Fixed ZKTeco device protocol codes —
     *  no database table stores custom labels for these, so there is nothing
     *  to derive this from; it is not a "setting" in ZKBioTime either. */
    public array $stateLabels = [
        0 => 'Check In',
        1 => 'Check Out',
        2 => 'Break Out',
        3 => 'Break In',
        4 => 'OT In',
        5 => 'OT Out',
    ];
}
