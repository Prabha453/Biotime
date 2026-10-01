<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php $period = date('F Y', mktime(0, 0, 0, $m, 1, $y)); ?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= route_to('portal.payslips') ?>"><i class="bi bi-arrow-left me-1"></i>All payslips</a>
    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    <span class="ms-auto fw-semibold"><?= esc($period) ?></span>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h2 class="h6">Earnings &amp; deductions</h2>
            <div class="table-responsive">
            <table class="table mb-0 mt-2">
                <tbody>
                <?php foreach ($s['earnings'] as $r): ?>
                    <tr><td><?= esc($r['label']) ?></td><td class="text-end mono"><?= $cur ?> <?= number_format((float) $r['amount'], 2) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-semibold border-top"><td>Total earnings</td><td class="text-end mono"><?= $cur ?> <?= number_format((float) $s['total_earnings'], 2) ?></td></tr>
                <?php foreach ($s['deductions'] as $r): ?>
                    <tr class="text-danger"><td><?= esc($r['label']) ?></td><td class="text-end mono">− <?= $cur ?> <?= number_format((float) $r['amount'], 2) ?></td></tr>
                <?php endforeach; ?>
                <?php if (! $s['deductions']): ?><tr class="text-muted"><td>No deductions</td><td class="text-end">—</td></tr><?php endif; ?>
                <tr class="fw-semibold border-top <?= $s['total_deductions'] > 0 ? 'text-danger' : '' ?>">
                    <td>Total deductions</td>
                    <td class="text-end mono"><?= $s['total_deductions'] > 0 ? '− ' : '' ?><?= $cur ?> <?= number_format((float) $s['total_deductions'], 2) ?></td>
                </tr>
                <tr class="fw-bold border-top" style="font-size:1.05rem"><td>Net pay</td><td class="text-end mono"><?= $cur ?> <?= number_format((float) $s['net_pay'], 2) ?></td></tr>
                </tbody>
            </table>
            </div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6">Employee</h2>
            <div class="table-responsive">
            <table class="table table-sm mb-0 mt-2"><tbody>
                <tr><td class="text-muted">Name</td><td><?= esc($emp['name'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Code</td><td class="mono"><?= esc($emp['emp_code']) ?></td></tr>
                <tr><td class="text-muted">Department</td><td><?= esc($emp['dept_name'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Position</td><td><?= esc($emp['position_name'] ?? '—') ?></td></tr>
                <tr><td class="text-muted">Period</td><td><?= esc($period) ?></td></tr>
            </tbody></table>
            </div>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6">Attendance basis</h2>
            <div class="table-responsive">
            <table class="table table-sm mb-0 mt-2"><tbody>
                <tr><td class="text-muted">Worked days</td><td><?= $s['worked_days'] ?></td></tr>
                <tr><td class="text-muted">Absent days</td><td class="<?= $s['absent_days'] ? 'text-danger' : '' ?>"><?= $s['absent_days'] ?></td></tr>
                <tr><td class="text-muted">Leave days</td><td><?= $s['leave_days'] ?></td></tr>
                <tr><td class="text-muted">Late</td><td><?= mins($s['late_minutes']) ?></td></tr>
                <tr><td class="text-muted">Overtime</td><td><?= $s['ot_hours'] ?> h</td></tr>
                <tr><td class="text-muted">Day rate</td><td class="mono"><?= $cur ?> <?= number_format((float) $s['day_rate'], 2) ?></td></tr>
                <tr><td class="text-muted">Hour rate</td><td class="mono"><?= $cur ?> <?= number_format((float) $s['hour_rate'], 2) ?></td></tr>
            </tbody></table>
            </div>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
