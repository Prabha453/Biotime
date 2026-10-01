<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $cur = $cfg->currency; ?>
<?= page_head($title, $subtitle, modal_btn('loanNew', 'Add loan')) ?>
<?= view('admin/payroll/_nav', ['tab' => 'loans']) ?>

<div class="card">
    <div class="card-header">Loans <span class="text-muted small">— <?= $cur ?> installment deducted every payroll run until settled</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th class="text-end">Loan</th><th class="text-end">Per cycle</th><th>Cycles</th><th>Taken</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($loans as $l): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($l['name'] ?: $l['first_name'] ?: ('#' . $l['emp_code'])) ?> <span class="mono text-muted"><?= esc($l['emp_code']) ?></span></td>
                    <td class="text-end mono"><?= $cur ?> <?= number_format((float) $l['loan_amount'], 2) ?></td>
                    <td class="text-end mono"><?= $cur ?> <?= number_format((float) $l['per_cycle_refund'], 2) ?></td>
                    <td class="mono"><?= (int) $l['refund_cycle'] ?></td>
                    <td class="mono"><?= esc(substr((string) $l['loan_time'], 0, 10)) ?></td>
                    <td><?= $l['loan_clean_time'] ? '<span class="pill pill-muted">Settled</span>' : '<span class="pill pill-info">Active</span>' ?></td>
                    <td class="text-end">
                        <?php if (! $l['loan_clean_time']): ?>
                            <?= form_open(route_to('payroll.loan.settle', $l['id']), ['class' => 'd-inline', 'data-confirm' => 'Mark settled? No further installments.']) ?>
                                <button class="btn btn-sm btn-outline-secondary" title="Mark settled"><i class="bi bi-check2-all"></i></button>
                            <?= form_close() ?>
                        <?php endif; ?>
                        <?= form_open(route_to('payroll.loan.delete', $l['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this loan?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $loans): ?><tr><td colspan="7" class="text-center text-muted py-4">No active loans.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($loanPager)): ?><div class="px-3 pb-2"><?= $loanPager->links('loans') ?></div><?php endif; ?>
</div>

<?php
$empOptions = '';
foreach ($employees as $e) {
    $empOptions .= '<option value="' . $e['id'] . '">' . esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) . '</option>';
}
?>
<div class="modal fade" id="loanNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payroll.loan.save')) ?>
    <div class="modal-header"><h5 class="modal-title">Add loan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required><option value="">Select…</option><?= $empOptions ?></select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Loan amount (<?= $cur ?>) *</label><input type="number" step="0.01" min="0" name="loan_amount" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">Date taken *</label><input type="date" name="loan_time" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Repayment cycles *</label><input type="number" min="1" name="refund_cycle" class="form-control" required value="12"></div>
            <div class="col"><label class="form-label tiny">Per-cycle deduction (<?= $cur ?>) *</label><input type="number" step="0.01" min="0" name="per_cycle_refund" class="form-control" required></div>
        </div>
        <div class="mb-1"><label class="form-label tiny">Note</label><input name="remark" class="form-control" maxlength="120"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add loan</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
