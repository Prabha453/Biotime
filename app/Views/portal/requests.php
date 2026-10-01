<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php
$statusMap = [0 => ['Pending', 'warning'], 1 => ['Approved', 'success'], 2 => ['Rejected', 'danger'], -1 => ['Revoked', 'secondary']];
$reopen    = session()->getFlashdata('reopen');
?>

<div class="row g-3 mb-4">
    <!-- Leave -->
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-calendar-x me-1"></i>Apply for leave</h2>
            <form method="post" action="<?= route_to('portal.requests.apply') ?>" class="mt-2">
                <?= csrf_field() ?><input type="hidden" name="type" value="leave">
                <div class="mb-2"><label class="form-label small">Leave type</label>
                    <select name="pay_code_id" class="form-select form-select-sm" required>
                        <option value="">Select…</option>
                        <?php foreach ($leaveTypes as $lt): ?><option value="<?= $lt['id'] ?>"><?= esc($lt['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label small">From</label><input type="date" name="start_date" class="form-control form-control-sm" required></div>
                    <div class="col"><label class="form-label small">To</label><input type="date" name="end_date" class="form-control form-control-sm" required></div>
                </div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="half_day" value="1" id="hd"><label class="form-check-label small" for="hd">Half day</label></div>
                <div class="mb-2"><label class="form-label small">Reason</label><textarea name="reason" rows="2" class="form-control form-control-sm"></textarea></div>
                <button class="btn btn-sm w-100 text-white" style="background:#F26522">Submit</button>
            </form>
        </div></div>
    </div>
    <!-- Manual punch -->
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-clock-history me-1"></i>Punch correction</h2>
            <form method="post" action="<?= route_to('portal.requests.apply') ?>" class="mt-2">
                <?= csrf_field() ?><input type="hidden" name="type" value="manual">
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label small">Date</label><input type="date" name="punch_date" class="form-control form-control-sm" required></div>
                    <div class="col"><label class="form-label small">Time</label><input type="time" name="punch_time" class="form-control form-control-sm" required></div>
                </div>
                <div class="mb-2"><label class="form-label small">Direction</label>
                    <select name="punch_state" class="form-select form-select-sm" required>
                        <option value="0">Check In</option><option value="1">Check Out</option>
                        <option value="2">Break Out</option><option value="3">Break In</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small">Reason</label><textarea name="reason" rows="2" class="form-control form-control-sm"></textarea></div>
                <button class="btn btn-sm w-100 text-white" style="background:#F26522">Submit</button>
            </form>
        </div></div>
    </div>
    <!-- Overtime -->
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><i class="bi bi-hourglass-split me-1"></i>Overtime</h2>
            <form method="post" action="<?= route_to('portal.requests.apply') ?>" class="mt-2">
                <?= csrf_field() ?><input type="hidden" name="type" value="overtime">
                <div class="mb-2"><label class="form-label small">Date</label><input type="date" name="ot_date" class="form-control form-control-sm" required></div>
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label small">Start</label><input type="time" name="ot_start" class="form-control form-control-sm" required></div>
                    <div class="col"><label class="form-label small">End</label><input type="time" name="ot_end" class="form-control form-control-sm" required></div>
                </div>
                <div class="mb-2"><label class="form-label small">Type</label>
                    <select name="pay_code_id" class="form-select form-select-sm">
                        <option value="">Default</option>
                        <?php foreach ($otTypes as $ot): ?><option value="<?= $ot['id'] ?>"><?= esc($ot['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small">Reason</label><textarea name="reason" rows="2" class="form-control form-control-sm"></textarea></div>
                <button class="btn btn-sm w-100 text-white" style="background:#F26522">Submit</button>
            </form>
        </div></div>
    </div>
</div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th>Applied</th><th>Type</th><th>Detail</th><th>When</th><th>Reason</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$sl, $sc] = $statusMap[(int) $r['approval_status']] ?? ['?', 'secondary']; ?>
            <tr>
                <td class="mono small"><?= esc($r['applied']) ?></td>
                <td><?= esc(ucfirst($r['kind'])) ?></td>
                <td class="small"><?= esc($r['detail'] ?? '—') ?></td>
                <td class="mono small"><?= esc($r['a']) ?><?= $r['b'] ? ' → ' . esc($r['b']) : '' ?></td>
                <td class="small text-muted"><?= esc($r['reason'] ?? '') ?></td>
                <td>
                    <span class="badge bg-<?= $sc ?>"><?= $sl ?></span>
                    <?php if ((int) $r['approval_status'] !== 0 && $r['approval_remark']): ?>
                        <div class="small text-muted"><?= esc($r['approval_remark']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?php if ((int) $r['approval_status'] === 0): ?>
                        <form method="post" action="<?= route_to('portal.requests.withdraw', (int) $r['id']) ?>" data-confirm="Withdraw this request?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger">Withdraw</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="7" class="text-center text-muted py-4">You have not submitted any requests.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
