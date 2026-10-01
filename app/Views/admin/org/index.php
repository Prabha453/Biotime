<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$modalId = ['departments' => 'deForm', 'positions' => 'poForm', 'areas' => 'arForm', 'companies' => 'coForm'][$tab];
$newLbl  = ['departments' => 'Add department', 'positions' => 'Add position', 'areas' => 'Add area', 'companies' => 'Add company'][$tab];

$companyOpts = opts_from($companies, 'id', 'company_name');
$nameKey     = ['departments' => 'dept_name', 'positions' => 'position_name', 'areas' => 'area_name', 'companies' => 'company_name'][$tab];
$parentOpts  = opts_from($rows, 'id', $nameKey);
$managerOpts = [];
foreach (($managers ?? []) as $m) {
    $managerOpts[$m['id']] = $m['emp_code'] . ' · ' . ($m['name'] ?? '');
}
$posOpts = \App\Controllers\Org::POS;   // logo/name position on payslips & reports
?>
<?php
$subById = ['departments' => 'The reporting structure attendance, schedules and payroll all reference',
    'positions' => 'Job titles / designations', 'areas' => 'Physical locations that group devices and employees',
    'companies' => 'Legal entities — used on payslips and reports'][$tab];
$impType   = ['departments' => 'departments', 'positions' => 'positions', 'areas' => 'areas'][$tab] ?? null;
$exportKey = ['departments' => 'departments', 'positions' => 'positions', 'areas' => 'areas', 'companies' => 'companies'][$tab];
$histPfx   = ['departments' => 'org.department', 'positions' => 'org.position', 'areas' => 'org.area', 'companies' => 'org.company'][$tab];
$delRoute  = ['departments' => 'org.department.bulk_delete', 'positions' => 'org.position.bulk_delete', 'areas' => 'org.area.bulk_delete', 'companies' => 'org.company.bulk_delete'][$tab];
$fbFields  = [
    'departments' => ['dept_code' => ['label' => 'Department code', 'type' => 'text'], 'dept_name' => ['label' => 'Department name', 'type' => 'text'], 'company_name' => ['label' => 'Company', 'type' => 'text'], 'manager_name' => ['label' => 'Manager', 'type' => 'text']],
    'positions'   => ['position_code' => ['label' => 'Position code', 'type' => 'text'], 'position_name' => ['label' => 'Position name', 'type' => 'text'], 'company_name' => ['label' => 'Company', 'type' => 'text']],
    'areas'       => ['area_code' => ['label' => 'Area code', 'type' => 'text'], 'area_name' => ['label' => 'Area name', 'type' => 'text'], 'company_name' => ['label' => 'Company', 'type' => 'text']],
    'companies'   => ['company_code' => ['label' => 'Code', 'type' => 'text'], 'company_name' => ['label' => 'Name', 'type' => 'text'], 'is_default' => ['label' => 'Default', 'type' => 'bool']],
][$tab];
?>
<?= page_head($title, $subById) ?>
<?= grid_toolbar([
    'add'          => ['label' => $newLbl, 'modal' => $modalId],
    'delete'       => route_to($delRoute),
    'import'       => $impType,
    'export'       => $exportKey,
    'columns'      => true,
    'filter'       => '#orgFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => $histPfx, 'label' => $tabs[$tab]],
]) ?>
<?= filter_builder('orgFilter', $fbFields, ['button' => false]) ?>

<div data-crud="<?= route_to('org.tab', $tab) ?>">
    <div data-crud-results class="ajax-region">
        <?= view('admin/org/_list', ['rows' => $rows, 'tab' => $tab, 'tabs' => $tabs, 'pager' => $pager ?? null]) ?>
    </div>

    <?php
    // one modal shell for whichever tab is active — fields via field_helper
    $modalTitle = ['departments' => 'Department', 'positions' => 'Position', 'areas' => 'Area', 'companies' => 'Company'][$tab];
    $saveRoute  = ['departments' => 'org.department.save', 'positions' => 'org.position.save', 'areas' => 'org.area.save', 'companies' => 'org.company.save'][$tab];
    ?>
    <div class="modal fade" id="<?= $modalId ?>" tabindex="-1"><div class="modal-dialog<?= $tab === 'companies' ? ' modal-lg' : '' ?>"><div class="modal-content">
        <?= $tab === 'companies'
            ? form_open_multipart(route_to($saveRoute), ['data-crud-save' => 'POST'])
            : form_open(route_to($saveRoute), ['data-crud-save' => 'POST']) ?>
        <?= field_hidden('id', '') ?>
        <div class="modal-header"><h5 class="modal-title"><?= $modalTitle ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">

            <?php if ($tab === 'departments'): ?>
                <?= field_text('dept_code', 'Code', ['required' => true, 'maxlength' => 50, 'col' => 'col-5', 'class' => 'mono']) ?>
                <?= field_text('dept_name', 'Name', ['required' => true, 'maxlength' => 200, 'col' => 'col-7']) ?>
                <?= field_select('company_id', 'Company', $companyOpts, ['required' => true, 'col' => 'col-12', 'placeholder' => '— select —']) ?>
                <?= field_select('parent_dept_id', 'Parent department', $parentOpts, ['col' => 'col-6', 'placeholder' => '— none —']) ?>
                <?= field_select('dept_manager_id', 'Manager', $managerOpts, ['col' => 'col-6', 'placeholder' => '— none —']) ?>
                <?= field_switch('is_default', 'Default department for new employees', ['col' => 'col-12']) ?>

            <?php elseif ($tab === 'positions'): ?>
                <?= field_text('position_code', 'Code', ['required' => true, 'maxlength' => 50, 'col' => 'col-5', 'class' => 'mono']) ?>
                <?= field_text('position_name', 'Name', ['required' => true, 'maxlength' => 100, 'col' => 'col-7']) ?>
                <?= field_select('company_id', 'Company', $companyOpts, ['required' => true, 'col' => 'col-12', 'placeholder' => '— select —']) ?>
                <?= field_select('parent_position_id', 'Reports to', $parentOpts, ['col' => 'col-12', 'placeholder' => '— none —']) ?>
                <?= field_switch('is_default', 'Default position for new employees', ['col' => 'col-12']) ?>

            <?php elseif ($tab === 'areas'): ?>
                <?= field_text('area_code', 'Code', ['required' => true, 'maxlength' => 30, 'col' => 'col-5', 'class' => 'mono']) ?>
                <?= field_text('area_name', 'Name', ['required' => true, 'maxlength' => 100, 'col' => 'col-7']) ?>
                <?= field_select('company_id', 'Company', $companyOpts, ['required' => true, 'col' => 'col-12', 'placeholder' => '— select —']) ?>
                <?= field_select('parent_area_id', 'Parent area', $parentOpts, ['col' => 'col-12', 'placeholder' => '— none —']) ?>
                <?= field_switch('is_default', 'Default area', ['col' => 'col-12']) ?>

            <?php else: /* companies */ ?>
                <div class="col-md-12"><div class="row g-3">
                    <?= field_text('company_code', 'Code', ['required' => true, 'maxlength' => 50, 'col' => 'col-4', 'class' => 'mono']) ?>
                    <?= field_text('company_name', 'Name', ['required' => true, 'maxlength' => 100, 'col' => 'col-8']) ?>
                    <?= field_text('address', 'Address line 1', ['maxlength' => 200, 'col' => 'col-6']) ?>
                    <?= field_text('address2', 'Address line 2', ['maxlength' => 200, 'col' => 'col-6']) ?>
                    <?= field_text('city', 'City', ['maxlength' => 10, 'col' => 'col-4']) ?>
                    <?= field_text('postal_code', 'Postal code', ['maxlength' => 20, 'col' => 'col-4']) ?>
                    <?= field_text('state', 'State / province', ['maxlength' => 20, 'col' => 'col-4']) ?>
                    <?= field_text('country', 'Country', ['maxlength' => 10, 'col' => 'col-4']) ?>
                    <?= field_text('phone', 'Phone', ['maxlength' => 20, 'col' => 'col-4']) ?>
                    <?= field_text('fax', 'Fax', ['maxlength' => 20, 'col' => 'col-4']) ?>
                    </div>
                    </div>
                    <div class="col-md-8">
                    <div class="row g-3">
                    <?= field_email('email', 'Email', ['maxlength' => 50, 'col' => 'col-6']) ?>
                    <?= field_text('website', 'Website', ['maxlength' => 50, 'col' => 'col-6']) ?>
                    <?= field_select('logo_pos', 'Logo on payslip', $posOpts, ['col' => 'col-6', 'value' => 0]) ?>
                    <?= field_select('name_pos', 'Name on payslip', $posOpts, ['col' => 'col-6', 'value' => 1]) ?>
                    <?= field_switch('is_default', 'Default company for new employees', ['col' => 'col-12']) ?>
                    </div>
                    </div>
                    <div class="col-md-4">
                    <div class="row g-3">
                    <?= field_image('logo', 'Logo', ['col' => 'col-md-6', 'accept' => 'image/jpeg,image/png,image/gif', 'removable' => true, 'hint' => 'PNG / JPG / GIF, up to 4 MB.']) ?>
                    </div>
                    </div>
            <?php endif; ?>

        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
        <?= form_close() ?>
    </div></div></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Shared modal populate: a real "edit" button carries data-*; "new" carries none;
// a programmatic reopen (server validation error) has no relatedTarget.
document.querySelectorAll('#deForm, #poForm, #arForm, #coForm').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (ev) {
        var t = ev.relatedTarget, form = modal.querySelector('form');
        if (!t) return;
        form.classList.remove('was-validated');
        TT.clearErrors(form);
        form.reset();
        var edit = t.classList.contains('js-edit');
        form.querySelector('[name=id]').value = edit ? (t.dataset.id || '') : '';
        modal.querySelector('.modal-title').textContent =
            (edit ? 'Edit ' : 'Add ') + modal.querySelector('.modal-title').textContent.replace(/^(Edit|Add|New) /, '');
        if (!edit) return;
        Object.keys(t.dataset).forEach(function (k) {
            if (k === 'id' || k === 'bsToggle' || k === 'bsTarget' || k === 'logo') return;
            var f = form.querySelector('[name=' + k + ']');
            if (!f || f.type === 'file') return;
            if (f.type === 'checkbox') f.checked = t.dataset[k] === '1';
            else f.value = t.dataset[k] === 'null' ? '' : (t.dataset[k] || '');
        });
        // logo preview in the .tt-upload widget
        var up = form.querySelector('.tt-upload');
        if (up) {
            var url = t.dataset.logo || '', img = up.querySelector('.tt-upload-img'),
                empty = up.querySelector('.tt-upload-empty'),
                rmRow = form.querySelector('.js-rm-row'),
                rm = form.querySelector('[name=logo_remove]');
            if (rm) rm.checked = false;
            if (img) {
                if (url) { img.src = url; img.hidden = false; }
                else { img.removeAttribute('src'); img.hidden = true; }
            }
            if (empty) empty.hidden = !!url;
            if (rmRow) rmRow.hidden = !url;
        }
        if (window.TT) TT.syncForm(form);
    });
    modal.addEventListener('shown.bs.modal', function () { if (window.TT) TT.syncForm(modal.querySelector('form')); });
});

// client-side "search this list" — (re)bound after every AJAX swap
function bindOrgSearch() {
    var box = document.getElementById('orgSearch'),
        tbl = document.getElementById('orgTable'),
        none = document.getElementById('orgNoMatch');
    if (!box || box.dataset.bound) return;
    box.dataset.bound = '1';
    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase(), shown = 0,
            rows = tbl.tBodies[0] ? tbl.tBodies[0].rows : [];
        Array.prototype.forEach.call(rows, function (tr) {
            if (tr.querySelector('.empty-state')) return;
            var hit = tr.textContent.toLowerCase().indexOf(q) !== -1;
            tr.hidden = !hit; if (hit) shown++;
        });
        none.hidden = shown !== 0 || q === '';
    });
}
bindOrgSearch();
if (!window.__orgSearchBound) { window.__orgSearchBound = true; document.addEventListener('tt:swapped', bindOrgSearch); }
</script>
<?= $this->endSection() ?>
