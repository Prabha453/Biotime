<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$num = static fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'New balance', 'modal' => 'lbForm'],
    'delete'       => route_to('leavebalance.bulk_delete'),
    'import'       => 'leavebalance',
    'export'       => ['key' => 'leave_balance'],
    'export_qs'    => ['year' => $year, 'dept' => $dept ?: ''],
    'columns'      => true,
    'filter'       => '#lbFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'personnel.leavebal', 'label' => 'Leave Balance'],
]) ?>
<?= filter_builder('lbFilter', [
    'emp_code'         => ['label' => 'Employee ID', 'type' => 'text'],
    'emp_name'         => ['label' => 'Employee name', 'type' => 'text'],
    'pay_code'         => ['label' => 'Leave type code', 'type' => 'text'],
    'entitlement_days' => ['label' => 'Entitlement', 'type' => 'number'],
    'pre_balance'      => ['label' => 'Carried forward', 'type' => 'number'],
], ['button' => false]) ?>

<form method="get" class="card card-body mb-3 d-flex flex-row flex-wrap gap-2 align-items-end" data-crud-filter>
    <div>
        <label class="form-label tiny mb-1">Year</label>
        <select name="year" class="form-select form-select-sm w-auto" data-native onchange="this.form.submit()">
            <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label tiny mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm w-auto" data-native onchange="this.form.submit()">
            <option value="">All departments</option>
            <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= (int) $d['id'] === $dept ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="flex-grow-1" style="max-width:280px">
        <label class="form-label tiny mb-1">Search</label>
        <input name="q" value="<?= esc($q, 'attr') ?>" class="form-control form-control-sm" placeholder="Name or code">
    </div>
    <button class="btn btn-sm btn-outline-secondary">Apply</button>
    <?= form_open(route_to('leavebalance.seed'), ['class' => 'ms-auto', 'data-confirm' => 'Seed missing rows for ' . $year . ' from each employee\'s leave-group quota?']) ?>
        <input type="hidden" name="year" value="<?= $year ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-magic me-1"></i>Seed from quotas</button>
    <?= form_close() ?>
</form>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll" aria-label="Select all"></th>
        <th data-col="employee">Employee</th><th data-col="dept">Department</th><th data-col="type">Leave type</th>
        <th data-col="entitlement" class="text-end">Entitlement</th><th data-col="carried" class="text-end">Carried</th>
        <th data-col="taken" class="text-end">Taken</th><th data-col="balance" class="text-end">Balance</th>
        <th class="text-end">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $bal = (float) $r['entitlement_days'] + (float) $r['pre_balance'] - (float) $r['taken']; ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td>
                <span class="fw-semibold"><?= esc($r['emp_name'] ?: $r['emp_code']) ?></span>
                <span class="text-muted small d-block mono"><?= esc($r['emp_code']) ?></span>
            </td>
            <td class="text-muted"><?= esc($r['dept_name'] ?? '—') ?></td>
            <td><span class="mono"><?= esc($r['pay_code'] ?? '?') ?></span> <?= esc($r['pay_name'] ?? '') ?></td>
            <td class="text-end"><?= $num($r['entitlement_days']) ?></td>
            <td class="text-end"><?= $num($r['pre_balance']) ?></td>
            <td class="text-end"><?= $num($r['taken']) ?></td>
            <td class="text-end fw-semibold <?= $bal < 0 ? 'text-danger' : '' ?>"><?= $num($bal) ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="btn btn-sm btn-outline-secondary js-lb" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('leavebalance.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this leave balance?', 'data-confirm-danger' => '']) ?>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="9"><div class="empty-state"><i class="bi bi-calendar2-week"></i>No leave balances for <?= $year ?>. Add one, or seed from leave-group quotas.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="lbForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('leavebalance.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Leave balance</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc(($e['name'] ?: $e['emp_code']) . ' (' . $e['emp_code'] . ')') ?></option><?php endforeach; ?>
            </select></div>
        <div class="row g-2">
            <div class="col-7 mb-2"><label class="form-label tiny">Leave type *</label>
                <select name="pay_code_id" class="form-select" required>
                    <option value="">Select…</option>
                    <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['code'] . ' — ' . $t['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-5 mb-2"><label class="form-label tiny">Year *</label>
                <select name="year" class="form-select" required>
                    <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label tiny">Entitlement (days)</label><input name="entitlement_days" type="number" step="0.5" min="0" class="form-control" value="0"></div>
            <div class="col-6 mb-2"><label class="form-label tiny">Carried forward (days)</label><input name="pre_balance" type="number" step="0.5" class="form-control" value="0"></div>
        </div>
        <p class="text-muted tiny mb-0">Taken days and the remaining balance are computed from approved leave requests.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-lb').forEach(function (b) {
    b.addEventListener('click', function () {
        var m = document.getElementById('lbForm'), form = m.querySelector('form');
        form.reset();
        var d = JSON.parse(b.dataset.json || '{}');
        form.querySelector('[name=id]').value = d.id || '';
        form.querySelector('[name=employee_id]').value = d.employee_id || '';
        form.querySelector('[name=pay_code_id]').value = d.pay_code_id || '';
        form.querySelector('[name=year]').value = d.year || '';
        form.querySelector('[name=entitlement_days]').value = d.entitlement_days == null ? 0 : d.entitlement_days;
        form.querySelector('[name=pre_balance]').value = d.pre_balance == null ? 0 : d.pre_balance;
        new bootstrap.Modal(m).show();
    });
});
</script>
<?= $this->endSection() ?>
