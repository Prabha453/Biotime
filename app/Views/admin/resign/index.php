<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add resignation', 'modal' => 'resignForm'],
    'import'       => 'resign',
    'export'       => ['key' => 'resign'],
    'columns'      => true,
    'filter'       => '#resignFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'resign', 'label' => 'Resign'],
]) ?>
<?= filter_builder('resignFilter', [
    'emp_code'    => ['label' => 'Employee ID', 'type' => 'text'],
    'name'        => ['label' => 'Employee name', 'type' => 'text'],
    'dept_name'   => ['label' => 'Department', 'type' => 'text'],
    'resign_type' => ['label' => 'Resign type', 'type' => 'select', 'options' => $types],
    'resign_date' => ['label' => 'Resign date', 'type' => 'date'],
    'disableatt'  => ['label' => 'Attendance disabled', 'type' => 'bool'],
], ['button' => false]) ?>

<div data-crud="<?= route_to('resign') ?>">
    <div data-crud-results class="ajax-region">
        <?= view('admin/resign/_list', ['rows' => $rows, 'types' => $types, 'pager' => $pager ?? null]) ?>
    </div>

    <div class="modal fade" id="resignForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <?= form_open(route_to('resign.save'), ['data-crud-save' => 'POST']) ?>
        <input type="hidden" name="id" value="">
        <div class="modal-header"><h5 class="modal-title">Resignation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3 js-emp-pick">
                <label class="form-label">Employee<?= req() ?></label>
                <select name="employee_id" class="form-select<?= err_class('employee_id') ?>">
                    <option value="">— select —</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= (int) old('employee_id') === (int) $emp['id'] ? 'selected' : '' ?>>
                            <?= esc($emp['emp_code'] . ' · ' . ($emp['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= err_text('employee_id') ?>
            </div>
            <div class="mb-3 js-emp-name d-none">
                <label class="form-label">Employee</label>
                <input class="form-control" disabled value="">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label">Resign date<?= req() ?></label>
                    <input type="date" name="resign_date" class="form-control<?= err_class('resign_date') ?>" required value="<?= esc(old('resign_date', date('Y-m-d'))) ?>">
                    <?= err_text('resign_date') ?>
                </div>
                <div class="col-6">
                    <label class="form-label">Type<?= req() ?></label>
                    <select name="resign_type" class="form-select<?= err_class('resign_type') ?>" required>
                        <?php foreach ($types as $k => $v): ?>
                            <option value="<?= $k ?>" <?= (int) old('resign_type', 3) === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= err_text('resign_type') ?>
                </div>
            </div>
            <div class="switch-row mb-2"><div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="disableatt" value="1" id="resignDisable" checked>
            </div><label for="resignDisable">Disable attendance &amp; deactivate the employee</label></div>
            <div class="mb-1">
                <label class="form-label">Reason</label>
                <textarea name="reason" rows="2" class="form-control" maxlength="200"><?= esc(old('reason')) ?></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary">Save</button>
        </div>
        <?= form_close() ?>
    </div></div></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('resignForm')?.addEventListener('show.bs.modal', function (ev) {
    const modal = this, form = modal.querySelector('form');
    const t = ev.relatedTarget;
    if (!t) return;                              // programmatic reopen after a server error
    form.classList.remove('was-validated');
    TT.clearErrors(form);
    form.reset();
    const edit = t.classList.contains('js-edit');
    form.querySelector('[name=id]').value = edit ? (t.dataset.id || '') : '';
    modal.querySelector('.modal-title').textContent = edit ? 'Edit resignation' : 'Add resignation';
    // on edit the employee is fixed — show a read-only field, drop the picker's "required"
    const pick = modal.querySelector('.js-emp-pick'), nameBox = modal.querySelector('.js-emp-name');
    pick.classList.toggle('d-none', edit);
    nameBox.classList.toggle('d-none', !edit);
    pick.querySelector('select').required = !edit;
    if (edit) {
        nameBox.querySelector('input').value = t.dataset.name || '';
        form.querySelector('[name=resign_date]').value = t.dataset.resign_date || '';
        form.querySelector('[name=resign_type]').value = t.dataset.resign_type || '3';
        form.querySelector('[name=disableatt]').checked = t.dataset.disableatt === '1';
        form.querySelector('[name=reason]').value = t.dataset.reason && t.dataset.reason !== 'null' ? t.dataset.reason : '';
    }
    if (window.TT) TT.syncForm(form);
});
document.getElementById('resignForm')?.addEventListener('shown.bs.modal', function () {
    if (window.TT) TT.syncForm(this.querySelector('form'));
});

function bindResignSearch() {
    const box = document.getElementById('resignSearch'),
          tbl = document.getElementById('resignTable'),
          none = document.getElementById('resignNoMatch');
    if (!box || box.dataset.bound) return;
    box.dataset.bound = '1';
    box.addEventListener('input', function () {
        const q = box.value.trim().toLowerCase();
        let shown = 0;
        Array.prototype.forEach.call(tbl.tBodies[0] ? tbl.tBodies[0].rows : [], function (tr) {
            if (tr.querySelector('.empty-state')) return;
            const hit = tr.textContent.toLowerCase().indexOf(q) !== -1;
            tr.hidden = !hit; if (hit) shown++;
        });
        none.hidden = shown !== 0 || q === '';
    });
}
bindResignSearch();
if (!window.__resignSearchBound) { window.__resignSearchBound = true; document.addEventListener('tt:swapped', bindResignSearch); }
</script>
<?= $this->endSection() ?>
