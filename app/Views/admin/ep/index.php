<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('ep.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?php $unit = (int) ($cfg['temp_unit'] ?? 1) === 2 ? '°F' : '°C'; ?>

<?php if ($tab === 'dashboard'): ?>
<form method="get" class="mb-3 d-flex gap-2 align-items-end" style="max-width:280px">
    <div><label class="form-label tiny">Date</label><input type="date" name="d" class="form-control form-control-sm" value="<?= esc($day) ?>"></div>
    <button class="btn btn-sm btn-outline-secondary">Go</button>
</form>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><?= stat_tile('Scans', (int) $agg['total'], 'thermometer-half', 'si-blue') ?></div>
    <div class="col-6 col-lg-3"><?= stat_tile('Over threshold', (int) $agg['high'], 'exclamation-triangle', (int) $agg['high'] > 0 ? 'si-red' : 'si-slate', '≥ ' . esc($cfg['temp_warning']) . $unit) ?></div>
    <div class="col-6 col-lg-3"><?= stat_tile('No mask', (int) $agg['nomask'], 'mask', (int) $agg['nomask'] > 0 ? 'si-amber' : 'si-slate') ?></div>
    <div class="col-6 col-lg-3"><?= stat_tile('Avg temp', ($agg['avg_temp'] ?? '—') . ($agg['avg_temp'] ? $unit : ''), 'activity', 'si-green') ?></div>
</div>
<div class="card"><div class="card-header">Recent scans — <?= esc($day) ?></div><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Time</th><th>Person</th><th>Area</th><th class="text-end">Temp</th><th>Mask</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): $hot = (float) $r['temperature'] >= (float) $cfg['temp_warning']; ?>
            <tr>
                <td class="mono small"><?= esc(substr((string) $r['check_datetime'], 11, 8)) ?></td>
                <td><?= $r['emp_code'] ? esc($r['emp_code'] . ' · ' . ($r['name'] ?: '')) : '<span class="text-muted">Unknown</span>' ?></td>
                <td class="text-muted small"><?= esc($r['area']) ?></td>
                <td class="text-end mono <?= $hot ? 'text-danger fw-semibold' : '' ?>"><?= (float) $r['temperature'] > 0 ? esc($r['temperature']) . $unit : '—' ?></td>
                <td><?= (int) $r['is_mask'] === 1 ? label('yes', 'success') : label('no', 'danger') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $recent): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-thermometer"></i>No temperature scans for this date.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>

<?php elseif ($tab === 'transactions'): ?>
<form method="get" class="mb-3 d-flex gap-2" style="max-width:320px">
    <input name="q" class="form-control form-control-sm" placeholder="Search employee…" value="<?= esc($q) ?>">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
</form>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>When</th><th>Person</th><th>Area</th><th class="text-end">Temp</th><th>Mask</th><th>Source</th><th>Device</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $hot = (float) $r['temperature'] >= (float) $cfg['temp_warning']; ?>
            <tr>
                <td class="mono small"><?= esc(substr((string) $r['check_datetime'], 0, 16)) ?></td>
                <td><?= $r['emp_code'] ? esc($r['emp_code'] . ' · ' . ($r['name'] ?: '')) : '<span class="text-muted">Unknown</span>' ?></td>
                <td class="text-muted small"><?= esc($r['area']) ?></td>
                <td class="text-end mono <?= $hot ? 'text-danger fw-semibold' : '' ?>"><?= (float) $r['temperature'] > 0 ? esc($r['temperature']) . $unit : '—' ?></td>
                <td><?= (int) $r['is_mask'] === 1 ? label('yes', 'success') : label('no', 'danger') ?></td>
                <td class="small text-muted"><?= (int) $r['source'] === 5 ? 'Manual' : 'Device' ?></td>
                <td class="mono small"><?= esc($r['terminal_sn'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-thermometer"></i>No screening records. EP-capable terminals upload here automatically.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div><?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager, [10,15,25,50,100], 'ep') ?></div><?php endif; ?></div>

<?php else: /* setup */ ?>
<div class="card" style="max-width:520px"><div class="card-body">
    <?= form_open(route_to('ep.setup.save')) ?>
    <label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="temp_alarm" value="1" <?= ! empty($cfg['temp_alarm']) ? 'checked' : '' ?>>
        <span class="form-check-label">Raise an alarm on high temperature</span></label>
    <div class="row g-2 mb-3">
        <div class="col"><label class="form-label tiny">Warning threshold</label><input name="temp_warning" type="number" step="0.1" class="form-control" value="<?= esc($cfg['temp_warning'] ?? 37.3) ?>"></div>
        <div class="col"><label class="form-label tiny">Unit</label>
            <select name="temp_unit" class="form-select"><option value="1" <?= (int) ($cfg['temp_unit'] ?? 1) === 1 ? 'selected' : '' ?>>°C</option><option value="2" <?= (int) ($cfg['temp_unit'] ?? 1) === 2 ? 'selected' : '' ?>>°F</option></select></div>
    </div>
    <label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="mask_alarm" value="1" <?= ! empty($cfg['mask_alarm']) ? 'checked' : '' ?>>
        <span class="form-check-label">Raise an alarm when no mask is detected</span></label>
    <div><button class="btn btn-primary">Save thresholds</button></div>
    <?= form_close() ?>
</div></div>
<?php endif; ?>

<?= $this->endSection() ?>
