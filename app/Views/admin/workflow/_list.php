<?php
/** Workflow tab list — swapped by [data-crud] after every save / delete. */
$targets = \App\Controllers\Workflow::TARGETS;
$scope   = \App\Controllers\Workflow::SCOPE;

$delForm = static fn (string $route, string $confirm): string =>
    form_open($route, ['class' => 'd-inline', 'data-crud-delete' => $confirm])
    . '<button class="icon-ghost" title="Delete"><i class="bi bi-trash"></i></button>'
    . form_close();
?>
<div class="card">
    <div class="card-header">
        <div class="list-toolbar w-100">
            <span class="lt-title"><?= $tab === 'roles' ? 'Workflow Roles' : 'Workflow Builder' ?>
                <span class="lt-count"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></span></span>
            <div class="lt-right"><span class="lt-search"><i class="bi bi-search"></i>
                <input type="text" id="wfSearch" placeholder="Search this list&hellip;" autocomplete="off" aria-label="Search list"></span></div>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0" id="wfTable">

    <?php if ($tab === 'roles'): ?>
        <thead><tr><th>Role</th><th>Reports to</th><th>Company</th><th class="text-end">Staff</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><span class="cell-title"><?= esc($r['role_name']) ?></span><div class="cell-sub"><?= esc($r['role_code']) ?><?= $r['description'] ? ' — ' . esc($r['description']) : '' ?></div></td>
                <td class="text-muted"><?= esc($r['parent_name'] ?? '—') ?></td>
                <td class="text-muted"><?= esc($r['company_name'] ?? '—') ?></td>
                <td class="mono text-end"><?= (int) $r['emp_count'] ?></td>
                <td class="text-end">
                    <button class="icon-ghost js-edit" title="Edit"
                        data-id="<?= $r['id'] ?>" data-role_code="<?= esc($r['role_code'], 'attr') ?>" data-role_name="<?= esc($r['role_name'], 'attr') ?>"
                        data-description="<?= esc($r['description'], 'attr') ?>" data-parent_role_id="<?= (int) $r['parent_role_id'] ?>"
                        data-company_id="<?= (int) $r['company_id'] ?>"
                        data-bs-toggle="modal" data-bs-target="#roleForm"><i class="bi bi-pencil"></i></button>
                    <?= $delForm(route_to('workflow.role.delete', $r['id']), 'Delete this role?') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-diagram-2"></i>No workflow roles yet.</div></td></tr><?php endif; ?>
        </tbody>

    <?php else: ?>
        <thead><tr><th>Workflow</th><th>Approves</th><th>Applies to</th><th>Effective</th><th class="text-end">Steps</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><a class="cell-title" href="<?= route_to('workflow.flow', $r['id']) ?>" data-turbo="true"><?= esc($r['workflow_name']) ?></a><div class="cell-sub"><?= esc($r['workflow_code']) ?></div></td>
                <td><?= esc($targets[$r['ctype_key']] ?? ($r['ctype_key'] ?? '—')) ?></td>
                <td class="text-muted"><?= esc($scope[(int) $r['workflow_type']] ?? '—') ?></td>
                <td class="mono text-muted small"><?= esc($r['start_date']) ?> → <?= esc($r['end_date']) ?></td>
                <td class="mono text-end"><?= (int) $r['node_count'] ?></td>
                <td class="text-end">
                    <a class="icon-ghost" title="Steps" href="<?= route_to('workflow.flow', $r['id']) ?>" data-turbo="true"><i class="bi bi-list-ol"></i></a>
                    <button class="icon-ghost js-edit" title="Edit"
                        data-id="<?= $r['id'] ?>" data-workflow_code="<?= esc($r['workflow_code'], 'attr') ?>" data-workflow_name="<?= esc($r['workflow_name'], 'attr') ?>"
                        data-description="<?= esc($r['description'], 'attr') ?>" data-content_type_id="<?= (int) $r['content_type_id'] ?>"
                        data-workflow_type="<?= (int) $r['workflow_type'] ?>" data-department_id="<?= (int) $r['departments_id'] ?>"
                        data-start_date="<?= esc($r['start_date'], 'attr') ?>" data-end_date="<?= esc($r['end_date'], 'attr') ?>"
                        data-company_id="<?= (int) $r['company_id'] ?>"
                        data-bs-toggle="modal" data-bs-target="#flowForm"><i class="bi bi-pencil"></i></button>
                    <?= $delForm(route_to('workflow.flow.delete', $r['id']), 'Delete this workflow?') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-signpost-split"></i>No workflows defined — requests use single-step admin approval.</div></td></tr><?php endif; ?>
        </tbody>
    <?php endif; ?>

    </table></div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
