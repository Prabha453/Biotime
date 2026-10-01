<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
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
                <th data-col="date">Date</th><th data-col="code">Code</th><th data-col="employee">Employee</th><th data-col="department">Department</th>
                <th data-col="times" class="text-end">Punch Times</th><th data-col="time_set">Punch Time</th>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono text-nowrap"><?= esc($r['att_date']) ?></td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><?= esc($r['name'] ?? '—') ?></td>
                    <td class="small text-muted"><?= esc($r['dept_name'] ?? '—') ?></td>
                    <td class="text-end mono"><?= (int) $r['times'] ?></td>
                    <td class="mono small"><?= esc($r['time_set']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No punches in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
