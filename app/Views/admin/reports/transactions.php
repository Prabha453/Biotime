<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle ?? '', '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>') ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Employee</label><input type="text" name="q" value="<?= esc($q) ?>" placeholder="code or name" class="form-control"></div>
        <div class="col-md-3"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Run</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th data-col="punch_time">Punch time</th><th data-col="code">Code</th><th data-col="employee">Employee</th><th data-col="department">Department</th>
                <th data-col="state">State</th><th data-col="device">Device</th><th data-col="area">Area</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><?= esc(substr((string) $r['punch_time'], 0, 16)) ?></td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><?= esc($r['name'] ?? '—') ?></td>
                    <td class="small text-muted"><?= esc($r['dept_name'] ?? '—') ?></td>
                    <td><?= esc($states[(int) $r['punch_state']] ?? ('#' . $r['punch_state'])) ?></td>
                    <td class="small"><?= esc($r['terminal_alias'] ?? $r['terminal_sn'] ?? '—') ?></td>
                    <td class="small text-muted"><?= esc($r['area_alias'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No punches in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > $perPage):
        $pages = (int) ceil($total / $perPage);
        $lo    = max(1, $page - 3);
        $hi    = min($pages, $page + 3);
        $qs    = static fn ($p) => '?' . http_build_query(['from' => $from, 'to' => $to, 'q' => $q, 'page' => $p]); ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
        <span class="text-muted">Page <?= $page ?> of <?= $pages ?> · <?= number_format($total) ?> punches</span>
        <div class="btn-group btn-group-sm">
            <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= $qs($page - 1) ?>">Prev</a><?php endif; ?>
            <?php for ($i = $lo; $i <= $hi; $i++): ?>
                <a class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= $qs($i) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="<?= $qs($page + 1) ?>">Next</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
