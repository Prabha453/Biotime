<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head(esc($dev['alias']), 'Serial ' . esc($dev['sn']),
    modal_btn('devEdit', 'Edit device', 'pencil', 'btn-outline-secondary')
    . '<a href="' . route_to('devices') . '" class="btn btn-sm btn-outline-secondary">Back</a>') ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between">
                <h5 class="mb-0"><?= esc($dev['alias']) ?></h5>
                <span class="pill pill-<?= ($dev['online'] === true || $dev['online'] === 't') ? 'success' : 'muted' ?>">
                    <?= ($dev['online'] === true || $dev['online'] === 't') ? 'Online' : 'Offline' ?>
                </span>
            </div>
            <dl class="row small mt-2 mb-0">
                <dt class="col-5 text-muted">Serial</dt><dd class="col-7 mono"><?= esc($dev['sn']) ?></dd>
                <dt class="col-5 text-muted">IP</dt><dd class="col-7 mono"><?= esc($dev['ip_address']) ?> / <?= esc($dev['real_ip'] ?? '') ?></dd>
                <dt class="col-5 text-muted">Area</dt><dd class="col-7"><?= esc($dev['area_name'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Platform</dt><dd class="col-7 mono"><?= esc($dev['platform'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Firmware</dt><dd class="col-7 mono"><?= esc($dev['fw_ver'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Push proto</dt><dd class="col-7 mono"><?= esc($dev['push_protocol'] ?? '—') ?> <?= esc($dev['push_ver'] ?? '') ?></dd>
                <dt class="col-5 text-muted">FP / Face</dt><dd class="col-7"><?= (int) $dev['fp_count'] ?> / <?= (int) $dev['face_count'] ?></dd>
                <dt class="col-5 text-muted">Users</dt><dd class="col-7"><?= (int) $dev['user_count'] ?></dd>
                <dt class="col-5 text-muted">Transactions</dt><dd class="col-7"><?= number_format((int) $dev['transaction_count']) ?></dd>
                <dt class="col-5 text-muted">Last activity</dt><dd class="col-7 mono"><?= esc($dev['last_seen']) ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-header">Punches · last 14 days</div>
            <div class="card-body"><canvas id="devChart" height="80"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-7">
        <div class="card"><div class="card-header">Recent punches</div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Time</th><th>Employee</th><th>Code</th><th>Event</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td class="mono"><?= esc($r['tm']) ?></td>
                        <td><?= esc($r['name'] ?? '—') ?></td>
                        <td class="mono"><?= esc($r['emp_code']) ?></td>
                        <td><?= esc($stateLabels[(int) $r['punch_state']] ?? 'Punch') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Remote commands <span class="text-muted small">— run on the device's next check-in</span></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?= form_open(route_to('device.command_one', $dev['id']), ['class' => 'd-inline']) ?>
                        <input type="hidden" name="cmd" value="info">
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-info-circle me-1"></i>Query info</button>
                    <?= form_close() ?>
                    <?= form_open(route_to('device.command_one', $dev['id']), ['class' => 'd-inline']) ?>
                        <input type="hidden" name="cmd" value="sync_time">
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock me-1"></i>Sync clock</button>
                    <?= form_close() ?>
                    <?= form_open(route_to('device.command_one', $dev['id']), ['class' => 'd-inline', 'data-confirm' => 'Reboot this device now?']) ?>
                        <input type="hidden" name="cmd" value="reboot">
                        <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-clockwise me-1"></i>Reboot</button>
                    <?= form_close() ?>
                    <?= form_open(route_to('device.command_one', $dev['id']), ['class' => 'd-inline', 'data-confirm' => 'Clear the attendance log ON THE DEVICE? Punches already uploaded here are kept.', 'data-confirm-danger' => '']) ?>
                        <input type="hidden" name="cmd" value="clear_attlog">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-eraser me-1"></i>Clear device log</button>
                    <?= form_close() ?>
                    <?= form_open(route_to('device.command_one', $dev['id']), ['class' => 'd-inline']) ?>
                        <input type="hidden" name="cmd" value="clear_cmd">
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear pending</button>
                    <?= form_close() ?>
                </div>
                <?= form_open(route_to('device.command_one', $dev['id']), ['class' => 'row g-2 mt-2 align-items-end']) ?>
                    <input type="hidden" name="cmd" value="reupload_trans">
                    <div class="col"><label class="form-label tiny">Re-upload attendance — from</label><input type="date" name="from" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('-7 days')) ?>"></div>
                    <div class="col"><label class="form-label tiny">to</label><input type="date" name="to" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-cloud-download me-1"></i>Request</button></div>
                <?= form_close() ?>
            </div>
        </div>

        <?php if (! empty($pending)): ?>
        <div class="card mb-3"><div class="card-header">Queued <span class="pill pill-warning ms-1"><?= count($pending) ?></span></div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Queued</th><th>Command</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pending as $c): ?>
                    <tr>
                        <td class="mono small"><?= esc($c['ct']) ?></td>
                        <td class="small text-truncate" style="max-width:180px"><?= esc($c['content']) ?>
                            <?= $c['transfer_time'] ? '<span class="pill pill-info ms-1">sent</span>' : '' ?></td>
                        <td class="text-end">
                            <?= form_open(route_to('device.command.cancel', $dev['id'], $c['id']), ['class' => 'd-inline']) ?>
                                <button class="btn btn-sm btn-outline-danger" title="Cancel"><i class="bi bi-x-lg"></i></button>
                            <?= form_close() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <?php endif; ?>

        <div class="card"><div class="card-header">Command log</div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>When</th><th>Command</th><th>Ret</th></tr></thead>
                <tbody>
                <?php foreach ($commands as $c): ?>
                    <tr><td class="mono small"><?= esc($c['ct']) ?></td>
                        <td class="small text-truncate" style="max-width:220px"><?= esc($c['content']) ?></td>
                        <td class="mono"><?= esc($c['return_value']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (! $commands): ?><tr><td colspan="3" class="text-muted text-center">No commands.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
        <div class="card mt-3"><div class="card-header">Device parameters</div>
            <div class="card-body" style="max-height:260px;overflow:auto">
                <table class="table table-sm mb-0"><tbody>
                <?php foreach ($params as $p): ?>
                    <tr><td class="text-muted small"><?= esc($p['param_name']) ?></td><td class="mono small text-end"><?= esc($p['param_value']) ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="devEdit" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('device.save_id', $dev['id'])) ?>
    <div class="modal-header"><h5 class="modal-title">Edit device</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Display name *</label><input name="alias" class="form-control" value="<?= esc($dev['alias']) ?>" required></div>
        <div class="mb-2"><label class="form-label tiny">Area</label>
            <select name="area_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($areas as $a): ?><option value="<?= $a['id'] ?>" <?= (int) $dev['area_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['area_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_attendance" value="1" id="devAtt" <?= (int) $dev['is_attendance'] ? 'checked' : '' ?>>
            <label class="form-check-label small" for="devAtt">Count this device's punches for attendance</label>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
var _b = (getComputedStyle(document.documentElement).getPropertyValue('--primary') || '#7ac142').trim();
new Chart(document.getElementById('devChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn ($r) => substr($r['d'], 5), $daily)) ?>,
        datasets: [{ label: 'Punches', data: <?= json_encode(array_map(fn ($r) => (int) $r['c'], $daily)) ?>,
            borderColor: _b, backgroundColor: _b + '1f', fill: true, tension: .3 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } }
});
</script>
<?= $this->endSection() ?>
