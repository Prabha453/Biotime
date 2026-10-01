<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$sec = static fn ($s) => mins(intdiv((int) ($s ?? 0), 60));      // att_payloadmulpunchset totals are seconds
$min = static fn ($m) => mins((int) ($m ?? 0));                  // att_payloadbreak totals are already minutes
?>
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
                <?php if ($kind === 'work'): ?>
                    <th data-col="session" class="text-end">Session</th><th data-col="clock_in">Clock In</th><th data-col="clock_out">Clock Out</th>
                    <th data-col="total_time" class="text-end">Total Time</th><th data-col="worked_time" class="text-end">Worked Time</th>
                <?php else: ?>
                    <th data-col="break_out">Break Out</th><th data-col="break_in">Break In</th><th data-col="allowed" class="text-end">Allowed</th>
                    <th data-col="taken" class="text-end">Taken</th><th data-col="actual" class="text-end">Actual</th>
                    <th data-col="early_in" class="text-end">Early In</th><th data-col="late_in" class="text-end">Late In</th><th data-col="absent" class="text-end">Absent</th>
                <?php endif; ?>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono text-nowrap"><?= esc($r['att_date']) ?></td>
                    <td class="small text-muted"><?= esc($r['weekday']) ?></td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><?= esc($r['name'] ?? '—') ?></td>
                    <?php if ($kind === 'work'): ?>
                        <td class="text-end mono"><?= (int) $r['data_index'] ?></td>
                        <td class="mono"><?= $r['clock_in']  ? esc(substr($r['clock_in'], 0, 5))  : '—' ?></td>
                        <td class="mono"><?= $r['clock_out'] ? esc(substr($r['clock_out'], 0, 5)) : '—' ?></td>
                        <td class="text-end mono"><?= $sec($r['total_time']) ?></td>
                        <td class="text-end mono"><?= $sec($r['worked_time']) ?></td>
                    <?php else: ?>
                        <td class="mono"><?= $r['break_out'] ? esc(substr($r['break_out'], 0, 5)) : '—' ?></td>
                        <td class="mono"><?= $r['break_in']  ? esc(substr($r['break_in'], 0, 5))  : '—' ?></td>
                        <td class="text-end mono"><?= $min($r['duration']) ?></td>
                        <td class="text-end mono"><?= $min($r['taken']) ?></td>
                        <td class="text-end mono"><?= $min($r['actual_duration']) ?></td>
                        <td class="text-end mono text-success"><?= $min($r['early_in']) ?></td>
                        <td class="text-end mono text-danger"><?= $min($r['late_in']) ?></td>
                        <td class="text-end mono text-danger"><?= $min($r['absent']) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?>
                <tr><td colspan="<?= $kind === 'work' ? 9 : 11 ?>" class="text-center text-muted py-4">
                    No <?= $kind === 'work' ? 'multi-session' : 'break' ?> records in this range.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
    <div class="px-3 py-2 border-top small text-muted">
        <?php if ($kind === 'work'): ?>
            Every clock-in/clock-out session on a day that had more than one, straight from ZKBioTime's
            own calc engine (<code>att_payloadmulpunchset</code>) — populated only when an employee
            actually punched multiple sessions in a day.
        <?php else: ?>
            Every break instance ZKBioTime's engine recorded (<code>att_payloadbreak</code>) — populated
            only for a break configured "Required Punch", where the employee punched break-out/break-in.
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
