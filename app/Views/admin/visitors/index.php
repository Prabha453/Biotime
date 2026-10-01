<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
if (! function_exists('vis_name')) {
    function vis_name(array $v): string
    {
        $n = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
        return $n !== '' ? $n : '—';
    }
}
?>
<?= page_head($title, $subtitle, modal_btn('visNew', 'Check in visitor') . ' ' . modal_btn('resNew', 'New reservation', 'calendar-plus', 'btn-outline-primary')) ?>

<div class="kpi-row">
    <div class="kpi"><div class="k-label">On site now</div><div class="k-value"><?= count($onsite) ?></div><div class="k-sub">not yet checked out</div></div>
    <div class="kpi"><div class="k-label">Expected guests</div><div class="k-value"><?= array_sum(array_column($onsite, 'visit_quantity')) ?></div><div class="k-sub">total headcount</div></div>
</div>

<div class="card mb-3">
    <div class="card-header">Currently on site</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Visitor</th><th>Company</th><th>ID / passport</th><th>Host</th><th>Reason</th><th>Checked in</th><th>Expected out</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($onsite as $v): ?>
                <tr>
                    <td class="fw-semibold"><?= esc(vis_name($v)) ?><?php if ($v['visit_quantity'] > 1): ?> <span class="pill pill-muted">+<?= (int) $v['visit_quantity'] - 1 ?></span><?php endif; ?>
                        <?php if ($v['mobile']): ?><div class="small text-muted mono"><?= esc($v['mobile']) ?></div><?php endif; ?>
                    </td>
                    <td><?= esc($v['company'] ?? '—') ?></td>
                    <td class="mono small"><?= esc($v['cert_no']) ?></td>
                    <td><?= esc($v['host_name'] ?: $v['host_first'] ?: '—') ?> <span class="mono text-muted"><?= esc($v['host_code']) ?></span></td>
                    <td class="small"><?= esc($v['visit_reason'] ?? '—') ?></td>
                    <td class="mono small"><?= esc(date('d M H:i', strtotime($v['start_time']))) ?></td>
                    <td class="mono small text-muted"><?= esc(date('H:i', strtotime($v['end_time']))) ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('visitor.checkout', $v['id']), ['class' => 'd-inline']) ?>
                            <button class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-right me-1"></i>Check out</button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $onsite): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-person-badge"></i>No visitors on site. Use “Check in visitor” to register one.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (! empty($reservations)): ?>
<div class="card mb-3">
    <div class="card-header">Upcoming reservations <span class="text-muted small">visitor_reservation</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Visitor</th><th>Company</th><th>Host</th><th>Visit date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php $rst = [0 => ['Pending', 'warning'], 1 => ['Approved', 'success'], 2 => ['Rejected', 'danger']]; ?>
            <?php foreach ($reservations as $r): $s = $rst[(int) $r['approval_status']] ?? ['?', 'muted']; ?>
                <tr>
                    <td class="fw-semibold"><?= esc(trim($r['vis_first_name'] . ' ' . ($r['vis_last_name'] ?? ''))) ?>
                        <?php if ($r['visit_quantity'] > 1): ?><span class="pill pill-muted">+<?= (int) $r['visit_quantity'] - 1 ?></span><?php endif; ?>
                        <div class="small text-muted mono"><?= esc($r['cert_no']) ?></div>
                    </td>
                    <td><?= esc($r['company'] ?? '—') ?></td>
                    <td><?= esc($r['host_name'] ?: '—') ?> <span class="mono text-muted"><?= esc($r['host_code'] ?? '') ?></span></td>
                    <td class="mono small"><?= esc(date('d M H:i', strtotime($r['visit_date']))) ?></td>
                    <td><span class="pill pill-<?= $s[1] ?>"><?= $s[0] ?></span></td>
                    <td class="text-end" style="white-space:nowrap">
                        <?php if ((int) $r['approval_status'] === 0): ?>
                            <?= form_open(route_to('visitor.reservation.decide', $r['id']), ['class' => 'd-inline']) ?>
                                <input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></button>
                            <?= form_close() ?>
                            <?= form_open(route_to('visitor.reservation.decide', $r['id']), ['class' => 'd-inline']) ?>
                                <input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                            <?= form_close() ?>
                        <?php elseif ((int) $r['approval_status'] === 1): ?>
                            <?= form_open(route_to('visitor.reservation.convert', $r['id']), ['class' => 'd-inline']) ?>
                                <button class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Check in</button>
                            <?= form_close() ?>
                        <?php endif; ?>
                        <?= form_open(route_to('visitor.reservation.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this reservation?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Visit history</span>
        <form method="get" class="d-flex gap-2">
            <input name="q" value="<?= esc($q) ?>" class="form-control form-control-sm" placeholder="name or company" style="width:200px">
            <button class="btn btn-sm btn-outline-secondary">Search</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Visitor</th><th>Company</th><th>Host</th><th>In</th><th>Out</th><th>Duration</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($history as $v): $mins = (strtotime($v['exit_time']) - strtotime($v['start_time'])) / 60; ?>
                <tr>
                    <td class="fw-semibold"><?= esc(vis_name($v)) ?></td>
                    <td><?= esc($v['company'] ?? '—') ?></td>
                    <td><?= esc($v['host_name'] ?: $v['host_first'] ?: '—') ?></td>
                    <td class="mono small"><?= esc(date('d M H:i', strtotime($v['start_time']))) ?></td>
                    <td class="mono small"><?= esc(date('d M H:i', strtotime($v['exit_time']))) ?></td>
                    <td class="mono small"><?= mins((int) $mins) ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('visitor.delete', $v['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this visit record?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $history): ?><tr><td colspan="7" class="text-center text-muted py-4">No past visits<?= $q !== '' ? ' match “' . esc($q) . '”' : '' ?>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="visNew" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('visitor.register')) ?>
    <div class="modal-header"><h5 class="modal-title">Check in visitor</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label tiny">First name *</label><input name="first_name" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Last name</label><input name="last_name" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">ID / passport *</label><input name="cert_no" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Company</label><input name="company" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">Mobile</label><input name="mobile" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">Gender</label>
                <select name="gender" class="form-select"><option value="">—</option><option value="M">Male</option><option value="F">Female</option></select>
            </div>
            <div class="col-md-4"><label class="form-label tiny">Party size</label><input type="number" min="1" name="visit_quantity" class="form-control" value="1"></div>
            <div class="col-md-4"><label class="form-label tiny">Expected hours</label><input type="number" min="1" max="24" name="expected_hours" class="form-control" value="8"></div>
            <div class="col-md-4"><label class="form-label tiny">Host employee *</label>
                <select name="visited_id" class="form-select" required><option value="">Select…</option>
                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label tiny">Visiting department</label>
                <select name="visit_department_id" class="form-select"><option value="">—</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label tiny">Reason</label>
                <select name="visit_reason_id" class="form-select"><option value="">—</option>
                    <?php foreach ($reasons as $r): ?><option value="<?= $r['id'] ?>"><?= esc($r['visit_reason']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="form-label tiny">Carrying goods</label><input name="carrying" class="form-control" placeholder="laptop, tools…"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Check in</button></div>
    <?= form_close() ?>
</div></div></div>

<div class="modal fade" id="resNew" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('visitor.reserve')) ?>
    <div class="modal-header"><h5 class="modal-title">New visitor reservation</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label tiny">First name *</label><input name="vis_first_name" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Last name</label><input name="vis_last_name" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">ID / passport *</label><input name="cert_no" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Company</label><input name="company" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">Email</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">Party size</label><input type="number" min="1" name="visit_quantity" class="form-control" value="1"></div>
            <div class="col-md-4"><label class="form-label tiny">Visit date *</label><input type="date" name="visit_date" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Host employee *</label>
                <select name="host_id" class="form-select" required><option value="">Select…</option>
                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc(($e['name'] ?: $e['first_name'] ?: '#') . ' · ' . $e['emp_code']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label tiny">Department</label>
                <select name="visit_department_id" class="form-select"><option value="">—</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label tiny">Reason</label>
                <select name="visit_reason_id" class="form-select"><option value="">—</option>
                    <?php foreach ($reasons as $r): ?><option value="<?= $r['id'] ?>"><?= esc($r['visit_reason']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="form-label tiny">Notes</label><input name="apply_reason" class="form-control"></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve" value="1" id="resAuto" checked><label class="form-check-label small" for="resAuto">Approve immediately</label></div></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Reserve</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
