<?php
/**
 * Shared table body for Monthly Check-in&out / Monthly Worked Hrs — both are
 * "Employee | Department | one cell per day | one pay-code summary column
 * per code" grids, differing only in what the day cell shows (punch times
 * vs. worked hours), which the caller supplies via $cellOf.
 * @var array $rows @var int $dim @var array $summaryCodes
 * @var callable $cellOf @var callable $labelOf @var callable $valOf
 * @var ?object $pager
 */
?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 text-nowrap">
            <thead><tr>
                <th data-col="emp_id">Employee ID</th><th data-col="name">First Name</th><th data-col="dept">Department</th>
                <?php for ($i = 1; $i <= $dim; $i++): ?><th data-col="d<?= $i ?>" class="text-center"><?= sprintf('%02d', $i) ?></th><?php endfor; ?>
                <?php foreach ($summaryCodes as $c): ?><th data-col="pc_<?= esc($c, 'attr') ?>" class="text-end"><?= esc($labelOf($c)) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody class="mono-cells">
            <?php foreach ($rows as $r): $e = $r['emp']; ?>
                <tr>
                    <td class="mono"><a href="<?= route_to('employee.view', $e['id']) ?>"><?= esc($e['emp_code']) ?></a></td>
                    <td><?= esc(emp_name($e)) ?></td>
                    <td class="text-muted"><?= esc($e['dept_name'] ?? '') ?></td>
                    <?php for ($i = 1; $i <= $dim; $i++): $v = isset($r['days'][$i]) ? $cellOf($r['days'][$i]) : ''; ?>
                        <td class="text-center small"><?= $v === '' ? '<span class="text-muted">—</span>' : esc($v) ?></td>
                    <?php endfor; ?>
                    <?php foreach ($summaryCodes as $c): ?><td class="text-end small"><?= esc($valOf($r, $c)) ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="<?= 3 + $dim + count($summaryCodes) ?>" class="text-center text-muted py-4">No employees.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
