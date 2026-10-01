<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$cur = $cfg->currency;
$empOptions = '';
foreach ($employees as $e) {
    $empOptions .= '<option value="' . $e['id'] . '">' . esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) . '</option>';
}
$typeOptions = '<option value="">— uncategorised —</option>';
foreach ($types as $t) {
    $typeOptions .= '<option value="' . $t['id'] . '">' . esc($t['name']) . '</option>';
}
?>
<?= page_head($title, $subtitle, modal_btn('adj_increase', 'Add increase')) ?>
<?= view('admin/payroll/_nav', ['tab' => 'increases']) ?>

<div class="card">
    <div class="card-header"><i class="bi bi-plus-circle text-success"></i> Extra increases <span class="text-muted small">— bonuses, allowances, back-pay · added to earnings</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Category</th><th>Note</th><th>Date</th><th class="text-end">Amount</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($inc as $r): ?>
            <tr>
                <td><span class="fw-semibold"><?= esc($r['name'] ?: ('#' . $r['emp_code'])) ?></span> <span class="mono text-muted"><?= esc($r['emp_code']) ?></span></td>
                <td><?= $r['type_name'] ? '<span class="pill pill-muted">' . esc($r['type_name']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                <td class="small text-muted"><?= esc($r['remark'] ?? '') ?></td>
                <td class="mono"><?= esc(substr((string) $r['issued_time'], 0, 10)) ?></td>
                <td class="text-end mono text-success">+<?= $cur ?> <?= number_format((float) $r['amount'], 2) ?></td>
                <td class="text-end">
                    <?= form_open(route_to('payroll.adjustment.delete', 'increase', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $inc): ?><tr><td colspan="6" class="text-center text-muted py-4">No extra increases.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if (isset($incPager)): ?><div class="px-3 pb-2"><?= $incPager->links('inc') ?></div><?php endif; ?>
</div>

<div class="modal fade" id="adj_increase" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payroll.adjustment.save', 'increase')) ?>
    <div class="modal-header"><h5 class="modal-title">Add extra increase</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required><option value="">Select…</option><?= $empOptions ?></select>
        </div>
        <div class="mb-2"><label class="form-label tiny">Category</label>
            <select name="special_type_id" class="form-select"><?= $typeOptions ?></select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Amount (<?= $cur ?>) *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">Date *</label><input type="date" name="issued_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="mb-1"><label class="form-label tiny">Note</label><input name="remark" class="form-control" maxlength="300"></div>
        <p class="ext-note mt-2 mb-0"><i class="bi bi-info-circle"></i> Applied to the payroll run for the month this date falls in. Re-run that month after adding.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<div class="modal fade" id="adjType" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payroll.adjustment.type.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New adjustment category</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label><input name="name" class="form-control" required maxlength="255" placeholder="Housing allowance"></div>
        <div class="mb-1"><label class="form-label tiny">Note</label><input name="remark" class="form-control" maxlength="200"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div>
    <?= form_close() ?>
</div></div></div>
<p class="text-muted small mt-2">Categories: <?= count($types) ?> defined. <a href="#" data-bs-toggle="modal" data-bs-target="#adjType">Add a category</a></p>
<?= $this->endSection() ?>
