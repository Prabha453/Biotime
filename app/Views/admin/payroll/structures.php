<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $cur = $cfg->currency; ?>
<?= page_head($title, $subtitle, modal_btn('salNew', 'Add salary')) ?>
<?= view('admin/payroll/_nav', ['tab' => 'salary']) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th>Code</th><th class="text-end">Monthly salary</th><th>Effective from</th><th>Note</th><th>Formulas</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($r['name'] ?: $r['first_name'] ?: ('#' . $r['emp_code'])) ?></td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td class="text-end mono"><?= $cur ?> <?= number_format((float) $r['salary_amount'], 2) ?></td>
                    <td class="mono"><?= esc($r['effective_date']) ?></td>
                    <td class="small text-muted"><?= esc($r['salary_remark'] ?? '') ?></td>
                    <td class="small text-muted">
                        <?php $fc = array_sum(array_map('count', $r['formulas'] ?? [])); ?>
                        <?= $fc ? $fc . ' attached' : '—' ?>
                    </td>
                    <td class="text-end">
                        <?= form_open(route_to('payroll.structure.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this salary record?']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-wallet2"></i>
                    No salary structures yet. Payroll can't produce a payslip without a base salary.
                </div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="salNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payroll.structure.save')) ?>
    <div class="modal-header"><h5 class="modal-title">Add salary structure</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Monthly salary (<?= $cur ?>) *</label>
                <input type="number" step="0.01" min="0" name="salary_amount" class="form-control" required>
            </div>
            <div class="col"><label class="form-label tiny">Effective from *</label>
                <input type="date" name="effective_date" class="form-control" required value="<?= date('Y-m-01') ?>">
            </div>
        </div>
        <div class="mb-1"><label class="form-label tiny">Note</label>
            <input name="salary_remark" class="form-control" maxlength="120" placeholder="e.g. annual increment">
        </div>
        <hr class="my-2">
        <p class="form-hint mb-2">Formulas attached here (Payroll Setup ▸ Formulas) apply only to this structure — when off, the flat rates in Payroll Setup ▸ Payslip Settings are used instead.</p>
        <?php foreach ($formulaKinds as $kind => [, $label]): ?>
            <div class="mb-2">
                <label class="form-label tiny"><?= esc($label) ?></label>
                <?php if (! $formulaLists[$kind]): ?>
                    <div class="small text-muted">No <?= esc($label) ?> formulas configured yet.</div>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($formulaLists[$kind] as $f): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="<?= esc($kind, 'attr') ?>_ids[]" value="<?= (int) $f['id'] ?>" id="fk_<?= esc($kind, 'attr') ?>_<?= (int) $f['id'] ?>">
                                <label class="form-check-label small" for="fk_<?= esc($kind, 'attr') ?>_<?= (int) $f['id'] ?>"><?= esc($f['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
