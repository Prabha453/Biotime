<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $q = http_build_query(['y' => $year, 'm' => $month, 'dept' => $dept]); ?>
<?= page_head($title, $subtitle,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
  . export_dropdown(route_to('reports.monthlyStatus.export') . '?' . $q)) ?>

<?= view('admin/reports/_monthly_filterbar', ['year' => $year, 'month' => $month, 'dept' => $dept, 'departments' => $departments]) ?>

<div class="d-flex flex-wrap gap-3 mb-2 small">
    <span><?= code_pill('P') ?> Present</span>
    <span><?= code_pill('A') ?> Absent</span>
    <span><?= code_pill('L') ?> Late</span>
    <span><?= code_pill('E') ?> Early Out</span>
    <span><?= code_pill('H') ?> Holiday</span>
    <span><?= code_pill('W') ?> Weekend</span>
    <span><?= code_pill('V') ?> Leave</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 text-nowrap">
            <thead><tr>
                <th data-col="emp_id">Employee ID</th><th data-col="name">First Name</th><th data-col="dept">Department</th>
                <?php for ($i = 1; $i <= $dim; $i++): ?><th data-col="d<?= $i ?>" class="text-center"><?= sprintf('%02d', $i) ?></th><?php endfor; ?>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): $e = $r['emp']; ?>
                <tr>
                    <td class="mono"><a href="<?= route_to('employee.view', $e['id']) ?>"><?= esc($e['emp_code']) ?></a></td>
                    <td><?= esc(emp_name($e)) ?></td>
                    <td class="text-muted"><?= esc($e['dept_name'] ?? '') ?></td>
                    <?php for ($i = 1; $i <= $dim; $i++): $v = isset($r['days'][$i]) ? $codeOf($r['days'][$i]) : ''; ?>
                        <td class="text-center small"><?= code_pill($v) ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="<?= 3 + $dim ?>" class="text-center text-muted py-4">No employees.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
