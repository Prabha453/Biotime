<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle ?? '', '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>') ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Group by</label>
            <select name="by" class="form-select">
                <option value="department" <?= $by === 'department' ? 'selected' : '' ?>>Department</option>
                <option value="employee" <?= $by === 'employee' ? 'selected' : '' ?>>Employee</option>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Run</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 text-nowrap">
            <thead><tr>
                <th data-col="group"><?= $by === 'employee' ? 'Employee' : 'Department' ?></th>
                <th data-col="work_days" class="text-end">Work days</th><th data-col="present" class="text-end">Present</th><th data-col="absent" class="text-end">Absent</th>
                <th data-col="leave" class="text-end">Leave</th><th data-col="late" class="text-end">Late (days)</th><th data-col="late_min" class="text-end">Late (mins)</th>
                <th data-col="early" class="text-end">Early</th><th data-col="worked" class="text-end">Worked</th><th data-col="ot" class="text-end">Overtime</th>
            </tr></thead>
            <tbody>
            <?php $tot = $totals ?? array_fill_keys(['work_days','present','absent','leave','late','late_min','early','worked_min','ot_min'], 0); ?>
            <?php foreach ($groups as $g): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($g['label']) ?></td>
                    <td class="text-end mono"><?= (int) ($g['work_days'] ?? 0) ?></td>
                    <td class="text-end mono text-success"><?= (int) ($g['present'] ?? 0) ?></td>
                    <td class="text-end mono text-danger"><?= (int) ($g['absent'] ?? 0) ?></td>
                    <td class="text-end mono"><?= (int) ($g['leave'] ?? 0) ?></td>
                    <td class="text-end mono"><?= (int) ($g['late'] ?? 0) ?></td>
                    <td class="text-end mono"><?= mins($g['late_min'] ?? 0) ?></td>
                    <td class="text-end mono"><?= (int) ($g['early'] ?? 0) ?></td>
                    <td class="text-end mono"><?= mins($g['worked_min'] ?? 0) ?></td>
                    <td class="text-end mono text-success"><?= mins($g['ot_min'] ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $groups): ?><tr><td colspan="10" class="text-center text-muted py-4">Nothing found in this range.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($groups): ?>
            <tfoot><tr class="fw-semibold border-top">
                <td>Total (all)</td>
                <td class="text-end mono"><?= $tot['work_days'] ?></td>
                <td class="text-end mono"><?= $tot['present'] ?></td>
                <td class="text-end mono"><?= $tot['absent'] ?></td>
                <td class="text-end mono"><?= $tot['leave'] ?></td>
                <td class="text-end mono"><?= $tot['late'] ?></td>
                <td class="text-end mono"><?= mins($tot['late_min']) ?></td>
                <td class="text-end mono"><?= $tot['early'] ?></td>
                <td class="text-end mono"><?= mins($tot['worked_min']) ?></td>
                <td class="text-end mono"><?= mins($tot['ot_min']) ?></td>
            </tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
