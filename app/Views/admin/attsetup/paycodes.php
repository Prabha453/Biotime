<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add', 'modal' => 'pcForm'],
    'export'       => ['key' => 'att_paycode'],
    'columns'      => true,
    'filter'       => '#pcFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'att.paycode', 'label' => 'Pay Code'],
]) ?>
<?= filter_builder('pcFilter', [
    'code'      => ['label' => 'Code', 'type' => 'text'],
    'name'      => ['label' => 'Name', 'type' => 'text'],
    'code_type' => ['label' => 'Type', 'type' => 'select', 'options' => $codeTypes],
    'is_paid'   => ['label' => 'Paid', 'type' => 'bool'],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th data-col="code">Code</th><th data-col="name">Name</th><th data-col="type">Type</th>
        <th data-col="tag">Tag</th><th data-col="work">Is Work</th><th data-col="paid">Is Paid</th>
        <th data-col="hours" class="text-end">Fixed Hrs</th><th data-col="fmt">Format</th>
        <th data-col="order" class="text-end">Order</th><th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="mono fw-semibold"><?= esc($r['code']) ?></td>
            <td><?= esc($r['name']) ?></td>
            <td><span class="badge bg-label-<?= [1=>'primary',2=>'warning',3=>'info',4=>'danger',5=>'secondary'][(int) $r['code_type']] ?? 'secondary' ?>"><?= esc($codeTypes[(int) $r['code_type']] ?? '—') ?></span></td>
            <td class="small text-muted"><?= esc($tags[(int) $r['tag']] ?? '—') ?></td>
            <td><?= ($r['is_work'] === true || $r['is_work'] === 't') ? '<i class="bi bi-check-lg text-success"></i>' : '—' ?></td>
            <td><?= ($r['is_paid'] === true || $r['is_paid'] === 't') ? '<i class="bi bi-check-lg text-success"></i>' : '<span class="text-danger small">unpaid</span>' ?></td>
            <td class="text-end mono"><?= rtrim(rtrim(number_format((float) $r['fixed_hours'], 2), '0'), '.') ?></td>
            <td class="small text-muted"><?= esc($formats[(int) $r['display_format']] ?? '—') ?></td>
            <td class="text-end mono"><?= (int) $r['display_order'] ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-pc-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('attsetup.paycodes.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this pay code?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="10"><div class="empty-state"><i class="bi bi-tags"></i>No pay codes.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="pcForm" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('attsetup.paycodes.save'), ['id' => 'pcFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Pay Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <?= field_text('code', 'Code', ['col' => 'col-md-3', 'required' => true, 'maxlength' => 20, 'class' => 'mono']) ?>
        <?= field_text('name', 'Name', ['col' => 'col-md-5', 'required' => true, 'maxlength' => 50]) ?>
        <?= field_select('code_type', 'Type', $codeTypes, ['col' => 'col-md-4', 'value' => 1]) ?>
        <?= field_select('tag', 'Multiplier tag', ['' => '—'] + $tags, ['col' => 'col-md-4']) ?>
        <?= field_number('fixed_hours', 'Fixed hours / day', ['col' => 'col-md-4', 'value' => 8, 'step' => '0.5']) ?>
        <?= field_text('symbol', 'Report symbol', ['col' => 'col-md-4', 'maxlength' => 20]) ?>
        <?= field_select('display_format', 'Display format', $formats, ['col' => 'col-md-4', 'value' => 2]) ?>
        <?= field_select('round_off', 'Rounding', $rounds, ['col' => 'col-md-4', 'value' => 1]) ?>
        <?= field_number('min_val', 'Rounding step', ['col' => 'col-md-4', 'value' => '0.1', 'step' => '0.1']) ?>
        <?= field_number('display_order', 'Display order', ['col' => 'col-md-4', 'value' => 999]) ?>
        <div class="col-12 d-flex flex-wrap gap-4">
            <?= field_switch('is_work', 'Counts as worked time', ['checked' => false]) ?>
            <?= field_switch('is_paid', 'Paid', ['checked' => true]) ?>
            <?= field_switch('is_benefit', 'Counts toward benefits', ['checked' => false]) ?>
            <?= field_switch('is_display', 'Show in reports', ['checked' => true]) ?>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-pc-edit').forEach(function (b) {
    b.addEventListener('click', function () {
        var f = document.getElementById('pcFormEl'), d = JSON.parse(b.dataset.json || '{}');
        f.reset(); f.querySelector('[name=id]').value = d.id || '';
        ['code','name','code_type','tag','fixed_hours','symbol','display_format','round_off','min_val','display_order'].forEach(function (k) {
            var el = f.querySelector('[name="' + k + '"]'); if (el) el.value = d[k] == null ? '' : d[k];
        });
        ['is_work','is_paid','is_benefit','is_display'].forEach(function (k) {
            var el = f.querySelector('[name="' + k + '"]'); if (el) el.checked = d[k] === true || d[k] === 't';
        });
        document.querySelector('#pcForm .modal-title').textContent = 'Edit Pay Code';
        new bootstrap.Modal(document.getElementById('pcForm')).show();
    });
});
document.querySelector('[data-bs-target="#pcForm"]')?.addEventListener('click', function () {
    var f = document.getElementById('pcFormEl'); f.reset(); f.querySelector('[name=id]').value = '';
    document.querySelector('#pcForm .modal-title').textContent = 'Add Pay Code';
});
</script>
<?= $this->endSection() ?>
