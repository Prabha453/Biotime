<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$modalId = $tab === 'roles' ? 'roleForm' : 'flowForm';
$newLbl  = $tab === 'roles' ? 'Add role' : 'Add workflow';
$saveRt  = $tab === 'roles' ? 'workflow.role.save' : 'workflow.flow.save';

$companyOpts = opts_from($companies, 'id', 'company_name');
$roleOpts    = ['' => '— none —'] + opts_from($roles, 'id', 'role_name');
$ctypeOpts   = $ctypes;                       // [id => label]
$scopeOpts   = \App\Controllers\Workflow::SCOPE;
?>
<?= page_head($title, 'Approval roles and the multi-step workflows the Requests screen routes through') ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('workflow.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?= grid_toolbar([
    'add'     => ['label' => $newLbl, 'modal' => $modalId],
    'columns' => true,
]) ?>

<div data-crud="<?= route_to('workflow.tab', $tab) ?>">
    <div data-crud-results class="ajax-region">
        <?= view('admin/workflow/_list', ['rows' => $rows, 'tab' => $tab, 'pager' => $pager ?? null]) ?>
    </div>

    <div class="modal fade" id="<?= $modalId ?>" tabindex="-1"><div class="modal-dialog<?= $tab === 'flows' ? ' modal-lg' : '' ?>"><div class="modal-content">
        <?= form_open(route_to($saveRt), ['data-crud-save' => 'POST']) ?>
        <?= field_hidden('id', '') ?>
        <div class="modal-header"><h5 class="modal-title"><?= $tab === 'roles' ? 'Workflow role' : 'Workflow' ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <?php if ($tab === 'roles'): ?>
                <?= field_text('role_code', 'Code', ['required' => true, 'maxlength' => 30, 'col' => 'col-4', 'class' => 'mono']) ?>
                <?= field_text('role_name', 'Name', ['required' => true, 'maxlength' => 50, 'col' => 'col-8']) ?>
                <?= field_select('parent_role_id', 'Reports to', $roleOpts, ['col' => 'col-6']) ?>
                <?= field_select('company_id', 'Company', $companyOpts, ['col' => 'col-6', 'value' => array_key_first($companyOpts)]) ?>
                <?= field_textarea('description', 'Description', ['col' => 'col-12', 'rows' => 2, 'maxlength' => 200]) ?>
            <?php else: ?>
                <?= field_text('workflow_code', 'Code', ['required' => true, 'maxlength' => 50, 'col' => 'col-4', 'class' => 'mono']) ?>
                <?= field_text('workflow_name', 'Name', ['required' => true, 'maxlength' => 50, 'col' => 'col-8']) ?>
                <?= field_select('content_type_id', 'Approves', $ctypeOpts, ['required' => true, 'col' => 'col-6', 'placeholder' => '— select —']) ?>
                <?= field_select('workflow_type', 'Applies to', $scopeOpts, ['required' => true, 'col' => 'col-6', 'value' => 0]) ?>
                <?= field_select('department_id', 'Department', ['' => '— any —'], ['col' => 'col-6', 'hint' => 'Used when "Applies to" is Department.', 'attrs' => ['data-native' => true]]) ?>
                <?= field_select('applicant_position_id', 'Position', ['' => '— any —'], ['col' => 'col-6', 'hint' => 'Used when "Applies to" is Position.', 'attrs' => ['data-native' => true]]) ?>
                <?= field_date('start_date', 'Effective from', ['required' => true, 'col' => 'col-4', 'value' => date('Y-01-01')]) ?>
                <?= field_date('end_date', 'Effective to', ['required' => true, 'col' => 'col-4', 'value' => date('Y-12-31')]) ?>
                <?= field_select('company_id', 'Company', $companyOpts, ['col' => 'col-4', 'value' => array_key_first($companyOpts)]) ?>
                <?= field_textarea('description', 'Description', ['col' => 'col-12', 'rows' => 2, 'maxlength' => 50]) ?>
                <div class="col-12"><p class="form-hint mb-0"><i class="bi bi-info-circle me-1"></i>A first approval step is created automatically — open the workflow to add roles and more steps.</p></div>
            <?php endif; ?>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
        <?= form_close() ?>
    </div></div></div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('#roleForm, #flowForm').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (ev) {
        var t = ev.relatedTarget, form = modal.querySelector('form');
        if (!t) return;
        form.classList.remove('was-validated');
        if (window.TT) TT.clearErrors(form);
        form.reset();
        var edit = t.classList.contains('js-edit');
        form.querySelector('[name=id]').value = edit ? (t.dataset.id || '') : '';
        modal.querySelector('.modal-title').textContent =
            (edit ? 'Edit ' : 'Add ') + modal.querySelector('.modal-title').textContent.replace(/^(Edit|Add|New) /, '');
        if (edit) {
            Object.keys(t.dataset).forEach(function (k) {
                if (['id', 'bsToggle', 'bsTarget'].indexOf(k) > -1) return;
                var f = form.querySelector('[name=' + k + ']');
                if (!f) return;
                f.value = t.dataset[k] === 'null' ? '' : (t.dataset[k] || '');
            });
        }
        if (window.TT) TT.syncForm(form);
    });
});
</script>
<?= $this->endSection() ?>