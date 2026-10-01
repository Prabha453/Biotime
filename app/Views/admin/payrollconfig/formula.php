<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('fx-' . $kind, 'New', 'plus-lg')) ?>
<?= view('admin/payrollconfig/_formula_subnav', ['kinds' => $kinds, 'tab' => $tab]) ?>

<div class="card mb-3"><div class="card-body d-flex justify-content-between align-items-center">
    <div>
        <div class="fw-semibold">Formula-driven payroll</div>
        <div class="text-muted small">When ON, approved OT / leave / exception lines are priced by the formula below. When OFF, the flat multipliers in Payslip Settings apply.</div>
    </div>
    <span><?= $useFormulas ? label('Enabled', 'success') : label('Disabled', 'secondary') ?></span>
</div></div>

<div class="card">
    <div class="card-header"><?= esc($label) ?> formulas</div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Name</th><?php if ($needsPc): ?><th>Pay code</th><?php endif; ?><th>Formula</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($formulas as $f): ?>
            <tr>
                <td class="fw-semibold"><?= esc($f['name']) ?></td>
                <?php if ($needsPc): ?><td class="mono"><?= esc($f['pay_code'] ?? '—') ?></td><?php endif; ?>
                <td class="mono small"><?= esc($f['formula']) ?></td>
                <td class="text-end" style="white-space:nowrap">
                    <button class="btn btn-sm btn-outline-secondary js-fx" data-target="fx-<?= $kind ?>" data-json='<?= esc(json_encode($f), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('payrollconfig.formula.delete', $kind, $f['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this formula?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $formulas): ?><tr><td colspan="<?= $needsPc ? 4 : 3 ?>" class="text-muted small py-3">No <?= strtolower($label) ?> formulas.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="modal fade" id="fx-<?= $kind ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('payrollconfig.formula.save', $kind)) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title"><?= esc($label) ?> formula</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Name *</label><input name="name" class="form-control" required maxlength="30"></div>
            <?php if ($needsPc): ?>
            <div class="col"><label class="form-label tiny">Pay code</label>
                <select name="pay_code_id" class="form-select"><option value="">—</option>
                    <?php foreach ($paycodes as $pc): ?><option value="<?= $pc['id'] ?>"><?= esc($pc['code'] . ' · ' . $pc['name']) ?></option><?php endforeach; ?>
                </select></div>
            <?php endif; ?>
            <?php if ($kind === 'increase'): ?>
            <div class="col-3"><label class="form-label tiny">Apply</label>
                <select name="apply_mode" class="form-select"><option value="0">Add</option><option value="1">Replace</option></select></div>
            <?php endif; ?>
        </div>
        <?php if ($kind === 'increase'): ?>
        <div class="mb-2"><label class="form-label tiny">Special payment type</label>
            <select name="special_type_id" class="form-select"><option value="">—</option>
                <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="mb-2"><label class="form-label tiny">Formula *</label>
            <input name="formula" class="form-control mono" required maxlength="100" placeholder="((({Basic Salary}/30)/8)*1.5)*{NOT}"></div>
        <div class="mb-2"><label class="form-label tiny">Remark</label><input name="remark" class="form-control"></div>
        <div class="ext-note"><i class="bi bi-braces"></i> Tokens:
            <?php foreach ($tokens as $t => $desc): ?><code title="<?= esc($desc, 'attr') ?>">{<?= esc($t) ?>}</code> <?php endforeach; ?>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-fx').forEach(function(b){
  b.addEventListener('click', function(){
    var m = document.getElementById(b.dataset.target), form = m.querySelector('form'); form.reset();
    var d = JSON.parse(b.dataset.json || '{}');
    Object.keys(d).forEach(function(k){ var f = form.querySelector('[name="'+k+'"]'); if (f) f.value = d[k] == null ? '' : d[k]; });
    new bootstrap.Modal(m).show();
  });
});
</script>
<?= $this->endSection() ?>
