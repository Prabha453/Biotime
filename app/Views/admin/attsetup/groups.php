<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add', 'modal' => 'agForm'],
    'columns'      => true,
    'filter'       => '#agFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'att.attgroup', 'label' => 'Attendance Group'],
]) ?>
<?= filter_builder('agFilter', [
    'code' => ['label' => 'Code', 'type' => 'text'],
    'name' => ['label' => 'Name', 'type' => 'text'],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th data-col="code">Code</th><th data-col="name">Name</th><th data-col="company">Company</th>
        <th data-col="emp" class="text-end">Members</th><th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="mono fw-semibold"><?= esc($r['code']) ?></td>
            <td><?= esc($r['name']) ?></td>
            <td class="text-muted"><?= esc($r['company_name'] ?? '—') ?></td>
            <td class="text-end mono"><?= (int) $r['emp_count'] ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-ag-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('attsetup.groups.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this group?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-people"></i>No attendance groups.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="agForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('attsetup.groups.save'), ['id' => 'agFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Attendance Group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <?= field_text('code', 'Code', ['col' => 'col-md-5', 'required' => true, 'maxlength' => 50, 'class' => 'mono']) ?>
        <?= field_text('name', 'Name', ['col' => 'col-md-7', 'required' => true, 'maxlength' => 100]) ?>
        <div class="col-12"><p class="form-hint mb-0">Assign employees to the group from the employee form's Attendance tab, or with a bulk action.</p></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-ag-edit').forEach(function (b) {
    b.addEventListener('click', function () {
        var f = document.getElementById('agFormEl'), d = JSON.parse(b.dataset.json || '{}');
        f.reset(); f.querySelector('[name=id]').value = d.id || '';
        f.querySelector('[name=code]').value = d.code || '';
        f.querySelector('[name=name]').value = d.name || '';
        document.querySelector('#agForm .modal-title').textContent = 'Edit Attendance Group';
        new bootstrap.Modal(document.getElementById('agForm')).show();
    });
});
document.querySelector('[data-bs-target="#agForm"]')?.addEventListener('click', function () {
    var f = document.getElementById('agFormEl'); f.reset(); f.querySelector('[name=id]').value = '';
    document.querySelector('#agForm .modal-title').textContent = 'Add Attendance Group';
});
</script>
<?= $this->endSection() ?>
