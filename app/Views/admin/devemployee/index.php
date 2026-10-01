<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$bio = static fn ($n, $ver = null) => $ver && $ver !== ':' && $ver !== '0:0'
    ? '<span class="mono small">Ver ' . esc(str_replace(':', ':', $ver)) . '</span>'
    : ((int) $n > 0 ? '<span class="mono">' . (int) $n . '</span>' : '<span class="text-muted">–</span>');
?>
<?= page_head($title, $subtitle) ?>
<?php
$emLeft = '<div class="btn-group"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Area Transfer</button>'
    . '<ul class="dropdown-menu"><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#emAreaModal">Move to area…</button></li></ul></div>'
    . '<div class="btn-group"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Data Transfer</button>'
    . '<ul class="dropdown-menu">'
    . '<li><button type="button" class="dropdown-item" data-em-op="resync">Re-sync to device</button></li>'
    . '<li><button type="button" class="dropdown-item" data-em-op="reupload">Re-upload from device</button></li>'
    . '<li><hr class="dropdown-divider"></li>'
    . '<li><button type="button" class="dropdown-item text-danger" data-em-op="clear_bio">Delete biometric data</button></li>'
    . '</ul></div>'
    . '<div class="btn-group"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Data Enrollment</button>'
    . '<ul class="dropdown-menu">'
    . '<li><button type="button" class="dropdown-item" data-em-op="enroll_fp">Enrol fingerprint</button></li>'
    . '<li><button type="button" class="dropdown-item" data-em-op="enroll_face">Enrol face</button></li>'
    . '<li><button type="button" class="dropdown-item" data-em-op="enroll_palm">Enrol palm</button></li>'
    . '</ul></div>';
?>
<?= grid_toolbar([
    'left'         => $emLeft,
    'filter'       => '#emFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'export'       => ['key' => 'devemployee'],
    'columns'      => true,
    'history'      => ['prefix' => 'device.employee', 'label' => 'Device employee'],
]) ?>
<?= filter_builder('emFilter', [
    'emp_code'      => ['label' => 'Employee ID', 'type' => 'text'],
    'first_name'    => ['label' => 'First name', 'type' => 'text'],
    'last_name'     => ['label' => 'Last name', 'type' => 'text'],
    'dept_name'     => ['label' => 'Department', 'type' => 'text'],
    'card_no'       => ['label' => 'Card no.', 'type' => 'text'],
    'dev_privilege' => ['label' => 'Device privilege', 'type' => 'select', 'options' => $devPriv],
    'verify_mode'   => ['label' => 'Verify mode', 'type' => 'select', 'options' => $verify],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
        <th data-col="code">Employee ID</th>
        <th data-col="name">First Name</th>
        <th data-col="dept">Department</th>
        <th data-col="card">Card NO.</th>
        <th data-col="fp">Fingerprint</th>
        <th data-col="face">Face</th>
        <th data-col="palm">Palm</th>
        <th data-col="vlpalm">VL Palm</th>
        <th data-col="vlface">VL Face</th>
        <th data-col="vlphoto">VL Face Photo</th>
        <th data-col="enroll">Enroll Device</th>
        <th data-col="priv">Device Privilege</th>
        <th data-col="verify">Verify Mode</th>
        <th data-col="updated">Update Time</th>
        <th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td class="mono"><a href="<?= route_to('employee.view', $r['id']) ?>" class="cell-title" data-turbo="true"><?= esc($r['emp_code']) ?></a></td>
            <td><?= esc($r['first_name'] ?: '—') ?></td>
            <td class="text-muted"><?= esc($r['dept_name'] ?? '—') ?></td>
            <td class="mono small"><?= esc($r['card_no'] ?: '—') ?></td>
            <td><?= $bio($r['fp_n'], $r['fp_ver']) ?></td>
            <td><?= $bio($r['face_n']) ?></td>
            <td><?= $bio($r['palm_n']) ?></td>
            <td><?= $bio($r['vl_palm_n']) ?></td>
            <td><?= $bio($r['vl_face_n']) ?></td>
            <td><?= $bio($r['vl_photo_n']) ?></td>
            <td class="mono small"><?= esc($r['enroll_sn'] ?: '—') ?></td>
            <td class="small"><?= esc($devPriv[(int) $r['dev_privilege']] ?? '—') ?></td>
            <td class="small"><?= esc($verify[(int) $r['verify_mode']] ?? 'Any') ?></td>
            <td class="mono small text-muted"><?= esc($r['updated_at'] ?? '—') ?></td>
            <td class="text-end">
                <button class="icon-ghost js-em-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="16"><div class="empty-state"><i class="bi bi-people"></i>No employees.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<?= form_open(route_to('devemp.transfer'), ['id' => 'emOpForm', 'class' => 'd-none', 'data-confirm' => 'Queue this device action for the selected employees?']) ?>
    <input type="hidden" name="op" value=""><span id="emOpIds"></span>
<?= form_close() ?>

<div class="modal fade" id="emAreaModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('devemp.area_transfer'), ['id' => 'emAreaForm']) ?>
    <div class="modal-header"><h5 class="modal-title">Area Transfer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <span id="emAreaIds"></span>
        <label class="form-label tiny">Move the selected employees to</label>
        <select name="area_id" class="form-select" required>
            <option value="">Select an area…</option>
            <?php foreach ($areas as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['area_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Transfer</button></div>
    <?= form_close() ?>
</div></div></div>

<div class="modal fade" id="deEmpForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open('', ['id' => 'emEditForm']) ?>
    <div class="modal-header"><h5 class="modal-title">Edit Employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-md-6"><label class="form-label tiny">Employee ID</label><input class="form-control mono" id="emEditCode" disabled></div>
        <div class="col-md-6"><label class="form-label tiny">Card no.</label><input name="card_no" class="form-control mono"></div>
        <div class="col-md-6"><label class="form-label tiny">First name</label><input name="first_name" class="form-control"></div>
        <div class="col-md-6"><label class="form-label tiny">Last name</label><input name="last_name" class="form-control"></div>
        <div class="col-md-6"><label class="form-label tiny">Device privilege</label><select name="dev_privilege" class="form-select"><?php foreach ($devPriv as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label tiny">Verify mode</label><select name="verify_mode" class="form-select"><?php foreach ($verify as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const sel = () => [...document.querySelectorAll('.grid-check:checked')].map(c => c.value);
    const inputs = ids => ids.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');

    document.addEventListener('click', function (e) {
        const op = e.target.closest('[data-em-op]');
        if (op) {
            const ids = sel();
            if (!ids.length) { alert('Select at least one employee.'); return; }
            const f = document.getElementById('emOpForm');
            f.querySelector('[name=op]').value = op.dataset.emOp;
            document.getElementById('emOpIds').innerHTML = inputs(ids);
            f.requestSubmit ? f.requestSubmit() : f.submit();
        }
        const at = e.target.closest('[data-bs-target="#emAreaModal"]');
        if (at) {
            const ids = sel();
            if (!ids.length) { setTimeout(() => alert('Select at least one employee.')); return; }
            document.getElementById('emAreaIds').innerHTML = inputs(ids);
        }
        const ed = e.target.closest('.js-em-edit');
        if (ed) {
            const d = JSON.parse(ed.dataset.json || '{}');
            const f = document.getElementById('emEditForm');
            f.action = '<?= site_url('admin/device-employees') ?>/' + d.id + '/edit';
            f.reset();
            document.getElementById('emEditCode').value = d.emp_code || '';
            ['card_no', 'first_name', 'last_name', 'dev_privilege', 'verify_mode'].forEach(k => {
                const el = f.querySelector('[name="' + k + '"]'); if (el) el.value = d[k] == null ? '' : d[k];
            });
            new bootstrap.Modal(document.getElementById('deEmpForm')).show();
        }
    });
})();
</script>
<?= $this->endSection() ?>
