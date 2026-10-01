<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$qs   = ['from' => $from, 'to' => $to, 'dept' => $dept, 'company' => $company, 'q' => $q];
$actions = '<div class="d-flex gap-2">'
    . '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
    . export_dropdown(route_to('reports.timeCard.export') . '?' . http_build_query($qs))
    . '</div>';

/** @var array<string,array{0:string,1:string,2:bool}> $columns */
$cellCls = static function (string $key, array $col): string {
    $c = $col[1] === 'end' ? 'text-end' : ($col[1] === 'center' ? 'text-center' : '');
    if (str_starts_with($key, 'brk') && str_ends_with($key, '_out')) {
        $c .= ' border-start';
    }
    return trim($c);
};
?>
<?= page_head($title, $subtitle ?? '', $actions) ?>

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Company</label>
            <select name="company" class="form-select" data-native>
                <option value="">All companies</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) $company === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int) $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Employee</label><input type="text" name="q" value="<?= esc($q) ?>" placeholder="code or name" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Run</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tcTable">
            <thead><tr>
                <?php foreach ($columns as $key => $col): ?>
                    <th data-col="<?= esc($key, 'attr') ?>" class="<?= $cellCls($key, $col) ?>"<?= $col[2] ? '' : ' hidden data-col-default-hidden' ?>><?= esc($col[0]) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($columns as $key => $col): $v = (string) ($r[$key] ?? ''); ?>
                        <td class="<?= $cellCls($key, $col) ?>"<?= $col[2] ? '' : ' hidden' ?>><?php /* col visibility mirrored from th by tt-grid.js */ ?>
                            <?php if ($key === 'status'): ?>
                                <?= $v === '' ? '' : status_pill($v, $r['_status_class'] ?? 'muted') ?>
                            <?php elseif ($key === 'emp_id'): ?>
                                <a href="<?= route_to('employee.view', $r['_emp_id']) ?>" class="text-decoration-none fw-semibold"><?= esc($v) ?></a>
                            <?php elseif (in_array($key, ['check_in', 'check_out', 'duty', 'timetable', 'break_dur'], true)): ?>
                                <span class="text-muted"><?= $v === '' ? '—' : esc($v) ?></span>
                            <?php elseif ($key === 'break_taken'): ?>
                                <span class="<?= $v === '' ? 'text-muted' : 'text-warning fw-semibold' ?>"><?= $v === '' ? '—' : esc($v) ?></span>
                            <?php elseif (in_array($key, ['worked', 'total'], true)): ?>
                                <span class="fw-semibold"><?= $v === '' ? '—' : esc($v) ?></span>
                            <?php elseif (str_starts_with($key, 'brk') || str_starts_with($key, 'pc_') || in_array($key, ['clock_in', 'clock_out', 'total_ot', 'total_leaves', 'unscheduled', 'remaining', 'work_day', 'attendance'], true)): ?>
                                <?= $v === '' ? '<span class="text-muted">—</span>' : esc($v) ?>
                            <?php else: ?>
                                <?= esc($v) ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="<?= count($columns) ?>" class="text-center text-muted py-4">No worked days in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
    <div class="px-3 py-2 border-top small text-muted">
        Aligned to ZKBioTime's engine: <strong>Duty Duration</strong> = timetable span − unpaid break ·
        <strong>Total Hrs</strong> = last-out − first-in punch ·
        <strong>Regular</strong> = Duty Duration − Late/Early/Absence − Leave ·
        <strong>Remaining</strong> = Duty Duration − Leave − Regular.
        <strong>Worked Hrs</strong> = Regular + Total OT − <em>break taken beyond the timetable's break allowance</em>
        (a normal break costs nothing; only excess break is docked — business rule, not ZKBioTime).
        Use <em>Columns</em> to show/hide.
    </div>
</div>
<?= $this->endSection() ?>
