<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php $t = $totals; ?>

<div class="d-flex flex-wrap align-items-baseline gap-2 mb-3">
    <span class="text-muted">Attendance for</span><span class="fw-semibold"><?= esc($period) ?></span>
</div>

<div class="stat-grid mb-4">
    <?php foreach ([
        ['Present days', (int) $t['present_days'], 'text-success'],
        ['Absent days', (int) $t['absent_days'], $t['absent_days'] ? 'text-danger' : ''],
        ['Leave days', (float) $t['leave_days'], ''],
        ['Late arrivals', (int) $t['late_days'], $t['late_days'] ? 'text-warning' : ''],
        ['Late minutes', mins($t['late_minutes']), ''],
        ['Overtime', round($t['ot_minutes'] / 60, 1) . ' h', 'text-success'],
    ] as $c): ?>
        <div class="card"><div class="card-body">
            <div class="text-muted small"><?= esc($c[0]) ?></div>
            <div class="h4 <?= $c[2] ?>"><?= is_string($c[1]) ? esc($c[1]) : $c[1] ?></div>
        </div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6">Quick actions</h2>
            <div class="d-flex flex-column gap-2 mt-2">
                <a class="btn btn-outline-secondary text-start" href="<?= route_to('portal.attendance') ?>"><i class="bi bi-calendar-check me-2"></i>View my daily register</a>
                <a class="btn btn-outline-secondary text-start" href="<?= route_to('portal.requests') ?>"><i class="bi bi-send me-2"></i>Apply for leave / overtime / punch correction</a>
                <a class="btn btn-outline-secondary text-start" href="<?= route_to('portal.payslips') ?>"><i class="bi bi-cash-stack me-2"></i>Open my payslips</a>
            </div>
            <?php if ($pending > 0): ?>
                <div class="alert alert-warning mt-3 mb-0 py-2 small"><i class="bi bi-hourglass-split me-1"></i><?= $pending ?> request<?= $pending === 1 ? '' : 's' ?> awaiting approval.</div>
            <?php endif; ?>
            <?php if ($lastSlip): ?>
                <div class="mt-3 small text-muted">Last payslip:
                    <a href="<?= route_to('portal.payslip', (int) $lastSlip['y'], (int) $lastSlip['m']) ?>">
                        <?= esc(date('F Y', mktime(0, 0, 0, (int) $lastSlip['m'], 1, (int) $lastSlip['y']))) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6">Leave balances · <?= date('Y') ?></h2>
            <?php if (! $balances): ?>
                <p class="text-muted small mb-0">No leave entitlement is configured for your leave group.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table table-sm mb-0 mt-2">
                    <thead><tr><th>Type</th><th class="text-end">Entitled</th><th class="text-end">Taken</th><th class="text-end">Balance</th></tr></thead>
                    <tbody>
                    <?php foreach ($balances as $b): ?>
                        <tr>
                            <td><?= esc($b['name']) ?></td>
                            <td class="text-end mono"><?= rtrim(rtrim(number_format($b['entitlement'], 1), '0'), '.') ?></td>
                            <td class="text-end mono"><?= rtrim(rtrim(number_format($b['taken'], 1), '0'), '.') ?></td>
                            <td class="text-end mono fw-semibold <?= $b['balance'] <= 0 ? 'text-danger' : 'text-success' ?>">
                                <?= rtrim(rtrim(number_format($b['balance'], 1), '0'), '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
