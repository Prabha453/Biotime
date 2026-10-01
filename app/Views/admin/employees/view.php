<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$t = $totals;
$prevM = $month == 1 ? 12 : $month - 1; $prevY = $month == 1 ? $year - 1 : $year;
$nextM = $month == 12 ? 1 : $month + 1; $nextY = $month == 12 ? $year + 1 : $year;
$mUrl = fn ($m, $y) => route_to('employee.view', $emp['id']) . "?m=$m&y=$y";
$actions = '<a href="' . route_to('employee.edit', $emp['id']) . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>'
    . modal_btn('pAdd', 'Add / fix punch', 'fingerprint');
?>
<?= page_head(esc(emp_name($emp)), 'Employee #' . esc($emp['emp_code']), $actions) ?>
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <div class="d-flex gap-3">
                <?= emp_avatar($emp, 'lg') ?>
                <div>
                    <h5 class="mb-0"><?= esc(emp_name($emp)) ?></h5>
                    <div class="text-muted mono">#<?= esc($emp['emp_code']) ?></div>
                    <div class="mt-1">
                        <?php if ($emp['resign_date']): ?><span class="pill pill-danger">Resigned <?= fdate($emp['resign_date']) ?></span>
                        <?php else: ?><span class="pill pill-success">Active</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <hr>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted">Department</dt><dd class="col-7"><?= esc($emp['dept_name'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Position</dt><dd class="col-7"><?= esc($emp['position_name'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Hired</dt><dd class="col-7 mono"><?= fdate($emp['hire_date'] ?? null) ?></dd>
                <dt class="col-5 text-muted">Mobile</dt><dd class="col-7"><?= esc($emp['mobile'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Email</dt><dd class="col-7"><?= esc($emp['email'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Gender</dt><dd class="col-7"><?= esc($emp['gender'] ?? '—') ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Month totals <span class="text-muted small">— <?= (int) $daysInMonth ?> days in <?= date('F Y', strtotime($from)) ?></span></span>
                <div class="btn-group btn-group-sm">
                    <a class="btn btn-outline-secondary" href="<?= $mUrl($prevM, $prevY) ?>"><i class="bi bi-chevron-left"></i></a>
                    <span class="btn btn-outline-secondary disabled"><?= date('F Y', strtotime($from)) ?></span>
                    <a class="btn btn-outline-secondary" href="<?= $mUrl($nextM, $nextY) ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <?php foreach ([
                        ['Days in month', $daysInMonth, 'si-slate'],
                        ['Weekend days', $weekendCount, 'si-slate'],
                        ['Work days', $t['work_days'], 'si-slate'],
                        ['Present', $t['present_days'], 'si-green'],
                        ['Absent', $t['absent_days'], 'si-red'],
                        ['Late days', $t['late_days'], 'si-amber'],
                        ['Incomplete', $t['incomplete'], 'si-amber'],
                        ['Leave', $t['leave_days'], 'si-blue'],
                        ['Worked', mins($t['worked_minutes']), 'si-indigo'],
                        ['Overtime', mins($t['ot_minutes']), 'si-blue'],
                        ['Late total', mins($t['late_minutes']), 'si-amber'],
                        ['Short', mins($t['short_minutes']), 'si-slate'],
                    ] as $k): ?>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2">
                                <div class="stat-label"><?= esc($k[0]) ?></div>
                                <div class="fw-bold fs-5"><?= esc($k[1]) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Daily breakdown</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>Date</th><th>Day</th><th>Scheduled</th><th>Punches</th>
                <th>In</th><th>Out</th><th>Worked</th><th>Late</th><th>Early</th><th>OT</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($days as $d): ?>
                <tr class="<?= $d['is_weekend'] || $d['is_holiday'] ? 'table-light' : '' ?>">
                    <td class="mono"><?= esc(substr($d['date'], 5)) ?></td>
                    <td class="small text-muted"><?= esc($d['weekday']) ?></td>
                    <td class="mono small"><?= $d['scheduled'] ? esc($d['scheduled']['in'] . '–' . $d['scheduled']['out']) : '—' ?></td>
                    <td>
                        <?php foreach ($d['punches'] as $p): ?><?= punch_chip($p) ?><?php endforeach; ?>
                        <?php if (! $d['punches']): ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="mono"><?= esc($d['first_in'] ?? '—') ?></td>
                    <td class="mono"><?= esc($d['last_out'] ?? '—') ?></td>
                    <td class="mono"><?= mins($d['worked_minutes']) ?></td>
                    <td class="mono text-danger"><?= mins($d['late_minutes']) ?></td>
                    <td class="mono text-danger"><?= mins($d['early_minutes']) ?></td>
                    <td class="mono text-success"><?= mins($d['ot_minutes']) ?></td>
                    <td><?= status_pill($d['status'], $d['status_class']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="pAdd" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('punch.add')) ?>
    <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
    <div class="modal-header"><h5 class="modal-title">Add punch for <?= esc(emp_name($emp)) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Use this to fix a forgotten check-out or a missed scan. The register recalculates immediately.</p>
        <div class="row g-2">
            <div class="col-6"><label class="form-label tiny">Date</label><input type="date" name="punch_date" class="form-control" value="<?= sprintf('%04d-%02d-%02d', $year, $month, min((int) date('d'), (int) date('t', strtotime($from)))) ?>" required></div>
            <div class="col-3"><label class="form-label tiny">Time</label><input type="time" name="punch_time" class="form-control" required></div>
            <div class="col-3"><label class="form-label tiny">Event</label>
                <select name="punch_state" class="form-select">
                    <option value="0">Check In</option><option value="1">Check Out</option>
                    <option value="2">Break Out</option><option value="3">Break In</option>
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add punch</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
