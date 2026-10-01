<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('payrollconfig.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'tax' || $tab === 'social'): ?>
<div class="card" style="max-width:760px"><div class="card-body">
    <?= form_open(route_to('payrollconfig.slabs.save', $tab)) ?>
    <div class="row g-2 mb-3">
        <div class="col-4"><label class="form-label tiny">Country / scheme</label><input name="country_code" class="form-control" value="<?= esc($row['country_code'] ?? 'default') ?>"></div>
        <div class="col-4 d-flex align-items-end"><label class="form-check"><input type="checkbox" class="form-check-input" name="used" value="1" <?= ! empty($row['used']) ? 'checked' : '' ?>> <span class="form-check-label">Apply to payroll</span></label></div>
    </div>
    <?php if ($tab === 'social'): ?>
        <p class="form-hint">Below Minimum Salary, the flat Minimum Deduction Amount applies; above Maximum Salary, the flat Maximum Deduction Amount applies. The bands below cover everything in between.</p>
        <div class="row g-2 mb-3">
            <div class="col"><label class="form-label tiny">Minimum Salary</label><input name="minimum" type="number" step="0.01" class="form-control" value="<?= esc($parsed['minimum'] ?? '') ?>"></div>
            <div class="col"><label class="form-label tiny">Minimum Deduction Amount</label><input name="minimum_social_security" type="number" step="0.01" class="form-control" value="<?= esc($parsed['minimum_social_security'] ?? '') ?>"></div>
        </div>
    <?php endif; ?>
    <table class="table table-sm align-middle" id="slabTbl">
        <thead><tr><th>From</th><th>To</th><th>%</th><th></th></tr></thead>
        <tbody>
        <?php $slabs = $parsed['min_max_percent'] ?? [['min' => '', 'max' => '', 'percent' => '']]; ?>
        <?php foreach ($slabs as $s): ?>
            <tr>
                <td><input name="min[]" type="number" step="0.01" class="form-control form-control-sm" value="<?= esc($s['min'] ?? '') ?>"></td>
                <td><input name="max[]" type="number" step="0.01" class="form-control form-control-sm" value="<?= esc($s['max'] ?? '') ?>"></td>
                <td><input name="pct[]" type="number" step="0.01" class="form-control form-control-sm" value="<?= esc($s['percent'] ?? '') ?>"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger js-rmrow"><i class="bi bi-x"></i></button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addSlab"><i class="bi bi-plus-lg"></i> Add band</button>
    <?php if ($tab === 'tax'): ?>
        <div class="row g-2 mb-3">
            <div class="col"><label class="form-label tiny">Assessable Income Over</label><input name="maximum" type="number" step="0.01" class="form-control" value="<?= esc($parsed['maximum'] ?? '') ?>"></div>
            <div class="col"><label class="form-label tiny">Tax Rate %</label><input name="maximum_percent" type="number" step="0.01" class="form-control" value="<?= esc($parsed['maximum_percent'] ?? '') ?>"></div>
        </div>
    <?php else: ?>
        <div class="row g-2 mb-3">
            <div class="col"><label class="form-label tiny">Maximum Salary</label><input name="maximum" type="number" step="0.01" class="form-control" value="<?= esc($parsed['maximum'] ?? '') ?>"></div>
            <div class="col"><label class="form-label tiny">Maximum Deduction Amount</label><input name="maximum_social_security" type="number" step="0.01" class="form-control" value="<?= esc($parsed['maximum_social_security'] ?? '') ?>"></div>
        </div>
    <?php endif; ?>
    <button class="btn btn-primary">Save <?= $tab === 'tax' ? 'tax' : 'social security' ?> slabs</button>
    <?= form_close() ?>
</div></div>

<?php elseif ($tab === 'exemptions'): ?>
<?= page_head('', '', '') ?>
<div class="d-flex justify-content-end mb-2"><?= modal_btn('exForm', 'New exemption') ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Name</th><th>Employee</th><th>Year</th><th class="text-end">Amount</th><th>Remark</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="fw-semibold"><?= esc($r['exemption_name']) ?></td>
            <td><?= $r['emp_name'] ? esc($r['emp_code'] . ' · ' . $r['emp_name']) : '<span class="text-muted">All</span>' ?></td>
            <td class="mono"><?= (int) $r['year'] ?></td>
            <td class="text-end mono"><?= number_format((float) $r['amount'], 2) ?></td>
            <td class="text-muted small"><?= esc($r['remark'] ?? '') ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="btn btn-sm btn-outline-secondary js-ex" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('payrollconfig.exemption.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete?', 'data-confirm-danger' => '']) ?>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-receipt"></i>No expense exemptions recorded.</div></td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="exForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('payrollconfig.exemption.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Expense exemption</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label><input name="exemption_name" class="form-control" required></div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Amount *</label><input name="amount" type="number" step="0.01" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">Issued date *</label><input name="issued_time" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Employee (blank = all)</label>
            <select name="employee_id" class="form-select"><option value="">All employees</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . $e['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label tiny">Remark</label><input name="remark" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<?php else: /* settings */ ?>
<div class="card" style="max-width:640px"><div class="card-body">
    <?= form_open(route_to('payrollconfig.settings.save')) ?>
    <div class="mb-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="use_formulas" value="1" <?= (int) $kv['use_formulas'] === 1 ? 'checked' : '' ?>>
        <span class="form-check-label">Price OT / leave / exception lines with salary-structure <strong>formulas</strong></span></label>
        <div class="text-muted small ms-4">Off = use the flat multipliers below.</div></div>
    <div class="row g-2 mb-3">
        <div class="col"><label class="form-label tiny">Weekday OT ×</label><input name="ot_normal_x" type="number" step="0.05" class="form-control" value="<?= esc($kv['ot_normal_x']) ?>"></div>
        <div class="col"><label class="form-label tiny">Weekend OT ×</label><input name="ot_weekend_x" type="number" step="0.05" class="form-control" value="<?= esc($kv['ot_weekend_x']) ?>"></div>
        <div class="col"><label class="form-label tiny">Holiday OT ×</label><input name="ot_holiday_x" type="number" step="0.05" class="form-control" value="<?= esc($kv['ot_holiday_x']) ?>"></div>
    </div>
    <div class="mb-3"><label class="form-label tiny">Payslip run day of month</label>
        <input name="slip_day" type="number" min="1" max="28" class="form-control" style="max-width:120px" value="<?= esc($kv['slip_day']) ?>"></div>
    <div class="mb-3"><label class="form-label tiny">Company stamp / signatory line</label><input name="company_stamp" class="form-control" value="<?= esc($kv['company_stamp']) ?>" placeholder="For Sun Emirates HR Dept."></div>
    <div class="mb-3"><label class="form-label tiny">Payslip footer note</label><textarea name="payslip_note" class="form-control" rows="2"><?= esc($kv['payslip_note']) ?></textarea></div>
    <button class="btn btn-primary">Save settings</button>
    <?= form_close() ?>
</div></div>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.getElementById('exForm')?.addEventListener('show.bs.modal', function(ev){
  var t = ev.relatedTarget, form = this.querySelector('form'); form.reset(); form.querySelector('[name=id]').value = '';
  if (t && t.classList.contains('js-ex')) {
    var d = JSON.parse(t.dataset.json || '{}');
    ['id','exemption_name','amount','employee_id','remark'].forEach(function(k){
      var f = form.querySelector('[name="'+k+'"]'); if (f) f.value = d[k] == null ? '' : d[k];
    });
    form.querySelector('[name=issued_time]').value = (d.issued_time || '').slice(0, 10);
  }
});
var st = document.getElementById('slabTbl');
document.getElementById('addSlab')?.addEventListener('click', function(){
  var tr = st.tBodies[0].rows[0].cloneNode(true);
  tr.querySelectorAll('input').forEach(function(i){ i.value = ''; });
  st.tBodies[0].appendChild(tr);
});
st?.addEventListener('click', function(e){
  var b = e.target.closest('.js-rmrow'); if (b && st.tBodies[0].rows.length > 1) b.closest('tr').remove();
});
</script>
<?= $this->endSection() ?>
