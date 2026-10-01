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
                <th data-col="date">Date</th><th data-col="day">Day</th><th data-col="code">Code</th><th data-col="employee">Employee</th>
                <th data-col="punch_time">Punch Time</th><th data-col="recorded">Recorded As</th><th data-col="paired">Paired As</th>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono text-nowrap"><?= esc($r['att_date']) ?></td>
                    <td class="small text-muted"><?= esc($r['weekday']) ?></td>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><?= esc($r['name'] ?? '—') ?></td>
                    <td class="mono"><?= esc(substr($r['punch_time'], 0, 5)) ?></td>
                    <td><span class="badge bg-label-secondary"><?= esc($r['punch_label']) ?></span></td>
                    <td>
                        <span class="badge <?= $r['was_corrected'] ? 'bg-label-warning' : 'bg-label-success' ?>"><?= esc($r['adjust_label']) ?></span>
                        <?php if ($r['was_corrected']): ?><i class="bi bi-arrow-repeat text-warning ms-1" title="ZKBio re-paired this punch against the shift window"></i><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No calculated punches in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
    <div class="px-3 py-2 border-top small text-muted">
        "Recorded As" is the raw punch state the device sent; "Paired As" is what ZKBioTime's own
        engine matched it to once it was fit against the shift window — they can differ on a night
        shift, where the device may label the morning clock-out "Check In".
    </div>
</div>
<?= $this->endSection() ?>
