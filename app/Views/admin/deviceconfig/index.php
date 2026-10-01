<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$sw = static function (string $name, string $label, $on): string {
    return '<label class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="'
        . esc($name, 'attr') . '" value="1" ' . (! empty($on) ? 'checked' : '')
        . '> <span class="form-check-label">' . esc($label) . '</span></label>';
};
$sel = static function (string $name, string $label, array $opts, $cur): string {
    $o = '';
    foreach ($opts as $k => $v) {
        $o .= '<option value="' . esc($k, 'attr') . '"' . ((string) $k === (string) $cur ? ' selected' : '') . '>' . esc($v) . '</option>';
    }

    return '<div class="col-md-6 col-lg-4"><label class="form-label tiny">' . esc($label) . '</label><select name="'
        . esc($name, 'attr') . '" class="form-select">' . $o . '</select></div>';
};
$numf = static fn (string $name, string $label, $cur, string $suffix = '') =>
    '<div class="col-md-6 col-lg-4"><label class="form-label tiny">' . esc($label) . ($suffix ? ' <span class="text-muted">(' . esc($suffix) . ')</span>' : '')
    . '</label><input type="number" min="1" name="' . esc($name, 'attr') . '" class="form-control" value="' . (int) $cur . '"></div>';
?>
<?= page_head($title, $subtitle) ?>

<?= form_open(route_to('deviceconfig.config.save')) ?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Registration &amp; Upload</div>
        <div class="card-body">
            <?= $sw('enable_registration', 'Allow devices to enrol new employees', $cfg['enable_registration']) ?>
            <?= $sw('enable_auto_add', 'Auto-add employees first seen on a device', $cfg['enable_auto_add']) ?>
            <?= $sw('enable_resigned_filter', 'Drop punches from resigned employees', $cfg['enable_resigned_filter']) ?>
            <?= $sw('enable_name_upload', 'Push employee names to devices', $cfg['enable_name_upload']) ?>
            <?= $sw('enable_name_download', 'Accept employee names entered on a device', $cfg['enable_name_download']) ?>
            <?= $sw('enable_card_upload', 'Accept card numbers enrolled on a device', $cfg['enable_card_upload']) ?>
            <?= $sw('encryption', 'Encrypt device ↔ server traffic', $cfg['encryption']) ?>
        </div>
    </div></div>

    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Communication</div>
        <div class="card-body">
            <?= $sw('global_setup', 'Apply these communication settings to every device', $cfg['global_setup']) ?>
            <div class="row g-3 mt-1">
                <?= $sel('timezone', 'Default time zone', $tzList, $cfg['timezone']) ?>
                <?= $numf('heartbeat', 'Heartbeat', $cfg['heartbeat'], 'seconds') ?>
                <?= $sel('transfer_mode', 'Transfer mode', $transferModes, $cfg['transfer_mode']) ?>
                <?= $numf('transfer_interval', 'Transfer interval', $cfg['transfer_interval'], 'minutes') ?>
                <div class="col-md-6 col-lg-4"><label class="form-label tiny">Transfer time</label><input name="transfer_time" class="form-control" value="<?= esc($cfg['transfer_time']) ?>"></div>
                <?= $sel('sync_mode', 'Sync mode', $syncModes, $cfg['sync_mode']) ?>
                <div class="col-md-6 col-lg-4"><label class="form-label tiny">Sync time</label><input name="sync_time" class="form-control" value="<?= esc($cfg['sync_time']) ?>"></div>
            </div>
        </div>
    </div></div>

    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Data Retention <span class="text-muted small fw-normal">days</span></div>
        <div class="card-body"><div class="row g-3">
            <?= $numf('transaction_retention', 'Transactions', $cfg['transaction_retention']) ?>
            <?= $numf('command_retention', 'Commands', $cfg['command_retention']) ?>
            <?= $numf('dev_log_retention', 'Device logs', $cfg['dev_log_retention']) ?>
            <?= $numf('upload_log_retention', 'Upload logs', $cfg['upload_log_retention']) ?>
            <?= $numf('user_notification_log_retention', 'User notifications', $cfg['user_notification_log_retention']) ?>
            <?= $numf('report_log_retention', 'Report logs', $cfg['report_log_retention']) ?>
        </div></div>
    </div></div>

    <div class="col-lg-6"><div class="card h-100">
        <div class="card-header">Bio-Photo Approval Policy</div>
        <div class="card-body"><div class="row g-3">
            <?= $sel('edit_policy', 'Edited on the web', $policies, $cfg['edit_policy']) ?>
            <?= $sel('import_policy', 'Imported', $policies, $cfg['import_policy']) ?>
            <?= $sel('mobile_policy', 'From the mobile app', $policies, $cfg['mobile_policy']) ?>
            <?= $sel('device_policy', 'From a device', $policies, $cfg['device_policy']) ?>
            <?= $sel('api_policy', 'Via the API', $policies, $cfg['api_policy']) ?>
            <?= $sel('visitor_policy', 'Visitor photos', $policies, $cfg['visitor_policy']) ?>
        </div></div>
    </div></div>
</div>
<div class="mt-3"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save configuration</button></div>
<?= form_close() ?>
<?= $this->endSection() ?>
