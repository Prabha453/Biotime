<?php
/**
 * Employee results — the swappable region (card + pager). Rendered on first
 * paint by index.php and returned on its own for every AJAX filter / page /
 * sort request (see Employees::index()).
 *
 * @var array                       $rows
 * @var \CodeIgniter\Pager\Pager     $pager
 */
$empTypes = [1 => 'Permanent', 2 => 'Temporary', 3 => 'Probation'];
$statusOf = static fn (array $e) => ! empty($e['resign_date'])
    ? ['Resigned', 'danger'] : ['Active', 'success'];
$isOn = static fn (array $e) => in_array($e['is_active'] ?? true, [true, 't', '1', 1], true);
$total = $pager->getTotal();
?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <span>Employee list</span>
        <span class="text-muted small fw-normal"><?= number_format(count($rows)) ?> of <?= number_format($total) ?></span>
        <div class="ms-auto">
            <div class="view-toggle" id="empView">
                <button type="button" data-view="grid" title="Grid view"><i class="bi bi-grid-3x3-gap"></i></button>
                <button type="button" data-view="list" title="List view"><i class="bi bi-list-ul"></i></button>
            </div>
        </div>
    </div>

    <!-- Grid view -->
    <div class="card-body" data-view-pane="grid" hidden>
        <?php if (! $rows): ?>
            <div class="empty-state"><i class="bi bi-people"></i>No employees match these filters.</div>
        <?php else: ?>
        <div class="emp-grid">
            <?php foreach ($rows as $e): $view = route_to('employee.view', $e['id']); [$sLbl, $sCls] = $statusOf($e); ?>
                <div class="emp-card">
                    <div class="emp-card-top">
                        <input type="checkbox" class="form-check-input emp-check" value="<?= (int) $e['id'] ?>" aria-label="Select <?= esc($e['emp_code'], 'attr') ?>">
                        <span class="badge bg-label-<?= $sCls ?>"><?= $sLbl ?></span>
                        <div class="dropdown">
                            <button class="icon-ghost" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="<?= $view ?>" data-turbo="true"><i class="bi bi-eye me-2"></i>View / Timesheet</a></li>
                                <li><a class="dropdown-item" href="<?= route_to('employee.edit', $e['id']) ?>" data-turbo="true"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                            </ul>
                        </div>
                    </div>
                    <a href="<?= $view ?>" data-turbo="true"><?= emp_avatar($e, 'lg') ?></a>
                    <a href="<?= $view ?>" class="emp-card-name" data-turbo="true"><?= esc(emp_name($e)) ?></a>
                    <div class="emp-card-role"><?= esc($e['position_name'] ?? $e['dept_name'] ?? '—') ?></div>
                    <div class="emp-card-meta">
                        <div><span>Emp ID</span><strong><?= esc($e['emp_code']) ?></strong></div>
                        <div><span>Department</span><strong><?= esc($e['dept_name'] ?? '—') ?></strong></div>
                        <div><span>Phone</span><strong><?= esc($e['mobile'] ?: '—') ?></strong></div>
                        <div><span>Joined</span><strong><?= esc($e['hire_date'] ?? '—') ?></strong></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- List view — ZKBioTime employee fields -->
    <div class="table-responsive" data-view-pane="list">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th style="width:2.4rem"><input type="checkbox" class="form-check-input" id="empCheckAll" aria-label="Select all"></th>
                <th data-col="code">Emp ID</th><th data-col="name">Name</th><th data-col="email">Email</th><th data-col="phone">Phone</th><th data-col="dept">Department</th>
                <th data-col="designation">Designation</th><th data-col="area">Area</th><th data-col="employment">Employment</th><th data-col="hired">Hired</th><th data-col="attendance">Attendance</th>
                <th data-col="status">Status</th><th class="text-end">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $e): [$sLbl, $sCls] = $statusOf($e); $view = route_to('employee.view', $e['id']); ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input emp-check" value="<?= (int) $e['id'] ?>" aria-label="Select <?= esc($e['emp_code'], 'attr') ?>"></td>
                    <td class="mono"><?= esc($e['emp_code']) ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= emp_avatar($e) ?>
                            <div>
                                <a href="<?= $view ?>" class="cell-title" data-turbo="true"><?= esc(emp_name($e)) ?></a>
                                <div class="text-muted" style="font-size:11.5px"><?= esc($e['position_name'] ?? $e['dept_name'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="small"><?= $e['email']
                        ? '<a href="mailto:' . esc($e['email'], 'attr') . '">' . esc($e['email']) . '</a>'
                        : '<span class="text-muted">—</span>' ?></td>
                    <td class="mono small"><?= esc($e['mobile'] ?: '—') ?></td>
                    <td><?= esc($e['dept_name'] ?? '—') ?></td>
                    <td><?= esc($e['position_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= esc($e['area_name'] ?? '—') ?></td>
                    <td class="text-muted small"><?= esc($empTypes[(int) $e['emp_type']] ?? '—') ?></td>
                    <td class="mono small"><?= esc($e['hire_date'] ?? '—') ?></td>
                    <td><?= $isOn($e)
                        ? '<i class="bi bi-check-circle-fill text-success" title="Enabled"></i>'
                        : '<i class="bi bi-dash-circle text-muted" title="Disabled"></i>' ?></td>
                    <td><span class="badge bg-label-<?= $sCls ?>"><?= $sLbl ?></span></td>
                    <td class="text-end" style="white-space:nowrap">
                        <a href="<?= $view ?>" class="icon-ghost" title="View" data-turbo="true"><i class="bi bi-eye"></i></a>
                        <a href="<?= route_to('employee.edit', $e['id']) ?>" class="icon-ghost" title="Edit" data-turbo="true"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="13"><div class="empty-state"><i class="bi bi-people"></i>No employees match these filters.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pager_bar($pager) ?>
