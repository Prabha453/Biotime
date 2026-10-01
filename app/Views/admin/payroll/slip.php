<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$months = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
$cur = $cfg->currency;
$e   = $s['employee'];
?>
<?= page_head('Payslip', emp_name($e) . ' · ' . $months[$m] . ' ' . $y,
    '<a class="btn btn-sm btn-outline-secondary" href="' . route_to('payroll') . '?y=' . $y . '&m=' . $m . '"><i class="bi bi-arrow-left me-1"></i>Back</a>'
    . '<button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>') ?>

<?php if (! $stored): ?>
    <div class="ext-note"><i class="bi bi-info-circle"></i>
        This is a <strong>live preview</strong> — payroll has not been run for this month yet, so nothing is stored. Run it from the Payroll page to save.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Earnings &amp; deductions</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <?php foreach ($s['earnings'] as $row): ?>
                        <dt><?= esc($row['label']) ?></dt><dd><?= $cur ?> <?= number_format((float) $row['amount'], 2) ?></dd>
                    <?php endforeach; ?>
                    <div class="row-sep"></div>
                    <dt class="fw-semibold">Total earnings</dt><dd class="fw-semibold"><?= $cur ?> <?= number_format((float) $s['total_earnings'], 2) ?></dd>

                    <div class="row-sep"></div>
                    <?php if ($s['deductions']): foreach ($s['deductions'] as $row): ?>
                        <dt><?= esc($row['label']) ?></dt><dd class="text-danger">− <?= $cur ?> <?= number_format((float) $row['amount'], 2) ?></dd>
                    <?php endforeach; else: ?>
                        <dt class="text-muted">No deductions</dt><dd>—</dd>
                    <?php endif; ?>
                    <div class="row-sep"></div>
                    <dt class="fw-semibold">Total deductions</dt>
                    <dd class="fw-semibold <?= $s['total_deductions'] > 0 ? 'text-danger' : '' ?>">
                        <?= $s['total_deductions'] > 0 ? '− ' : '' ?><?= $cur ?> <?= number_format((float) $s['total_deductions'], 2) ?>
                    </dd>

                    <div class="row-sep"></div>
                    <div class="grand"><dt>Net pay</dt><dd><?= $cur ?> <?= number_format((float) $s['net_pay'], 2) ?></dd></div>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Employee</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Name</dt><dd><?= esc(emp_name($e)) ?></dd>
                    <dt>Code</dt><dd class="mono"><?= esc($e['emp_code']) ?></dd>
                    <dt>Department</dt><dd><?= esc($e['dept_name'] ?? '—') ?></dd>
                    <dt>Position</dt><dd><?= esc($e['position_name'] ?? '—') ?></dd>
                    <dt>Period</dt><dd><?= esc($months[$m]) ?> <?= $y ?></dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Attendance basis</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Worked days</dt><dd><?= $s['worked_days'] ?></dd>
                    <dt>Absent days</dt><dd class="<?= $s['absent_days'] ? 'text-danger' : '' ?>"><?= $s['absent_days'] ?></dd>
                    <dt>Leave days</dt><dd><?= $s['leave_days'] ?></dd>
                    <dt>Late</dt><dd><?= mins($s['late_minutes']) ?></dd>
                    <dt>Overtime</dt><dd><?= $s['ot_hours'] ?> h</dd>
                    <div class="row-sep"></div>
                    <dt>Day rate</dt><dd><?= $cur ?> <?= number_format((float) $s['day_rate'], 2) ?></dd>
                    <dt>Hour rate</dt><dd><?= $cur ?> <?= number_format((float) $s['hour_rate'], 2) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
