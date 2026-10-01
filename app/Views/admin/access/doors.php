<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('drNew', 'Configure door')) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Device</th><th>Door name</th><th>Lock delay</th><th>Sensor</th><th>Holiday-aware</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($doors as $d): ?>
            <tr>
                <td class="fw-semibold"><?= esc($d['device'] ?? $d['sn']) ?> <span class="mono text-muted small"><?= esc($d['sn']) ?></span></td>
                <td><?= esc($d['door_name'] ?? '—') ?></td>
                <td class="mono"><?= (int) $d['door_lock_delay'] ?>s</td>
                <td class="small"><?= (int) $d['door_sensor_type'] === 0 ? 'None' : ((int) $d['door_sensor_type'] === 1 ? 'Normally open' : 'Normally closed') ?></td>
                <td><?= (int) $d['valid_holiday'] === 1 ? label('yes', 'info') : label('no', 'secondary') ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary js-dr" data-json='<?= esc(json_encode($d), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $doors): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-door-closed"></i>No door parameters set — devices use factory defaults.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<div class="modal fade" id="drNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('access.door.save')) ?>
    <div class="modal-header"><h5 class="modal-title">Door parameters</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Device *</label>
            <select name="terminal_id" class="form-select" required><option value="">Select…</option>
                <?php foreach ($terminals as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['alias'] . ' · ' . $t['sn']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label tiny">Door name</label><input name="door_name" class="form-control" maxlength="50"></div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Lock delay (s)</label><input name="door_lock_delay" type="number" class="form-control" value="5"></div>
            <div class="col"><label class="form-label tiny">Sensor delay (s)</label><input name="door_sensor_delay" type="number" class="form-control" value="10"></div>
            <div class="col"><label class="form-label tiny">Alarm delay (s)</label><input name="door_alarm_delay" type="number" class="form-control" value="30"></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Sensor type</label>
                <select name="door_sensor_type" class="form-select"><option value="0">None</option><option value="1">Normally open</option><option value="2">Normally closed</option></select></div>
            <div class="col"><label class="form-label tiny">Retry times</label><input name="retry_times" type="number" class="form-control" value="3"></div>
        </div>
        <label class="form-check"><input type="checkbox" class="form-check-input" name="valid_holiday" value="1"> <span class="form-check-label tiny">Apply access holidays to this door</span></label>
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
bind('.js-dr', 'drNew', ['terminal_id','door_name','door_lock_delay','door_sensor_delay','door_alarm_delay','door_sensor_type','retry_times','valid_holiday']);
</script>
<?= $this->endSection() ?>
