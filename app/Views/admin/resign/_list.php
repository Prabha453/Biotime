<?php
/** Resign register list — swapped by tt-crud after every save / reinstate. */
$typeBadge = [1 => 'secondary', 2 => 'danger', 3 => 'warning', 4 => 'info', 5 => 'primary'];
?>
<div class="card">
    <div class="card-header">
        <div class="list-toolbar w-100">
            <span class="lt-title">Resigned employees <span class="lt-count"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></span></span>
            <div class="lt-right">
                <span class="lt-search"><i class="bi bi-search"></i>
                    <input type="text" id="resignSearch" placeholder="Search this list&hellip;" autocomplete="off" aria-label="Search list">
                </span>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="resignTable">
            <thead><tr>
                <th data-col="code">Emp ID</th><th data-col="name">Name</th><th data-col="dept">Department</th><th data-col="designation">Designation</th>
                <th data-col="date">Resign date</th><th data-col="type">Type</th><th data-col="attendance">Attendance</th><th data-col="reason">Reason</th><th class="text-end">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $t = (int) $r['resign_type']; $off = in_array($r['disableatt'], [true, 't', '1', 1], true); ?>
                <tr>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><a href="<?= route_to('employee.view', $r['emp_id']) ?>" class="cell-title"><?= esc($r['name'] ?: '#' . $r['emp_code']) ?></a></td>
                    <td><?= esc($r['dept_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= esc($r['position_name'] ?? '—') ?></td>
                    <td class="mono small"><?= esc($r['resign_date']) ?></td>
                    <td><span class="badge bg-label-<?= $typeBadge[$t] ?? 'secondary' ?>"><?= esc($types[$t] ?? '—') ?></span></td>
                    <td><?= $off
                        ? '<i class="bi bi-dash-circle text-muted" title="Disabled"></i>'
                        : '<i class="bi bi-check-circle-fill text-success" title="Still enabled"></i>' ?></td>
                    <td class="small text-muted"><?= esc($r['reason'] ?? '') ?></td>
                    <td class="text-end" style="white-space:nowrap">
                        <button class="icon-ghost js-edit" title="Edit"
                            data-id="<?= $r['id'] ?>" data-name="<?= esc($r['name'] ?: $r['emp_code'], 'attr') ?>"
                            data-resign_date="<?= esc($r['resign_date'], 'attr') ?>" data-resign_type="<?= $t ?>"
                            data-disableatt="<?= $off ? 1 : 0 ?>" data-reason="<?= esc($r['reason'], 'attr') ?>"
                            data-bs-toggle="modal" data-bs-target="#resignForm"><i class="bi bi-pencil"></i></button>
                        <?= form_open(route_to('resign.reinstate', $r['id']), ['class' => 'd-inline', 'data-crud-delete' => 'Reinstate ' . esc($r['name'] ?: $r['emp_code']) . '? This removes the resignation.']) ?>
                            <button class="icon-ghost" title="Reinstate"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="9"><div class="empty-state"><i class="bi bi-person-check"></i>No resignations recorded.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top small text-muted" id="resignNoMatch" hidden>No records match your search.</div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
