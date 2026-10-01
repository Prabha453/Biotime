<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $sec = static fn ($s) => mins(intdiv((int) ($s ?? 0), 60)); ?>
<?= page_head($title, $subtitle ?? '', '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>') ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Employee</label><input type="text" name="q" value="<?= esc($q) ?>" placeholder="code or name" class="form-control"></div>
        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Run</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th data-col="date">Date</th><th data-col="day">Day</th><th data-col="code">Code</th><th data-col="employee">Employee</th>
                <th data-col="index" class="text-end">#</th><th data-col="clock_in">Clock In</th><th data-col="clock_out">Clock Out</th>
                <th data-col="duration" class="text-end">Duration</th><th data-col="workday" class="text-end">Work Day</th><th data-col="pay_code">Pay Code</th>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono text-nowrap"><?= esc($r['att_date']) ?></td>
                    <td class="small text-muted"><?= esc($r['weekday']) ?></td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><?= esc($r['name'] ?? '—') ?></td>
                    <td class="text-end mono"><?= (int) $r['data_index'] ?></td>
                    <td class="mono"><?= $r['clock_in']  ? esc(substr($r['clock_in'], 0, 5))  : '—' ?></td>
                    <td class="mono"><?= $r['clock_out'] ? esc(substr($r['clock_out'], 0, 5)) : '—' ?></td>
                    <td class="text-end mono"><?= $sec($r['duration']) ?></td>
                    <td class="text-end mono"><?= number_format((float) $r['workday'], 1) ?></td>
                    <td class="small text-muted"><?= esc($r['pay_code_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No paired punches in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
    <div class="px-3 py-2 border-top small text-muted">
        One row per matched clock-in/clock-out pair, straight from ZKBioTime's own calc engine
        (<code>att_payloadparing</code>) — not recomputed here. "#" is the pair's session index within the day.
    </div>
</div>
<?= $this->endSection() ?>
