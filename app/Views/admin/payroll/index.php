<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$months = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
$q  = http_build_query(['y' => $y, 'm' => $m, 'dept' => $dept]);
$cur = $cfg->currency;
?>
<?= page_head($title, $subtitle, $slips
    ? '<a class="btn btn-sm btn-outline-success" href="' . route_to('payroll.export') . '?' . $q . '"><i class="bi bi-download me-1"></i>Export CSV</a>'
    : '') ?>
<?= view('admin/payroll/_nav', ['tab' => 'payslips']) ?>

<form class="filter-bar mb-3" method="get" action="<?= route_to('payroll') ?>">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label">Month</label>
            <select name="m" class="form-select">
                <?php foreach ($months as $n => $lbl): ?><option value="<?= $n ?>" <?= $m === $n ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Year</label>
            <input type="number" name="y" value="<?= $y ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>View</button>
        </div>
    </div>
</form>

<div class="kpi-row">
    <div class="kpi"><div class="k-label">Payslips</div><div class="k-value"><?= count($slips) ?></div><div class="k-sub"><?= esc($months[$m]) ?> <?= $y ?></div></div>
    <div class="kpi"><div class="k-label">Total basic</div><div class="k-value"><?= $cur ?> <?= number_format($totals['basic'], 0) ?></div></div>
    <div class="kpi neg"><div class="k-label">Total deductions</div><div class="k-value"><?= $cur ?> <?= number_format($totals['ded'], 0) ?></div></div>
    <div class="kpi pos"><div class="k-label">Net payable</div><div class="k-value"><?= $cur ?> <?= number_format($totals['net'], 0) ?></div></div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Run payroll</span>
        <span class="text-muted small">recomputes &amp; replaces payslips for <?= esc($months[$m]) ?> <?= $y ?></span>
    </div>
    <div class="card-body">
        <?= form_open(route_to('payroll.generate'), ['class' => 'd-flex flex-wrap gap-2 align-items-center', 'data-confirm' => 'Recompute payslips for this month? Existing ones are replaced.']) ?>
            <input type="hidden" name="y" value="<?= $y ?>">
            <input type="hidden" name="m" value="<?= $m ?>">
            <input type="hidden" name="dept" value="<?= esc($dept) ?>">
            <button class="btn btn-primary"><i class="bi bi-calculator me-1"></i>Run for <?= esc($months[$m]) ?> <?= $y ?><?= $dept ? ' · one department' : ' · all staff' ?></button>
            <span class="text-muted small">Pulls worked days, absences, late minutes and overtime from the biometric register.</span>
        <?= form_close() ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Employee</th><th>Code</th><th>Department</th>
                <th class="text-end">Basic</th><th class="text-end">Additions</th>
                <th class="text-end">Deductions</th><th class="text-end">Net</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($slips as $s): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($s['name'] ?: $s['first_name'] ?: ('#' . $s['emp_code'])) ?></td>
                    <td class="mono"><?= esc($s['emp_code']) ?></td>
                    <td class="text-muted"><?= esc($s['dept_name'] ?? '—') ?></td>
                    <td class="text-end mono"><?= number_format((float) $s['basic_salary'], 2) ?></td>
                    <td class="text-end mono text-success"><?= number_format((float) $s['total_increase'], 2) ?></td>
                    <td class="text-end mono text-danger"><?= number_format((float) $s['total_deduction'], 2) ?></td>
                    <td class="text-end mono fw-bold"><?= number_format((float) $s['net_pay'], 2) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= route_to('payroll.slip', $s['employee_id']) ?>?y=<?= $y ?>&m=<?= $m ?>">Payslip</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $slips): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-cash-stack"></i>
                    No payslips for <?= esc($months[$m]) ?> <?= $y ?> yet. Set salary structures, then run payroll above.
                </div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
