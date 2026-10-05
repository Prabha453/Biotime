Xampp 8.2
## ========= PHP.ini ========
extension=intl
extension=pdo_pgsql
extension=pgsql
extension=tidy
extension=xsl
extension=zip
extension=gd

.env - Database connection

# TimeTrack — ZKTeco biometric attendance

CodeIgniter 4 + PostgreSQL admin panel that turns the raw biometric punches
pushed by a ZKTeco terminal (`iclock_transaction`) into daily / monthly
attendance for the ZKBioTime schema already loaded in `post_sql_db`.

## Machine setup

| Thing | Value |
|-------|-------|
| PHP | 8.2, `pgsql` + `pdo_pgsql` enabled |
| PostgreSQL | 18, host `127.0.0.1`, **port 3307**, db `post_sql_db` |
| Composer deps | installed in `vendor/` |

`.env` already points at the database. The app only **reads** the ZKBioTime
tables; it keeps its own `users` table for the admin login.

## First run

```
php spark migrate            # creates the `users` table
php spark db:seed UserSeeder  # admin@example.com / admin123
php spark serve --port 8080
```

Open <http://127.0.0.1:8080/> → login → dashboard.

## How attendance is calculated

`App\Libraries\AttendanceCalculator` (config in `app/Config/Attendance.php`):

1. **Schedule** – for each (employee, date) it resolves a shift from
   `att_attschedule` (personal) → `att_departmentschedule` (department) →
   `att_groupschedule`. The shift's timetable comes from `att_shiftdetail`
   for that weekday; this dataset has no detail rows, so the company's single
   `att_timeinterval` (06:00, 555 min → 15:15) is used every working day.
2. **Punches** – `iclock_transaction` rows are read in `Asia/Dubai` (the
   device area), segmented into work sessions (a check-in more than 4 h after
   the session start opens a new one) so a night shift that clocks out after
   midnight stays on the day it started. Punches before 05:00 count against
   the previous working day.
3. **Derived** – first-in / last-out, worked minutes (minus unpaid breaks),
   late (vs. `in_time` + `allow_late` + grace), early-out, short time,
   overtime (worked beyond the timetable, floored to 30 min; all weekend /
   holiday work is OT).
4. **Status** – Present · Late · Early Out · Incomplete (one punch) · Absent ·
   Leave (approved `att_leave`) · Holiday (`att_holiday`) · Weekend · No Data
   (after the last recorded punch).

## Payroll

`App\Libraries\PayrollCalculator` (config in `app/Config/Payroll.php`) is
**attendance-driven**: it reads each employee's monthly totals from the
AttendanceCalculator and writes a payslip into ZKBioTime's own
`payroll_payrollpayload`.

- **Base salary** – `payroll_salarystructure`, effective-dated (latest ≤ month end).
- **Day rate** = salary ÷ `standardMonthlyDays` (26); **hour rate** = day rate ÷ 8.
- **Overtime pay** = OT hours × hour rate × `overtimeMultiplier` (1.25). The
  calculator already treats all weekend/holiday work as OT, so one blended rate.
- **Deductions** – absent days × day rate (toggle `deductAbsentDays`), optional
  late-minute deduction, active `payroll_emploan` installments, and any
  `payroll_salaryadvance` falling in that month.
- **Run** – recomputes + replaces the month's payloads. `/admin/payroll` → *Run*.

## Modules beyond attendance

| Route | Tables | What |
|-------|--------|------|
| `/admin/payroll` | `payroll_*` | payslip run, salary structures, loans & advances, bank details, CSV |
| `/admin/access` | `acc_accgroups` `acc_acctimezone` `acc_accprivilege` `acc_acccombination` | door access levels, weekly time zones, per-employee privileges (sync to the terminal) |
| `/admin/visitors` | `visitor_visitor` `visitor_visitorlog` | front-desk check-in / check-out register + history (seeds `personnel_certification` / `visitor_reason` on first use) |
| `/admin/meeting-rooms` | `meeting_meetingroom` `meeting_meetingroomdevice` | room directory + check-in terminal (booking approval stays in the ZKBioTime workflow module) |
| `/admin/system-log` | `base_systemlog` `base_adminlog` `accounts_usernotification` `iclock_terminal*log` `django_celery_beat_*` `base_sysparam` | read-only window on ZKBioTime's operational tables |

## Pages

| Route | What |
|-------|------|
| `/admin/dashboard` | today's present/absent/late, 14-day trend, device status, live punch feed |
| `/admin/employees` `/…/{id}` | roster + per-employee month timesheet with daily breakdown |
| `/admin/devices` `/…/{id}` | terminal status, parameters, command log, punch volume |
| `/admin/punch-logs` | raw biometric feed, filter by employee / event / device / date |
| `/admin/attendance/daily` | daily register (+ CSV) |
| `/admin/attendance/monthly` | month grid P/L/A/I/W/H/V per day (+ CSV) |
| `/admin/shifts` | timetables, breaks, shifts, personal & department schedules |
| `/admin/holidays` · `/admin/leaves` | wired into the engine (holidays + approved leave suppress Absent) |
| `/admin/reports/{late,absent,overtime,first-last}` | range analytics |
| `/admin/settings` | engine + `att_timeinterval` + `att_attpolicy` parameters |
