<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $cur = $cfg->currency; ?>
<?= page_head($title, $subtitle, modal_btn('advNew', 'Add advance')) ?>
<?= view('admin/payroll/_nav', ['tab' => 'advances']) ?>

<div class="card">
    <div class="card-header">Salary advances <span class="text-muted small">— deducted in full in the month they fall in</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th class="text-end">Amount</th><th>Date</th><th>Note</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($advances as $a): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($a['name'] ?: $a['first_name'] ?: ('#' . $a['emp_code'])) ?> <span class="mono text-muted"><?= esc($a['emp_code']) ?></span></td>
                    <td class="text-end mono"><?= $cur ?> <?= number_format((float) $a['advance_amount'], 2) ?></td>
                    <td class="mono"><?= esc(substr((string) $a['advance_time'], 0, 10)) ?></td>
                    <td class="small text-muted"><?= esc($a['advance_remark'] ?? '') ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('payroll.advance.delete', $a['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this advance?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $advances): ?><tr><td colspan="5" class="text-center text-muted py-4">No advances recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($advPager)): ?><div class="px-3 pb-2"><?= $advPager->links('adv') ?></div><?php endif; ?>
</div>

<?php
$empOptions = '';
foreach ($employees as $e) {
    $empOptions .= '<option value="' . $e['id'] . '">' . esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) . '</option>';
}
?>
<div class="modal fade" id="advNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payroll.advance.save')) ?>
    <div class="modal-header"><h5 class="modal-title">Add salary advance</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required><option value="">Select…</option><?= $empOptions ?></select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Amount (<?= $cur ?>) *</label><input type="number" step="0.01" min="0" name="advance_amount" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">Date *</label><input type="date" name="advance_time" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="mb-1"><label class="form-label tiny">Note</label><input name="advance_remark" class="form-control" maxlength="120"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add advance</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
