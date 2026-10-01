<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add', 'modal' => 'acForm'],
    'columns'      => true,
    'filter'       => '#acFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'att.attcode', 'label' => 'Attendance Code'],
]) ?>
<?= filter_builder('acFilter', [
    'alias' => ['label' => 'Name', 'type' => 'text'],
    'code'  => ['label' => 'Code', 'type' => 'text'],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th data-col="name">Name</th><th data-col="symbol">Symbol</th><th data-col="fmt">Format</th>
        <th data-col="round">Rounding</th><th data-col="step" class="text-end">Step</th><th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="fw-semibold"><?= esc($r['alias']) ?></td>
            <td class="mono"><?= esc($r['symbol'] ?: '—') ?></td>
            <td class="small"><?= esc($formats[(int) $r['display_format']] ?? '—') ?></td>
            <td class="small"><?= esc($rounds[(int) $r['round_off']] ?? '—') ?></td>
            <td class="text-end mono"><?= rtrim(rtrim(number_format((float) $r['min_val'], 1), '0'), '.') ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-ac-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('attsetup.attcodes.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this attendance code?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-type"></i>No attendance codes.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="acForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('attsetup.attcodes.save'), ['id' => 'acFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Attendance Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <?= field_text('alias', 'Name', ['col' => 'col-12', 'required' => true, 'maxlength' => 50]) ?>
        <?= field_text('symbol', 'Symbol', ['col' => 'col-md-6', 'maxlength' => 20]) ?>
        <?= field_select('display_format', 'Display format', $formats, ['col' => 'col-md-6', 'value' => 2]) ?>
        <?= field_select('round_off', 'Rounding', $rounds, ['col' => 'col-md-6', 'value' => 1]) ?>
        <?= field_number('min_val', 'Rounding step', ['col' => 'col-md-6', 'value' => '0.1', 'step' => '0.1']) ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-ac-edit').forEach(function (b) {
    b.addEventListener('click', function () {
        var f = document.getElementById('acFormEl'), d = JSON.parse(b.dataset.json || '{}');
        f.reset(); f.querySelector('[name=id]').value = d.id || '';
        ['alias','symbol','display_format','round_off','min_val'].forEach(function (k) {
            var el = f.querySelector('[name="' + k + '"]'); if (el) el.value = d[k] == null ? '' : d[k];
        });
        document.querySelector('#acForm .modal-title').textContent = 'Edit Attendance Code';
        new bootstrap.Modal(document.getElementById('acForm')).show();
    });
});
document.querySelector('[data-bs-target="#acForm"]')?.addEventListener('click', function () {
    var f = document.getElementById('acFormEl'); f.reset(); f.querySelector('[name=id]').value = '';
    document.querySelector('#acForm .modal-title').textContent = 'Add Attendance Code';
});
</script>
<?= $this->endSection() ?>
