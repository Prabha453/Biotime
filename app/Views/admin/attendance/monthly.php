<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$months = [1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$q = http_build_query(['y' => $year, 'm' => $month, 'dept' => $dept]);
$legend = [
    'P' => ['Present', 'ts-P'], 'L' => ['Late', 'ts-L'], 'I' => ['Incomplete', 'ts-I'],
    'A' => ['Absent', 'ts-A'], 'W' => ['Weekend', 'ts-W'], 'H' => ['Holiday', 'ts-H'],
    'V' => ['Leave', 'ts-V'], 'N' => ['Not scheduled', 'ts-N'],
];
?>
<?= page_head($title, $subtitle,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
  . '<a class="btn btn-sm btn-outline-success" href="' . route_to('att.monthly.export') . '?' . $q . '"><i class="bi bi-download me-1"></i>Export CSV</a>') ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label">Month</label>
            <select name="m" class="form-select" data-native>
                <?php foreach ($months as $n => $lbl): ?><option value="<?= $n ?>" <?= $month === $n ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Year</label>
            <input type="number" name="y" value="<?= $year ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-auto">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Generate</button>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap gap-3 mb-2 small">
    <?php foreach ($legend as $c => $l): ?>
        <span><span class="ts-cell d-inline-block <?= $l[1] ?>" style="width:20px;height:16px;line-height:16px;vertical-align:middle"></span> <?= $c ?> = <?= $l[0] ?></span>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table ts-grid mb-0">
            <thead><tr>
                <th data-col="employee" class="text-start">Employee</th>
                <?php for ($i = 1; $i <= $daysInMonth; $i++): $wd = date('D', strtotime("$from +" . ($i - 1) . " day")); ?>
                    <th data-col="d<?= $i ?>" class="<?= in_array($wd, ['Sat','Sun']) ? 'text-danger' : '' ?>"><?= $i ?><br><span class="text-muted" style="font-weight:400"><?= $wd[0] ?></span></th>
                <?php endfor; ?>
                <th data-col="present">P</th><th data-col="absent">A</th><th data-col="late">L</th><th data-col="ot">OT</th>
            </tr></thead>
            <tbody>
            <?php foreach ($grid as $g): $e = $g['emp']; ?>
                <tr>
                    <td class="text-start" style="white-space:nowrap">
                        <a href="<?= route_to('employee.view', $e['id']) ?>?m=<?= $month ?>&y=<?= $year ?>" class="text-decoration-none">
                            <span class="mono text-muted"><?= esc($e['emp_code']) ?></span> <?= esc(emp_name($e)) ?>
                        </a>
                    </td>
                    <?php for ($i = 1; $i <= $daysInMonth; $i++): $code = $g['cells'][$i] ?? 'N'; ?>
                        <td><span class="ts-cell d-inline-flex align-items-center justify-content-center ts-<?= $code ?>"><?= $code === 'N' ? '' : $code ?></span></td>
                    <?php endfor; ?>
                    <td class="fw-bold text-success"><?= $g['tot']['present'] ?></td>
                    <td class="fw-bold text-danger"><?= $g['tot']['absent'] ?></td>
                    <td class="fw-bold text-warning"><?= $g['tot']['late'] ?></td>
                    <td class="fw-bold mono"><?= mins($g['tot']['ot']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $grid): ?><tr><td colspan="<?= $daysInMonth + 5 ?>" class="text-center text-muted py-4">No employees.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
