<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle,
    '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>'
  . $exportUrl) ?>

<?= view('admin/reports/_summary_filterbar', ['from' => $from, 'to' => $to, 'dept' => $dept, 'group' => $group, 'departments' => $departments, 'groups' => $groups]) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 text-nowrap">
            <thead><tr>
                <th data-col="id"><?= esc($idLbl) ?></th>
                <th data-col="name">Name</th>
                <?php if ($level === 'employee'): ?><th data-col="dept"><?= esc($subLbl) ?></th><?php endif; ?>
                <?php if ($showQty): ?><th data-col="qty" class="text-end">Employee Qty.</th><?php endif; ?>
                <?php if ($showTimes): ?>
                    <th data-col="late_times" class="text-end">Late(Times)</th>
                    <th data-col="early_times" class="text-end">Early Leave(Times)</th>
                    <th data-col="absent_times" class="text-end">Absent(Times)</th>
                <?php endif; ?>
                <?php foreach ($summaryCols as $c => $label): ?><th data-col="pc_<?= esc($c, 'attr') ?>" class="text-end"><?= esc($label) ?>(<?= esc($unitOf($c)) ?>)</th><?php endforeach; ?>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><?= esc($idOf($r)) ?></td>
                    <td><?= esc($nameOf($r)) ?></td>
                    <?php if ($level === 'employee'): ?><td class="text-muted"><?= esc($subOf($r)) ?></td><?php endif; ?>
                    <?php if ($showQty): ?><td class="text-end"><?= (int) $r['qty'] ?></td><?php endif; ?>
                    <?php if ($showTimes): ?>
                        <td class="text-end"><?= $r['lateTimes'] ?: '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end"><?= $r['earlyTimes'] ?: '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end"><?= $r['absentTimes'] ?: '<span class="text-muted">—</span>' ?></td>
                    <?php endif; ?>
                    <?php foreach (array_keys($summaryCols) as $c): ?><td class="text-end small"><?= esc($valOf($r, $c)) ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="20" class="text-center text-muted py-4">No data for this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
