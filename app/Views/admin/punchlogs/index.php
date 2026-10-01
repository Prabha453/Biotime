<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('pAdd', 'Add punch', 'fingerprint')) ?>

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Employee</label><input name="q" value="<?= esc($filters['q']) ?>" class="form-control" placeholder="Name or code"></div>
        <div class="col-md-2"><label class="form-label">Event</label>
            <select name="state" class="form-select">
                <option value="">Any</option>
                <?php foreach ($stateLabels as $k => $v): ?><option value="<?= $k ?>" <?= (string) $filters['state'] === (string) $k ? 'selected' : '' ?>><?= esc($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Device</label>
            <select name="device" class="form-select">
                <option value="">All</option>
                <?php foreach ($devices as $d): ?><option value="<?= $d['id'] ?>" <?= (int) $filters['dev'] === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['alias']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Source</label>
            <select name="src" class="form-select">
                <option value="">All</option><option value="manual" <?= $filters['src'] === 'manual' ? 'selected' : '' ?>>Manual only</option>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($filters['from']) ?>" class="form-control"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Punch time</th><th>Employee</th><th>Code</th><th>Event</th><th>Source</th><th>Device</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $manual = (int) ($r['source'] ?? 0) === 5; ?>
                <tr>
                    <td class="mono"><?= esc($r['punch_time']) ?></td>
                    <td>
                        <?php if ($r['emp_id']): ?><a href="<?= route_to('employee.view', $r['emp_id']) ?>"><?= esc($r['emp_name'] ?? $r['emp_code']) ?></a>
                        <?php else: ?><span class="text-muted">Unmatched</span><?php endif; ?>
                    </td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><?php $s = (int) $r['punch_state']; $cls = in_array($s, [0,4]) ? 'success' : (in_array($s, [1,5]) ? 'danger' : 'warning'); ?>
                        <span class="pill pill-<?= $cls ?>"><?= esc($stateLabels[$s] ?? 'Punch') ?></span></td>
                    <td><?= $manual ? '<span class="pill pill-info">Manual</span>' : '<span class="pill pill-muted">Device</span>' ?></td>
                    <td class="text-muted"><?= esc($r['device'] ?? $r['terminal_sn'] ?? '—') ?></td>
                    <td class="text-end">
                        <?php if ($manual): ?>
                        <?= form_open(route_to('punch.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this manual punch?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-fingerprint"></i>No punches match.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="pAdd" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('punch.add')) ?>
    <div class="modal-header"><h5 class="modal-title">Add a manual punch</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required>
                <option value="">Select…</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2">
            <div class="col-5"><label class="form-label tiny">Date *</label><input type="date" name="punch_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-4"><label class="form-label tiny">Time *</label><input type="time" name="punch_time" class="form-control" required></div>
            <div class="col-3"><label class="form-label tiny">Event</label>
                <select name="punch_state" class="form-select"><option value="0">In</option><option value="1">Out</option><option value="2">Brk out</option><option value="3">Brk in</option></select>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add punch</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
