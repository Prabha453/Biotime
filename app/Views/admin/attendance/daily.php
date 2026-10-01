<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$q = http_build_query(['date' => $date, 'dept' => $dept]);
?>
<?= page_head($title, $subtitle,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
  . '<a class="btn btn-sm btn-outline-success" href="' . route_to('att.daily.export') . '?' . $q . '"><i class="bi bi-download me-1"></i>Export CSV</a>') ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Date</label>
            <input type="date" name="date" value="<?= esc($date) ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Show</button>
            <a class="btn btn-outline-secondary" href="<?= route_to('att.daily') ?>?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>"><i class="bi bi-chevron-left"></i></a>
            <a class="btn btn-outline-secondary" href="<?= route_to('att.daily') ?>?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>
</form>

<div class="row g-2 mb-3">
    <?php foreach ([
        ['Present', $summary['present'], 'success'],
        ['Late', $summary['late'], 'warning'],
        ['Early out', $summary['early'], 'warning'],
        ['Absent', $summary['absent'], 'danger'],
        ['Incomplete', $summary['incomplete'], 'warning'],
        ['On leave', $summary['leave'], 'info'],
        ['Off / weekend', $summary['off'], 'muted'],
    ] as $s): ?>
    <div class="col-4 col-md-2">
        <div class="card"><div class="card-body py-2 text-center">
            <div class="fs-4 fw-bold"><?= $s[1] ?></div>
            <span class="pill pill-<?= $s[2] ?>"><?= $s[0] ?></span>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th data-col="employee">Employee</th><th data-col="code">Code</th><th data-col="shift">Shift</th><th data-col="scheduled">Scheduled</th><th data-col="punches">Punches</th>
                <th data-col="in">In</th><th data-col="out">Out</th><th data-col="worked">Worked</th><th data-col="late">Late</th><th data-col="early">Early</th><th data-col="ot">OT</th><th data-col="status">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $e = $r['employee']; ?>
                <tr>
                    <td>
                        <a href="<?= route_to('employee.view', $e['id']) ?>?m=<?= (int) substr($date, 5, 2) ?>&y=<?= (int) substr($date, 0, 4) ?>"
                           class="fw-semibold text-decoration-none"><?= esc(emp_name($e)) ?></a>
                    </td>
                    <td class="mono"><?= esc($e['emp_code']) ?></td>
                    <td class="small text-muted"><?= esc($r['scheduled']['timetable'] ?? '—') ?></td>
                    <td class="mono small"><?= $r['scheduled'] ? esc($r['scheduled']['in'] . '–' . $r['scheduled']['out']) : '—' ?></td>
                    <td><?php foreach ($r['punches'] as $p): ?><?= punch_chip($p) ?><?php endforeach; ?><?php if (! $r['punches']): ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td class="mono"><?= esc($r['first_in'] ?? '—') ?></td>
                    <td class="mono"><?= esc($r['last_out'] ?? '—') ?></td>
                    <td class="mono"><?= mins($r['worked_minutes']) ?></td>
                    <td class="mono text-danger"><?= mins($r['late_minutes']) ?></td>
                    <td class="mono text-danger"><?= mins($r['early_minutes']) ?></td>
                    <td class="mono text-success"><?= mins($r['ot_minutes']) ?></td>
                    <td><?= status_pill($r['status'], $r['status_class']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="12" class="text-center text-muted py-4">No employees.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
