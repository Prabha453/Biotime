<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $cur = $cfg->currency; ?>
<?= page_head($title, $subtitle) ?>
<?= view('admin/payroll/_nav', ['tab' => 'reports']) ?>

<div class="subnav mb-3">
    <?php foreach ($reportTabs as $k => $label): ?>
        <a class="<?= $reportTab === $k ? 'is-active' : '' ?>" href="<?= route_to('payroll.reports.tab', $k) ?>?y=<?= $y ?>&m=<?= $m ?><?= $dept ? '&dept=' . $dept : '' ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<form class="d-flex flex-wrap gap-2 align-items-end mb-3">
    <div><label class="form-label tiny">Year</label><input type="number" name="y" class="form-control form-control-sm" style="width:100px" value="<?= (int) $y ?>"></div>
    <div><label class="form-label tiny">Month</label>
        <select name="m" class="form-select form-select-sm">
            <?php for ($mm = 1; $mm <= 12; $mm++): ?><option value="<?= $mm ?>" <?= $mm === (int) $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $mm, 1)) ?></option><?php endfor; ?>
        </select>
    </div>
    <div><label class="form-label tiny">Department</label>
        <select name="dept" class="form-select form-select-sm">
            <option value="">All departments</option>
            <?php foreach ($departments as $dp): ?><option value="<?= $dp['id'] ?>" <?= (int) $dept === (int) $dp['id'] ? 'selected' : '' ?>><?= esc($dp['dept_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
</form>

<?php if ($reportTab === 'structure'): ?>
    <p class="text-muted small">Base monthly salary currently on file, per employee (not month-scoped).</p>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Department</th><th class="text-end">Monthly salary</th><th>Effective from</th><th>Note</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= esc($r['name'] ?: $r['first_name'] ?: ('#' . $r['emp_code'])) ?> <span class="mono text-muted small"><?= esc($r['emp_code']) ?></span></td>
                <td><?= esc($r['dept_name'] ?? '—') ?></td>
                <td class="text-end mono"><?= $cur ?> <?= number_format((float) $r['salary_amount'], 2) ?></td>
                <td class="mono"><?= esc($r['effective_date']) ?></td>
                <td class="small text-muted"><?= esc($r['salary_remark'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No salary structures.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
    <?php if (isset($pager)): ?><div class="px-1 pt-2"><?= pager_bar($pager) ?></div><?php endif; ?>

<?php elseif ($reportTab === 'increases' || $reportTab === 'deductions'): ?>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Department</th><th>Category</th><th>Note</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= esc($r['name'] ?: $r['first_name'] ?: ('#' . $r['emp_code'])) ?> <span class="mono text-muted small"><?= esc($r['emp_code']) ?></span></td>
                <td><?= esc($r['dept_name'] ?? '—') ?></td>
                <td><?= $r['type_name'] ? '<span class="pill pill-muted">' . esc($r['type_name']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                <td class="small text-muted"><?= esc($r['remark'] ?? '') ?></td>
                <td class="mono"><?= esc(substr((string) $r['issued_time'], 0, 10)) ?></td>
                <td class="text-end mono"><?= $cur ?> <?= number_format((float) $r['amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No <?= $reportTab ?> this period.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($rows): ?><tfoot><tr><th colspan="5" class="text-end">Total</th><th class="text-end mono"><?= $cur ?> <?= number_format((float) $total, 2) ?></th></tr></tfoot><?php endif; ?>
    </table></div></div>
    <?php if (isset($pager)): ?><div class="px-1 pt-2"><?= pager_bar($pager) ?></div><?php endif; ?>

<?php elseif ($reportTab === 'details'): ?>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Department</th><th class="text-end">Basic</th><th class="text-end">Increase</th><th class="text-end">Deduction</th><th class="text-end">Net pay</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= esc($r['name'] ?: $r['first_name'] ?: ('#' . $r['emp_code'])) ?> <span class="mono text-muted small"><?= esc($r['emp_code']) ?></span></td>
                <td><?= esc($r['dept_name'] ?? '—') ?></td>
                <td class="text-end mono"><?= $cur ?> <?= number_format((float) $r['basic_salary'], 2) ?></td>
                <td class="text-end mono text-success">+<?= $cur ?> <?= number_format((float) $r['total_increase'], 2) ?></td>
                <td class="text-end mono text-danger">−<?= $cur ?> <?= number_format((float) $r['total_deduction'], 2) ?></td>
                <td class="text-end mono fw-semibold"><?= $cur ?> <?= number_format((float) $r['net_pay'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No payslips generated for this month yet — run payroll first.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>

<?php else: /* attendance parameters */ ?>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Department</th><th class="text-end">Worked days</th><th class="text-end">Absent days</th><th class="text-end">Leave days</th><th class="text-end">Late (min)</th><th class="text-end">OT (hrs)</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="fw-semibold"><?= esc($r['name'] ?: $r['first_name'] ?: ('#' . $r['emp_code'])) ?> <span class="mono text-muted small"><?= esc($r['emp_code']) ?></span></td>
                <td><?= esc($r['dept_name'] ?? '—') ?></td>
                <td class="text-end mono"><?= esc($r['worked_days'] ?? 0) ?></td>
                <td class="text-end mono"><?= esc($r['absent_days'] ?? 0) ?></td>
                <td class="text-end mono"><?= esc($r['leave_days'] ?? 0) ?></td>
                <td class="text-end mono"><?= esc($r['late_minutes'] ?? 0) ?></td>
                <td class="text-end mono"><?= esc($r['ot_hours'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No payslips generated for this month yet — run payroll first.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>
<?= $this->endSection() ?>
