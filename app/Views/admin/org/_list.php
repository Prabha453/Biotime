<?php
/** Org tab list — the region tt-crud swaps after every save / delete. */
$statusBadge = static fn ($isDefault) => ($isDefault === true || $isDefault === 't')
    ? '<span class="badge bg-label-primary">Default</span>'
    : '<span class="badge bg-label-success">Active</span>';

$delForm = static function (string $route, string $confirm): string {
    return form_open($route, ['class' => 'd-inline', 'data-crud-delete' => $confirm])
        . '<button class="icon-ghost" title="Delete"><i class="bi bi-trash"></i></button>'
        . form_close();
};
$checkAll = '<th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll" aria-label="Select all"></th>';
$rowCheck = static fn ($id) => '<td><input type="checkbox" class="form-check-input grid-check" value="' . (int) $id . '"></td>';
?>
<div class="card">
    <div class="card-header">
        <div class="list-toolbar w-100">
            <span class="lt-title"><?= esc($tabs[$tab]) ?> <span class="lt-count"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></span></span>
            <div class="lt-right">
                <span class="lt-search"><i class="bi bi-search"></i>
                    <input type="text" id="orgSearch" placeholder="Search this list&hellip;" autocomplete="off" aria-label="Search list">
                </span>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="orgTable">

        <?php if ($tab === 'departments'): ?>
            <thead><tr><?= $checkAll ?><th data-col="name">Department</th><th data-col="company">Company</th><th data-col="parent">Parent</th><th data-col="manager">Manager</th><th data-col="staff" class="text-end">Staff</th><th data-col="status">Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?= $rowCheck($r['id']) ?>
                    <td><span class="cell-title"><?= esc($r['dept_name']) ?></span><div class="cell-sub"><?= esc($r['dept_code']) ?></div></td>
                    <td><?= esc($r['company_name']) ?></td>
                    <td class="text-muted"><?= esc($r['parent_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= esc($r['manager_name'] ?? '—') ?></td>
                    <td class="mono text-end"><?= (int) $r['emp_count'] ?></td>
                    <td><?= $statusBadge($r['is_default']) ?></td>
                    <td class="text-end">
                        <button class="icon-ghost js-edit" title="Edit"
                            data-id="<?= $r['id'] ?>" data-dept_code="<?= esc($r['dept_code'], 'attr') ?>" data-dept_name="<?= esc($r['dept_name'], 'attr') ?>"
                            data-company_id="<?= $r['company_id'] ?>" data-parent_dept_id="<?= $r['parent_dept_id'] ?>" data-dept_manager_id="<?= $r['dept_manager_id'] ?>"
                            data-is_default="<?= $r['is_default'] === true || $r['is_default'] === 't' ? 1 : 0 ?>"
                            data-bs-toggle="modal" data-bs-target="#deForm"><i class="bi bi-pencil"></i></button>
                        <?= $delForm(route_to('org.department.delete', $r['id']), 'Delete this department?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-diagram-3"></i>No departments yet.</div></td></tr><?php endif; ?>
            </tbody>

        <?php elseif ($tab === 'positions'): ?>
            <thead><tr><?= $checkAll ?><th data-col="name">Position</th><th data-col="company">Company</th><th data-col="parent">Reports to</th><th data-col="staff" class="text-end">Staff</th><th data-col="status">Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?= $rowCheck($r['id']) ?>
                    <td><span class="cell-title"><?= esc($r['position_name']) ?></span><div class="cell-sub"><?= esc($r['position_code']) ?></div></td>
                    <td><?= esc($r['company_name']) ?></td>
                    <td class="text-muted"><?= esc($r['parent_name'] ?? '—') ?></td>
                    <td class="mono text-end"><?= (int) $r['emp_count'] ?></td>
                    <td><?= $statusBadge($r['is_default']) ?></td>
                    <td class="text-end">
                        <button class="icon-ghost js-edit" title="Edit"
                            data-id="<?= $r['id'] ?>" data-position_code="<?= esc($r['position_code'], 'attr') ?>" data-position_name="<?= esc($r['position_name'], 'attr') ?>"
                            data-company_id="<?= $r['company_id'] ?>" data-parent_position_id="<?= $r['parent_position_id'] ?>"
                            data-is_default="<?= $r['is_default'] === true || $r['is_default'] === 't' ? 1 : 0 ?>"
                            data-bs-toggle="modal" data-bs-target="#poForm"><i class="bi bi-pencil"></i></button>
                        <?= $delForm(route_to('org.position.delete', $r['id']), 'Delete this position?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-briefcase"></i>No positions yet.</div></td></tr><?php endif; ?>
            </tbody>

        <?php elseif ($tab === 'areas'): ?>
            <thead><tr><?= $checkAll ?><th data-col="name">Area</th><th data-col="company">Company</th><th data-col="parent">Parent</th><th data-col="staff" class="text-end">Staff</th><th data-col="devices" class="text-end">Devices</th><th data-col="status">Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?= $rowCheck($r['id']) ?>
                    <td><span class="cell-title"><?= esc($r['area_name']) ?></span><div class="cell-sub"><?= esc($r['area_code']) ?></div></td>
                    <td><?= esc($r['company_name']) ?></td>
                    <td class="text-muted"><?= esc($r['parent_name'] ?? '—') ?></td>
                    <td class="mono text-end"><?= (int) $r['emp_count'] ?></td>
                    <td class="mono text-end"><?= (int) $r['dev_count'] ?></td>
                    <td><?= $statusBadge($r['is_default']) ?></td>
                    <td class="text-end">
                        <button class="icon-ghost js-edit" title="Edit"
                            data-id="<?= $r['id'] ?>" data-area_code="<?= esc($r['area_code'], 'attr') ?>" data-area_name="<?= esc($r['area_name'], 'attr') ?>"
                            data-company_id="<?= $r['company_id'] ?>" data-parent_area_id="<?= $r['parent_area_id'] ?>"
                            data-is_default="<?= $r['is_default'] === true || $r['is_default'] === 't' ? 1 : 0 ?>"
                            data-bs-toggle="modal" data-bs-target="#arForm"><i class="bi bi-pencil"></i></button>
                        <?= $delForm(route_to('org.area.delete', $r['id']), 'Delete this area?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-geo"></i>No areas yet.</div></td></tr><?php endif; ?>
            </tbody>

        <?php else: /* companies */ ?>
            <thead><tr><?= $checkAll ?><th data-col="name">Company</th><th data-col="contact">Contact</th><th data-col="depts" class="text-end">Depts</th><th data-col="staff" class="text-end">Staff</th><th data-col="status">Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?= $rowCheck($r['id']) ?>
                    <td><span class="cell-title"><?= esc($r['company_name']) ?></span><div class="cell-sub"><?= esc($r['company_code']) ?></div></td>
                    <td class="text-muted small"><?= esc($r['email'] ?: $r['phone'] ?: '—') ?></td>
                    <td class="mono text-end"><?= (int) $r['dept_count'] ?></td>
                    <td class="mono text-end"><?= (int) $r['emp_count'] ?></td>
                    <td><?= $statusBadge($r['is_default']) ?></td>
                    <td class="text-end">
                        <button class="icon-ghost js-edit" title="Edit"
                            data-id="<?= $r['id'] ?>" data-company_code="<?= esc($r['company_code'], 'attr') ?>" data-company_name="<?= esc($r['company_name'], 'attr') ?>"
                            data-email="<?= esc($r['email'], 'attr') ?>" data-phone="<?= esc($r['phone'], 'attr') ?>" data-website="<?= esc($r['website'], 'attr') ?>"
                            data-address="<?= esc($r['address'], 'attr') ?>" data-is_default="<?= $r['is_default'] === true || $r['is_default'] === 't' ? 1 : 0 ?>"
                            data-bs-toggle="modal" data-bs-target="#coForm"><i class="bi bi-pencil"></i></button>
                        <?= $delForm(route_to('org.company.delete', $r['id']), 'Delete this company?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-building"></i>No companies yet.</div></td></tr><?php endif; ?>
            </tbody>
        <?php endif; ?>

        </table>
    </div>
    <div class="p-3 border-top small text-muted" id="orgNoMatch" hidden>No records match your search.</div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
