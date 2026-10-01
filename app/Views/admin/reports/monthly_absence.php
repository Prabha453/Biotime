<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $q = http_build_query(['y' => $year, 'm' => $month, 'dept' => $dept]); ?>
<?= page_head($title, $subtitle,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
  . export_dropdown(route_to('reports.monthlyAbsence.export') . '?' . $q)) ?>

<?= view('admin/reports/_monthly_filterbar', ['year' => $year, 'month' => $month, 'dept' => $dept, 'departments' => $departments]) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead><tr>
                <th data-col="emp_id">Employee ID</th><th data-col="name">First Name</th><th data-col="dept">Department</th>
                <th data-col="need" class="text-end">Need Present Days</th><th data-col="present" class="text-end">Present</th><th data-col="absence" class="text-end">Absence</th>
                <th data-col="holiday" class="text-end">Holiday Days</th><th data-col="present_pct" class="text-end">Present %</th><th data-col="absence_pct" class="text-end">Absence %</th>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): $e = $r['emp']; $need = $r['present'] + $r['absentDays']; ?>
                <tr>
                    <td class="mono"><a href="<?= route_to('employee.view', $e['id']) ?>"><?= esc($e['emp_code']) ?></a></td>
                    <td><?= esc(emp_name($e)) ?></td>
                    <td class="text-muted"><?= esc($e['dept_name'] ?? '') ?></td>
                    <td class="text-end"><?= $need ?: '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end fw-semibold text-success"><?= $r['present'] ?: '<span class="text-muted fw-normal">—</span>' ?></td>
                    <td class="text-end fw-semibold text-danger"><?= $r['absentDays'] ?: '<span class="text-muted fw-normal">—</span>' ?></td>
                    <td class="text-end"><?= $r['holidayDays'] ?: '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end"><?= number_format($need ? $r['present'] / $need * 100 : 0, 2) ?>%</td>
                    <td class="text-end"><?= number_format($need ? $r['absentDays'] / $need * 100 : 0, 2) ?>%</td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No employees.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
