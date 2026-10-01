<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>

<?php if ($tab === 'attributes'): ?>
<?= grid_toolbar([
    'add'          => ['label' => 'New attribute', 'modal' => 'cfForm'],
    'delete'       => route_to('personnelextra.field.bulk_delete'),
    'export'       => ['key' => 'custom_attrs'],
    'columns'      => true,
    'filter'       => '#caFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'personnel.attr', 'label' => 'Custom Attribute'],
]) ?>
<?= filter_builder('caFilter', [
    'attr_name' => ['label' => 'Name', 'type' => 'text'],
    'attr_type' => ['label' => 'Type', 'type' => 'select', 'options' => $types],
    'is_unique' => ['label' => 'Unique', 'type' => 'bool'],
    'enable'    => ['label' => 'Enabled', 'type' => 'bool'],
], ['button' => false]) ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll" aria-label="Select all"></th><th data-col="name">Name</th><th data-col="type">Type</th><th data-col="options">Options</th><th data-col="unique">Unique</th><th data-col="status">Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td class="fw-semibold"><?= esc($r['attr_name']) ?></td>
            <td><?= esc($types[(int) $r['attr_type']] ?? $r['attr_type']) ?></td>
            <td class="text-muted small">
                <?php
                $optsDisplay = $r['attr_value'] ?? '';
                if ((int) $r['attr_type'] === 2 && $optsDisplay !== '') {
                    $decoded = json_decode($optsDisplay, true);
                    if (is_array($decoded)) {
                        $optsDisplay = implode(', ', array_column($decoded, 'option'));
                    }
                }
                ?>
                <?= esc($optsDisplay) ?>
            </td>
            <td><?= ($r['is_unique'] === true || $r['is_unique'] === 't') ? label('unique', 'info') : '' ?></td>
            <td><?= ($r['enable'] === true || $r['enable'] === 't') ? label('enabled', 'success') : label('disabled', 'secondary') ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="btn btn-sm btn-outline-secondary js-cf" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('personnelextra.field.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this attribute?', 'data-confirm-danger' => '']) ?>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-input-cursor-text"></i>No custom attributes defined.</div></td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="cfForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('personnelextra.field.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Custom attribute</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Attribute name *</label><input name="attr_name" class="form-control" required maxlength="50"></div>
        <div class="mb-2"><label class="form-label tiny">Type</label>
            <select name="attr_type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label tiny">Combobox options (comma separated)</label><input name="attr_value" class="form-control" maxlength="999" placeholder="Married, Single, Other"></div>
        <div class="d-flex gap-3">
            <label class="form-check"><input type="checkbox" class="form-check-input" name="is_unique" value="1"> <span class="form-check-label tiny">Must be unique</span></label>
            <label class="form-check"><input type="checkbox" class="form-check-input" name="enable" value="1" checked> <span class="form-check-label tiny">Enabled</span></label>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<?php else: /* config — Personnel parameters */ ?>
<?= form_open(route_to('personnelextra.config.save')) ?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Code Setting</div>
        <div class="card-body">
            <?= field_switch('num_emp_code', 'Employee ID must be numeric', ['checked' => $cfg['num_emp_code']]) ?>
            <?= field_switch('order_by_num_emp_code', 'Sort the employee list by numeric ID', ['checked' => $cfg['order_by_num_emp_code']]) ?>
            <p class="form-hint mt-1 mb-0"><i class="bi bi-info-circle me-1"></i>The list already sorts numerically everywhere — this switch doesn't change that yet.</p>
            <div class="mt-2"><?= field_text('emp_default_password', 'Default self-service password', ['col' => '', 'value' => $cfg['emp_default_password'], 'maxlength' => 30, 'hint' => 'Applied to new employees who have no password set.']) ?></div>
        </div>
    </div></div>
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">QR Code Setting</div>
        <div class="card-body">
            <?= field_switch('enable_access_qrcode', 'Enable employee access QR code', ['checked' => $cfg['enable_access_qrcode']]) ?>
            <div class="row g-3 mt-1">
                <?= field_number('qrcode_validity_period_access', 'Access QR validity (minutes)', ['col' => 'col-sm-6', 'min' => 1, 'max' => 1440, 'value' => $cfg['qrcode_validity_period_access']]) ?>
                <?= field_number('qrcode_validity_period_biophoto', 'Bio-photo QR validity (minutes)', ['col' => 'col-sm-6', 'min' => 1, 'max' => 1440, 'value' => $cfg['qrcode_validity_period_biophoto']]) ?>
            </div>
            <p class="form-hint mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Saved but not enforced yet — QR-based access/enrollment isn't built in this app.</p>
        </div>
    </div></div>
</div>
<div class="mt-3"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save configuration</button></div>
<?= form_close() ?>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-cf').forEach(function (b) {
    b.addEventListener('click', function () {
        var m = document.getElementById('cfForm'), form = m.querySelector('form');
        form.reset();
        var d = JSON.parse(b.dataset.json || '{}');
        ['id', 'attr_name', 'attr_type'].forEach(function (k) {
            var f = form.querySelector('[name="' + k + '"]'); if (f) f.value = d[k] == null ? '' : d[k];
        });
        // Combobox attr_value is stored as JSON [{"option":"..."}, ...] — show
        // it back as the comma-separated text the input actually edits.
        var av = d.attr_value;
        if (String(d.attr_type) === '2' && av) {
            try {
                var parsed = JSON.parse(av);
                av = Array.isArray(parsed) ? parsed.map(function (o) { return o.option; }).join(', ') : av;
            } catch (e) { /* not JSON — leave as-is */ }
        }
        form.querySelector('[name=attr_value]').value = av == null ? '' : av;
        form.querySelector('[name=is_unique]').checked = d.is_unique === true || d.is_unique === 't';
        form.querySelector('[name=enable]').checked = d.enable === true || d.enable === 't';
        new bootstrap.Modal(m).show();
    });
});
</script>
<?= $this->endSection() ?>
