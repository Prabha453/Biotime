<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle ?? '') ?>
<div class="row g-3">
    <?php foreach ([
        ['reports.timeCard', 'Total Time Card', 'grid-3x3-gap', 'The full per-day record — scheduled duty, actual clock in/out, breaks, Regular/OT/leave and every pay code. Column picker + export.'],
        ['reports.dailyWorked', 'Daily Worked Hours', 'clock', 'Worked, overtime and short minutes per person per punched day.'],
        ['reports.overtime', 'Daily Overtime', 'hourglass-split', 'Worked time beyond the scheduled hours (and all weekend / holiday work).'],
        ['reports.late', 'Daily Late In', 'alarm', 'Every day an employee clocked in after the grace window, ranked by minutes lost.'],
        ['reports.earlyOut', 'Daily Early Out', 'box-arrow-left', 'Every day an employee left before the early-out grace window.'],
        ['reports.absent', 'Daily Absence', 'person-x', 'Scheduled days with zero punches and no approved leave.'],
        ['reports.exception', 'Daily Exception', 'exclamation-triangle', 'Every scheduled day with a late-in, early-out, absence or incomplete punch.'],
        ['reports.transactions', 'Transaction', 'list-columns', 'Raw device punches for a date range — code, time, state, device and area.'],
        ['reports.punchCard', 'Time Card', 'clock-history', 'Every punch of the day per person, as a single time list.'],
        ['reports.firstLast', 'First & Last', 'arrow-left-right', 'Earliest and latest punch per person per day, straight from the device.'],
        ['reports.firstInLastOut', 'First In / Last Out', 'box-arrow-in-right', 'First check-in and last check-out per person per day, with the span between.'],
        ['reports.summary', 'Summary', 'bar-chart', 'Present / absent / late / overtime day-counts rolled up by department or employee.'],
        ['reports.leaveBalance', 'Leave Balances', 'clipboard-check', 'Entitlement, taken and remaining days per leave type from each employee\'s leave group.'],
    ] as $c): ?>
    <div class="col-md-6">
        <a href="<?= route_to($c[0]) ?>" class="text-decoration-none">
            <div class="card h-100"><div class="card-body d-flex gap-3">
                <div class="stat-icon si-indigo"><i class="bi bi-<?= $c[2] ?>"></i></div>
                <div>
                    <div class="fw-semibold text-body"><?= esc($c[1]) ?></div>
                    <div class="small text-muted"><?= esc($c[3]) ?></div>
                </div>
            </div></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
