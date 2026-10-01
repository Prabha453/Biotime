<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$areaOptions = '';
foreach ($areas as $a) {
    $areaOptions .= '<option value="' . $a['id'] . '">' . esc($a['area_name']) . '</option>';
}
$wdLabels = ['sun' => 'Sunday', 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday'];
?>
<?= page_head($title, $subtitle, modal_btn('tzNew', 'New time zone')) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Name</th><th>Area</th><th>Mon window</th><th>Weekend</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($timezones as $z): ?>
            <tr>
                <td class="mono"><?= (int) $z['timezone_no'] ?></td>
                <td class="fw-semibold"><?= esc($z['timezone_name']) ?></td>
                <td><?= esc($z['area_name'] ?? '—') ?></td>
                <td class="mono small"><?= esc(substr((string) $z['mon_start'], 0, 5)) ?>–<?= esc(substr((string) $z['mon_end'], 0, 5)) ?></td>
                <td><?= ((int) $z['sat_on'] || (int) $z['sun_on']) ? '<span class="pill pill-info">Open</span>' : '<span class="pill pill-muted">Closed</span>' ?></td>
                <td class="text-end" style="white-space:nowrap">
                    <button class="btn btn-sm btn-outline-secondary js-tz-edit" data-json='<?= esc(json_encode($z), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('access.tz.delete', $z['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this time zone?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $timezones): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-clock"></i>No time zones defined.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<div class="modal fade" id="tzNew" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('access.tz.save'), ['id' => 'tzFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title" id="tzFormTitle">New time zone</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-md-6"><label class="form-label tiny">Area *</label><select name="area_id" class="form-select" required><option value="">Select…</option><?= $areaOptions ?></select></div>
            <div class="col-md-3"><label class="form-label tiny">Time Period No. *</label><input type="number" min="1" name="timezone_no" class="form-control" required value="<?= (int) $nextTimezoneNo ?>"></div>
            <div class="col-md-3"><label class="form-label tiny">Name *</label><input name="timezone_name" class="form-control" required></div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Remark</label><input name="remark" class="form-control" maxlength="999"></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th></th><th>Start Time</th><th>End Time</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($wdLabels as $k => $lbl): ?>
                    <tr>
                        <td class="small"><?= $lbl ?></td>
                        <td><input type="time" name="<?= $k ?>_start" class="form-control form-control-sm" value="00:00"></td>
                        <td><input type="time" name="<?= $k ?>_end" class="form-control form-control-sm" value="23:59"></td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="<?= $k ?>_on" value="1" id="tzOn_<?= $k ?>" <?= in_array($k, ['mon','tue','wed','thu','fri']) ? 'checked' : '' ?>>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var m = document.getElementById('tzNew'), form = document.getElementById('tzFormEl');
    var days = ['sun','mon','tue','wed','thu','fri','sat'];
    m.addEventListener('show.bs.modal', function (ev) {
        if (!ev.relatedTarget || !ev.relatedTarget.classList.contains('js-tz-edit')) {
            form.reset();
            form.querySelector('[name=id]').value = '';
            document.getElementById('tzFormTitle').textContent = 'New time zone';
        }
    });
    document.querySelectorAll('.js-tz-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = JSON.parse(b.dataset.json || '{}');
            form.reset();
            document.getElementById('tzFormTitle').textContent = 'Edit time zone';
            form.querySelector('[name=id]').value = d.id || '';
            ['area_id', 'timezone_no', 'timezone_name', 'remark'].forEach(function (k) {
                var f = form.querySelector('[name="' + k + '"]'); if (f) f.value = d[k] == null ? '' : d[k];
            });
            days.forEach(function (k) {
                form.querySelector('[name="' + k + '_start"]').value = (d[k + '_start'] || '00:00:00').slice(0, 5);
                form.querySelector('[name="' + k + '_end"]').value = (d[k + '_end'] || '23:59:00').slice(0, 5);
                form.querySelector('[name="' + k + '_on"]').checked = String(d[k + '_on']) === '1';
            });
            new bootstrap.Modal(m).show();
        });
    });
})();
</script>
<?= $this->endSection() ?>
