<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $roleOpts = opts_from($roles, 'id', 'role_name'); ?>
<?= page_head($flow['workflow_name'], '', modal_btn('nodeForm', 'Add step', 'plus-lg'), [
    ['Home', route_to('dashboard')], ['Workflow', route_to('workflow')], ['Steps'],
]) ?>

<div class="card mb-3"><div class="card-body">
    <div class="row g-3 small">
        <div class="col-md-3"><div class="text-muted">Code</div><div class="mono"><?= esc($flow['workflow_code']) ?></div></div>
        <div class="col-md-3"><div class="text-muted">Approves</div><div><?= esc($targetLabel) ?></div></div>
        <div class="col-md-3"><div class="text-muted">Applies to</div><div><?= esc($scopeLabel) ?></div></div>
        <div class="col-md-3"><div class="text-muted">Effective</div><div class="mono"><?= esc($flow['start_date']) ?> → <?= esc($flow['end_date']) ?></div></div>
    </div>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th style="width:3rem" class="text-end">#</th><th>Step</th><th>Approver roles</th><th>Notify roles</th><th>Day window</th><th class="text-end">Action</th></tr></thead>
    <tbody>
    <?php foreach ($nodes as $n): ?>
        <tr>
            <td class="mono text-end"><?= (int) $n['order_id'] ?></td>
            <td class="fw-semibold"><?= esc($n['node_name']) ?></td>
            <td><?= $n['approver_names'] ? esc($n['approver_names']) : '<span class="text-danger small">none — step will block</span>' ?>
                <?= ($n['approver_by_overall'] === true || $n['approver_by_overall'] === 't') ? ' <span class="badge bg-label-secondary">all depts</span>' : '' ?></td>
            <td class="text-muted"><?= esc($n['notifier_names'] ?? '—') ?></td>
            <td class="mono text-muted small"><?= (int) $n['from_day'] === 0 && (int) $n['to_day'] === 0 ? 'any' : ((int) $n['from_day'] . '–' . (int) $n['to_day'] . ' d') ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-node" title="Edit"
                    data-id="<?= $n['id'] ?>" data-node_name="<?= esc($n['node_name'], 'attr') ?>" data-order_id="<?= (int) $n['order_id'] ?>"
                    data-from_day="<?= (int) $n['from_day'] ?>" data-to_day="<?= (int) $n['to_day'] ?>"
                    data-approver_by_overall="<?= ($n['approver_by_overall'] === true || $n['approver_by_overall'] === 't') ? 1 : 0 ?>"
                    data-notify_by_overall="<?= ($n['notify_by_overall'] === true || $n['notify_by_overall'] === 't') ? 1 : 0 ?>"
                    data-approver_ids="<?= esc(trim($n['approver_ids'] ?? '{}', '{}'), 'attr') ?>"
                    data-notifier_ids="<?= esc(trim($n['notifier_ids'] ?? '{}', '{}'), 'attr') ?>"
                    data-bs-toggle="modal" data-bs-target="#nodeForm"><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('workflow.node.delete', $flow['id'], $n['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this step?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $nodes): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-list-ol"></i>No approval steps.</div></td></tr><?php endif; ?>
    </tbody>
</table></div>
<div class="card-footer"><a href="<?= route_to('workflow') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to workflows</a></div>
</div>

<div class="modal fade" id="nodeForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('workflow.node.save', $flow['id'])) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Approval step</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
        <?= field_text('node_name', 'Step name', ['required' => true, 'maxlength' => 30, 'col' => 'col-8', 'value' => 'Approval']) ?>
        <?= field_number('order_id', 'Order', ['required' => true, 'min' => 1, 'col' => 'col-4', 'value' => count($nodes) + 1]) ?>
        <?= field_select('approver_ids', 'Approver roles', $roleOpts, ['multiple' => true, 'required' => true, 'col' => 'col-12', 'hint' => 'All listed roles must approve this step.']) ?>
        <?= field_select('notifier_ids', 'Notify roles', $roleOpts, ['multiple' => true, 'col' => 'col-12']) ?>
        <?= field_number('from_day', 'Applies from (days)', ['min' => 0, 'col' => 'col-6', 'value' => 0, 'hint' => '0 / 0 = any duration']) ?>
        <?= field_number('to_day', 'Applies to (days)', ['min' => 0, 'col' => 'col-6', 'value' => 0]) ?>
        <?= field_switch('approver_by_overall', 'Approvers may be from any department', ['col' => 'col-12']) ?>
        <?= field_switch('notify_by_overall', 'Notifiers may be from any department', ['col' => 'col-12']) ?>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save step</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var modal = document.getElementById('nodeForm');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (ev) {
        var t = ev.relatedTarget, form = modal.querySelector('form');
        form.classList.remove('was-validated');
        if (window.TT) TT.clearErrors(form);
        form.reset();
        var edit = t && t.classList.contains('js-node');
        form.querySelector('[name=id]').value = edit ? (t.dataset.id || '') : '';
        modal.querySelector('.modal-title').textContent = (edit ? 'Edit ' : 'Add ') + 'approval step';
        if (edit) {
            ['node_name', 'order_id', 'from_day', 'to_day'].forEach(function (k) {
                var f = form.querySelector('[name=' + k + ']'); if (f) f.value = t.dataset[k] || '';
            });
            form.querySelector('[name=approver_by_overall]').checked = t.dataset.approverByOverall === '1';
            form.querySelector('[name=notify_by_overall]').checked = t.dataset.notifyByOverall === '1';
            var setMulti = function (name, csv) {
                var ids = (csv || '').split(',').filter(Boolean);
                Array.prototype.forEach.call(form.querySelectorAll('[name="' + name + '[]"] option'), function (o) {
                    o.selected = ids.indexOf(o.value) > -1;
                });
            };
            setMulti('approver_ids', t.dataset.approverIds);
            setMulti('notifier_ids', t.dataset.notifierIds);
        }
        if (window.TT) TT.syncForm(form);
    });
})();
</script>
<?= $this->endSection() ?>
