<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= page_head($title, $subtitle, modal_btn('wcForm', 'New work code', 'plus-lg')) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Code</th><th>Description</th><th>Company</th><th class="text-end">Used by</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono fw-semibold"><?= esc($r['code']) ?></td>
                <td><?= esc($r['alias']) ?></td>
                <td class="text-muted"><?= esc($r['company_name'] ?? 'All companies') ?></td>
                <td class="mono text-end"><?= (int) $r['uses'] ?></td>
                <td class="text-end" style="white-space:nowrap">
                    <button class="btn btn-sm btn-outline-secondary js-edit" data-bs-toggle="modal" data-bs-target="#wcForm"
                        data-id="<?= $r['id'] ?>" data-code="<?= esc($r['code'], 'attr') ?>" data-alias="<?= esc($r['alias'], 'attr') ?>"
                        data-company_id="<?= $r['company_id'] ?>"><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('workcode.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this work code?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-upc-scan"></i>No work codes yet.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="wcForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('workcode.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Work code</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-4"><label class="form-label tiny">Code *</label><input name="code" class="form-control mono" required maxlength="8" placeholder="OT01"></div>
            <div class="col"><label class="form-label tiny">Description *</label><input name="alias" class="form-control" required maxlength="24" placeholder="Project overtime"></div>
        </div>
        <div class="mb-1"><label class="form-label tiny">Company</label>
            <select name="company_id" class="form-select"><option value="">All companies</option>
                <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['company_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.getElementById('wcForm')?.addEventListener('show.bs.modal', function (ev) {
    var t = ev.relatedTarget, form = this.querySelector('form');
    form.reset();
    var edit = t && t.classList.contains('js-edit');
    form.querySelector('[name=id]').value = edit ? t.dataset.id : '';
    this.querySelector('.modal-title').textContent = edit ? 'Edit work code' : 'New work code';
    if (edit) ['code', 'alias', 'company_id'].forEach(function (k) {
        var f = form.querySelector('[name=' + k + ']'); if (f) f.value = t.dataset[k] === 'null' ? '' : (t.dataset[k] || '');
    });
});
</script>
<?= $this->endSection() ?>
