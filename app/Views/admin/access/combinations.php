<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$areaOptions = '';
foreach ($areas as $a) {
    $areaOptions .= '<option value="' . $a['id'] . '">' . esc($a['area_name']) . '</option>';
}
?>
<?= page_head($title, $subtitle, modal_btn('coNew', 'New combination')) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Name</th><th>Area</th><th>Levels required (by #)</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($combos as $c): $gs = array_filter([$c['group1'],$c['group2'],$c['group3'],$c['group4'],$c['group5']]); ?>
            <tr>
                <td class="mono"><?= (int) $c['combination_no'] ?></td>
                <td class="fw-semibold"><?= esc($c['combination_name']) ?></td>
                <td><?= esc($c['area_name'] ?? '—') ?></td>
                <td class="mono small"><?= $gs ? esc(implode(' + ', $gs)) : '—' ?></td>
                <td class="text-end" style="white-space:nowrap">
                    <button class="btn btn-sm btn-outline-secondary js-co" data-json='<?= esc(json_encode($c), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('access.combo.delete', $c['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this combination?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $combos): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-diagram-3"></i>No unlock combinations.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<div class="modal fade" id="coNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('access.combo.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Unlock combination</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label><input name="combination_name" class="form-control" required maxlength="100"></div>
        <div class="mb-2"><label class="form-label tiny">Area *</label><select name="area_id" class="form-select" required><option value="">Select…</option><?= $areaOptions ?></select></div>
        <label class="form-label tiny">Access levels that must all present (enter level # — up to 5)</label>
        <div class="row g-2">
            <?php foreach (['group1','group2','group3','group4','group5'] as $g): ?>
                <div class="col"><input name="<?= $g ?>" type="number" min="0" class="form-control mono" placeholder="0"></div>
            <?php endforeach; ?>
        </div>
        <div class="mt-2"><label class="form-label tiny">Remark</label><input name="remark" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function bind(cls, modalId, keys){
  document.querySelectorAll(cls).forEach(function(b){
    b.addEventListener('click', function(){
      var m = document.getElementById(modalId), form = m.querySelector('form'); form.reset();
      if (form.querySelector('[name=id]')) form.querySelector('[name=id]').value = '';
      var d = JSON.parse(b.dataset.json || '{}');
      keys.forEach(function(k){ var f = form.querySelector('[name="'+k+'"]'); if (f) {
        if (f.type === 'checkbox') f.checked = d[k] === 1 || d[k] === true || d[k] === 't';
        else f.value = d[k] == null ? '' : d[k];
      }});
      new bootstrap.Modal(m).show();
    });
  });
}
bind('.js-co', 'coNew', ['id','combination_name','area_id','group1','group2','group3','group4','group5','remark']);
</script>
<?= $this->endSection() ?>
