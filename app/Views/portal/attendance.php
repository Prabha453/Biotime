<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php $t = $totals; ?>

<form method="get" class="d-flex align-items-end gap-2 mb-3">
    <div>
        <label class="form-label small">Month</label>
        <input type="month" name="ym" value="<?= esc($ym) ?>" class="form-control">
    </div>
    <button class="btn btn-primary" style="background:#F26522;border-color:#F26522">Show</button>
    <span class="ms-auto text-muted small align-self-center"><?= esc($label) ?></span>
</form>

<div class="stat-grid mb-3">
    <?php foreach ([
        ['Worked', mins($t['worked_minutes'])],
        ['Present', (int) $t['present_days']],
        ['Absent', (int) $t['absent_days']],
        ['Leave', (float) $t['leave_days']],
        ['Late', mins($t['late_minutes'])],
        ['Overtime', mins($t['ot_minutes'])],
    ] as $c): ?>
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small"><?= esc($c[0]) ?></div>
            <div class="fw-semibold"><?= is_string($c[1]) ? esc($c[1]) : $c[1] ?></div>
        </div></div>
    <?php endforeach; ?>
</div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th>Date</th><th>Scheduled</th><th>First in</th><th>Last out</th>
            <th class="text-end">Worked</th><th class="text-end">Late</th><th class="text-end">OT</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php foreach ($days as $d): ?>
            <tr class="<?= $d['is_weekend'] || $d['is_holiday'] ? 'table-light' : '' ?>">
                <td class="mono"><?= esc($d['date']) ?> <span class="text-muted small"><?= esc($d['weekday']) ?></span></td>
                <td class="mono small"><?= $d['scheduled'] ? esc($d['scheduled']['in'] . '–' . $d['scheduled']['out']) : ($d['is_holiday'] ? esc($d['holiday_name'] ?? 'Holiday') : '—') ?></td>
                <td class="mono"><?= esc($d['first_in'] ?? '—') ?></td>
                <td class="mono"><?= esc($d['last_out'] ?? '—') ?></td>
                <td class="text-end mono"><?= mins($d['worked_minutes']) ?></td>
                <td class="text-end mono text-danger"><?= mins($d['late_minutes']) ?></td>
                <td class="text-end mono text-success"><?= mins($d['ot_minutes']) ?></td>
                <td><?= status_pill($d['status'], $d['status_class']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?= $this->endSection() ?>
