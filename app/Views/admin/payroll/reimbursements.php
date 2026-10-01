<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$cur = $cfg->currency;
$empOptions = '';
foreach ($employees as $e) {
    $empOptions .= '<option value="' . $e['id'] . '">' . esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) . '</option>';
}
?>
<?= page_head($title, $subtitle, modal_btn('adj_reimbursement', 'Add reimbursement')) ?>
<?= view('admin/payroll/_nav', ['tab' => 'reimbursements']) ?>

<div class="card">
    <div class="card-header"><i class="bi bi-receipt text-info"></i> Reimbursements <span class="text-muted small">— expense claims · added to earnings</span></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Note</th><th>Date</th><th class="text-end">Amount</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($rmb as $r): ?>
            <tr>
                <td><span class="fw-semibold"><?= esc($r['name'] ?: ('#' . $r['emp_code'])) ?></span> <span class="mono text-muted"><?= esc($r['emp_code']) ?></span></td>
                <td class="small text-muted"><?= esc($r['rmb_remark'] ?? '') ?></td>
                <td class="mono"><?= esc(substr((string) $r['rmb_time'], 0, 10)) ?></td>
                <td class="text-end mono text-success">+<?= $cur ?> <?= number_format((float) $r['rmb_amount'], 2) ?></td>
                <td class="text-end">
                    <?= form_open(route_to('payroll.adjustment.delete', 'reimbursement', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rmb): ?><tr><td colspan="5" class="text-center text-muted py-4">No reimbursements.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if (isset($rmbPager)): ?><div class="px-3 pb-2"><?= $rmbPager->links('rmb') ?></div><?php endif; ?>
</div>

<div class="modal fade" id="adj_reimbursement" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payroll.adjustment.save', 'reimbursement')) ?>
    <div class="modal-header"><h5 class="modal-title">Add reimbursement</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required><option value="">Select…</option><?= $empOptions ?></select>
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
<?= $this->endSection() ?>
