<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$tzOpts   = $tzList;
$areaOpts = ['' => '—'] + opts_from($areas, 'id', 'area_name');
// group the command list for the three ZKBio dropdowns
$byGroup = ['clean' => [], 'transfer' => [], 'menu' => []];
foreach ($commands as $key => $c) {
    $byGroup[$c['group']][$key] = $c['label'];
}
$cmdDropdown = static function (string $label, array $items): string {
    $li = '';
    foreach ($items as $key => $text) {
        $li .= '<li><button type="button" class="dropdown-item" data-dev-cmd="' . esc($key, 'attr') . '">' . esc($text) . '</button></li>';
    }

    return '<div class="btn-group"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">'
        . esc($label) . '</button><ul class="dropdown-menu">' . $li . '</ul></div>';
};
?>
<?= page_head($title, $subtitle) ?>
<?php
$devLeft = '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#devForm" data-dev-new><i class="bi bi-plus-lg me-1"></i>Add</button>'
    . '<button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete data-url="' . route_to('device.bulk_delete') . '" disabled><i class="bi bi-trash me-1"></i>Delete <span data-grid-count>0</span></button>'
    . '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#devAreaForm"><i class="bi bi-geo me-1"></i>New Area</button>'
    . '<button type="button" class="btn btn-sm btn-outline-secondary" data-dev-cmd="clear_cmd" data-dev-need-sel><i class="bi bi-x-circle me-1"></i>Clear Pending Command</button>'
    . $cmdDropdown('Data Clean', $byGroup['clean'])
    . $cmdDropdown('Data Transfer', $byGroup['transfer'])
    . $cmdDropdown('Device Menu', $byGroup['menu']);
?>
<?= grid_toolbar([
    'left'         => $devLeft,
    'filter'       => '#devFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'export'       => ['key' => 'devices'],
    'columns'      => true,
    'history'      => ['prefix' => 'device', 'label' => 'Device'],
]) ?>

<?= filter_builder('devFilter', [
    'alias'         => ['label' => 'Device name', 'type' => 'text'],
    'sn'            => ['label' => 'Serial number', 'type' => 'text'],
    'ip_address'    => ['label' => 'Device IP', 'type' => 'text'],
    'area'          => ['label' => 'Area', 'type' => 'text'],
    'fw_ver'        => ['label' => 'Firmware', 'type' => 'text'],
    'last_activity' => ['label' => 'Last activity', 'type' => 'date'],
], ['button' => false]) ?>

<div data-crud="<?= route_to('devices') ?>">
    <div data-crud-results class="ajax-region">
        <?= view('admin/devices/_list', ['rows' => $rows, 'pager' => $pager]) ?>
    </div>
</div>

<!-- ====== hidden form used by every command / bulk button (ids injected by JS) ====== -->
<?= form_open(route_to('device.command'), ['id' => 'devCmdForm', 'class' => 'd-none']) ?>
    <input type="hidden" name="cmd" value="">
    <span id="devCmdIds"></span>
    <input type="hidden" name="from" id="devCmdFrom">
    <input type="hidden" name="to" id="devCmdTo">
<?= form_close() ?>

<!-- ====== Add / Edit device ====== -->
<div class="modal fade" id="devForm" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('device.save'), ['id' => 'devFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Device</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <?= field_text('alias', 'Device name', ['col' => 'col-md-6', 'required' => true, 'maxlength' => 50]) ?>
        <?= field_text('sn', 'Serial number', ['col' => 'col-md-6', 'required' => true, 'maxlength' => 50, 'class' => 'mono']) ?>
        <?= field_text('ip_address', 'Device IP', ['col' => 'col-md-6', 'class' => 'mono', 'placeholder' => '0.0.0.0']) ?>
        <?= field_select('area_id', 'Area', $areaOpts, ['col' => 'col-md-6']) ?>
        <?= field_select('terminal_tz', 'Time zone', $tzOpts, ['col' => 'col-md-6', 'value' => 330]) ?>
        <?= field_select('transfer_mode', 'Transfer mode', $modes, ['col' => 'col-md-6', 'value' => 1]) ?>
        <?= field_number('heartbeat', 'Heartbeat (s)', ['col' => 'col-md-4', 'value' => 10, 'min' => 1]) ?>
        <?= field_number('transfer_interval', 'Transfer interval (min)', ['col' => 'col-md-4', 'value' => 1, 'min' => 1]) ?>
        <?= field_text('transfer_time', 'Transfer time', ['col' => 'col-md-4', 'value' => '00:00;14:05', 'hint' => 'Semicolon-separated HH:MM']) ?>
        <div class="col-12 d-flex flex-wrap gap-4">
            <?= field_switch('is_attendance', 'Attendance device', ['checked' => true]) ?>
            <?= field_switch('is_registration', 'Registration device', ['checked' => false]) ?>
            <?= field_switch('is_access', 'Access control device', ['checked' => false]) ?>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- ====== New Area ====== -->
<div class="modal fade" id="devAreaForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('device.new_area')) ?>
    <div class="modal-header"><h5 class="modal-title">New Area</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <?= field_select('company_id', 'Company', opts_from($companies, 'id', 'company_name'), ['col' => 'col-12']) ?>
        <?= field_text('area_code', 'Area code', ['col' => 'col-md-6', 'required' => true, 'maxlength' => 30]) ?>
        <?= field_text('area_name', 'Area name', ['col' => 'col-md-6', 'required' => true, 'maxlength' => 100]) ?>
        <?= field_select('parent_area_id', 'Superior area', ['' => '—'] + opts_from($areas, 'id', 'area_name'), ['col' => 'col-12']) ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- ====== Date range prompt (for "Re-upload transactions") ====== -->
<div class="modal fade" id="devRangeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Re-upload transactions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label tiny">From</label><input type="date" class="form-control" id="rngFrom" value="<?= date('Y-m-d', strtotime('-7 days')) ?>"></div>
        <div class="col-6"><label class="form-label tiny">To</label><input type="date" class="form-control" id="rngTo" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="rngGo">Queue</button></div>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const scope   = document.querySelector('[data-crud-results]');
    const cmdForm = document.getElementById('devCmdForm');
    const selIds  = () => [...scope.querySelectorAll('.grid-check:checked')].map(c => c.value);

    function runCmd(cmd, from, to) {
        const ids = selIds();
        if (!ids.length) { alert('Select at least one device first.'); return; }
        cmdForm.querySelector('[name=cmd]').value = cmd;
        document.getElementById('devCmdIds').innerHTML = ids.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
        document.getElementById('devCmdFrom').value = from || '';
        document.getElementById('devCmdTo').value = to || '';
        cmdForm.submit();
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-dev-cmd]');
        if (!btn) return;
        const cmd = btn.dataset.devCmd;
        if (cmd === 'reupload_trans') {
            if (!selIds().length) { alert('Select at least one device first.'); return; }
            new bootstrap.Modal(document.getElementById('devRangeModal')).show();
            return;
        }
        if (!confirm('Queue this command for the selected device(s)?')) return;
        runCmd(cmd);
    });
    document.getElementById('rngGo').addEventListener('click', function () {
        runCmd('reupload_trans', document.getElementById('rngFrom').value, document.getElementById('rngTo').value);
    });

    // edit device → fill the modal
    document.addEventListener('click', function (e) {
        const b = e.target.closest('.js-dev-edit');
        if (b) {
            const d = JSON.parse(b.dataset.json || '{}');
            const f = document.getElementById('devFormEl');
            f.reset();
            ['id', 'alias', 'sn', 'ip_address', 'area_id', 'terminal_tz', 'transfer_mode', 'heartbeat', 'transfer_interval', 'transfer_time'].forEach(function (k) {
                const el = f.querySelector('[name="' + k + '"]'); if (el) el.value = d[k] == null ? '' : d[k];
            });
            ['is_attendance', 'is_registration', 'is_access'].forEach(function (k) {
                const el = f.querySelector('[name="' + k + '"]'); if (el) el.checked = String(d[k]) === '1';
            });
            f.querySelector('.modal-title') || (document.querySelector('#devForm .modal-title').textContent = 'Edit device');
            new bootstrap.Modal(document.getElementById('devForm')).show();
        }
        if (e.target.closest('[data-dev-new]')) {
            const f = document.getElementById('devFormEl');
            f.reset();
            f.querySelector('[name=id]').value = '';
            document.querySelector('#devForm .modal-title').textContent = 'Add device';
        }
    });
})();
</script>
<?= $this->endSection() ?>
