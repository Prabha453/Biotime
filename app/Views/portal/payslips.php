<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th>Period</th><th class="text-end">Basic</th><th class="text-end">Additions</th>
            <th class="text-end">Deductions</th><th class="text-end">Net pay</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $y = (int) $r['y']; $m = (int) $r['m']; ?>
            <tr>
                <td><?= esc(date('F Y', mktime(0, 0, 0, $m, 1, $y))) ?></td>
                <td class="text-end mono"><?= number_format((float) $r['basic_salary'], 2) ?></td>
                <td class="text-end mono text-success"><?= number_format((float) $r['total_increase'], 2) ?></td>
                <td class="text-end mono text-danger"><?= number_format((float) $r['total_deduction'], 2) ?></td>
                <td class="text-end mono fw-semibold"><?= number_format((float) $r['net_pay'], 2) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= route_to('portal.payslip', $y, $m) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No payslips have been published yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?= $this->endSection() ?>
