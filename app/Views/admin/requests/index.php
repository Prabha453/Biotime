<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $st = [0 => ['Pending', 'warning'], 1 => ['Approved', 'success'], 2 => ['Rejected', 'danger'], -1 => ['Revoked', 'muted']]; ?>

<?= page_head($title, $subtitle . ($pending ? " · {$pending} pending" : ''), modal_btn('req_' . $tab, 'New ' . strtolower($tabs[$tab]) . ' request', 'plus-lg')) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('requests.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Employee</th><th><?= $tab === 'schedule' ? 'New timetable' : 'Detail' ?></th>
                <th><?= $tab === 'manual' ? 'When' : 'From' ?></th>
                <?php if (! in_array($tab, ['manual', 'schedule'], true)): ?><th>To</th><?php endif; ?>
                <?php if (! in_array($tab, ['manual', 'schedule'], true)): ?><th><?= $tab === 'overtime' ? 'Hours' : 'Days' ?></th><?php endif; ?>
                <th>Status</th><th>Reason</th><th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $s = $st[(int) $r['approval_status']] ?? ['?', 'muted']; ?>
                <tr>
                    <td><span class="mono text-muted"><?= esc($r['emp_code'] ?? '') ?></span> <?= esc($r['name'] ?? '') ?></td>
                    <td><span class="pill pill-info"><?= esc($r['detail'] ?? '—') ?></span></td>
                    <td class="mono small"><?= esc($r['a'] ?? '') ?></td>
                    <?php if (! in_array($tab, ['manual', 'schedule'], true)): ?>
                        <td class="mono small"><?= esc($r['b'] ?? '') ?></td>
                        <td class="mono"><?= $r['qty'] !== null ? esc(rtrim(rtrim((string) $r['qty'], '0'), '.')) : '' ?></td>
                    <?php endif; ?>
                    <td><span class="pill pill-<?= $s[1] ?>"><?= $s[0] ?></span>
                        <?php if (! empty($r['approval_remark'])): ?><div class="small text-muted"><?= esc($r['approval_remark']) ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= esc($r['reason'] ?? '') ?></td>
                    <td class="text-end" style="white-space:nowrap">
                        <?php if ((int) $r['approval_status'] === 0): ?>
                            <?= form_open(route_to('request.decide', $r['id']), ['class' => 'd-inline']) ?>
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                            <?= form_close() ?>
                            <?= form_open(route_to('request.decide', $r['id']), ['class' => 'd-inline']) ?>
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                            <?= form_close() ?>
                        <?php elseif ((int) $r['approval_status'] === 1): ?>
                            <?= form_open(route_to('request.decide', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Revoke this?']) ?>
                                <input type="hidden" name="action" value="revoke">
                                <button class="btn btn-sm btn-outline-warning" title="Revoke"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <?= form_close() ?>
                        <?php endif; ?>
                        <?= form_open(route_to('request.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this record?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Delete"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-inbox"></i>No <?= strtolower($tabs[$tab]) ?> requests yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<?php
$empSelect = function () use ($employees) {
    echo '<select name="employee_id" class="form-select" required><option value="">Select…</option>';
    foreach ($employees as $e) {
        echo '<option value="' . $e['id'] . '">' . esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) . '</option>';
    }
    echo '</select>';
};
$autoApprove = '<div class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve" value="1" id="aa_%s" checked><label class="form-check-label small" for="aa_%s">Approve immediately</label></div>';
$reasonBox   = '<div class="mb-2"><label class="form-label tiny">Reason</label><textarea name="reason" class="form-control" rows="2"></textarea></div>';
?>

<!-- Leave -->
<div class="modal fade" id="req_leave" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('request.apply')) ?>
    <input type="hidden" name="type" value="leave">
    <div class="modal-header"><h5 class="modal-title">New leave request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label><?php $empSelect(); ?></div>
        <div class="mb-2"><label class="form-label tiny">Leave type *</label>
            <select name="pay_code_id" class="form-select" required>
                <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?= $lt['id'] ?>"><?= esc($lt['name'] . ' (' . $lt['code'] . ')') ?><?= in_array($lt['is_paid'], [true, 't'], true) ? '' : ' — unpaid' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">From *</label><input type="date" name="start_date" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">To *</label><input type="date" name="end_date" class="form-control" required></div>
        </div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="half_day" value="1" id="lvHalf"><label class="form-check-label small" for="lvHalf">Half day (0.5)</label></div>
        <?= $reasonBox ?>
        <?= sprintf($autoApprove, 'leave', 'leave') ?>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- Manual punch -->
<div class="modal fade" id="req_manual" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('request.apply')) ?>
    <input type="hidden" name="type" value="manual">
    <div class="modal-header"><h5 class="modal-title">Manual punch correction</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label><?php $empSelect(); ?></div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Date *</label><input type="date" name="punch_date" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">Time *</label><input type="time" name="punch_time" class="form-control" required></div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Type *</label>
                <select name="punch_state" class="form-select" required>
                    <option value="0">Check In</option><option value="1">Check Out</option>
                    <option value="2">Break Out</option><option value="3">Break In</option>
                </select>
            </div>
            <?php if (! empty($workCodes)): ?>
            <div class="col"><label class="form-label tiny">Work code</label>
                <select name="work_code_id" class="form-select">
                    <option value="">— none —</option>
                    <?php foreach ($workCodes as $w): ?><option value="<?= $w['id'] ?>"><?= esc($w['code'] . ' · ' . $w['alias']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <?= $reasonBox ?>
        <?= sprintf($autoApprove, 'manual', 'manual') ?>
        <p class="ext-note mt-2 mb-0">Approved corrections are folded into the register — no punch row is created.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- Overtime -->
<div class="modal fade" id="req_overtime" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('request.apply')) ?>
    <input type="hidden" name="type" value="overtime">
    <div class="modal-header"><h5 class="modal-title">Overtime request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label><?php $empSelect(); ?></div>
        <div class="mb-2"><label class="form-label tiny">Date *</label><input type="date" name="ot_date" class="form-control" required></div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Start *</label><input type="time" name="ot_start" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">End *</label><input type="time" name="ot_end" class="form-control" required></div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Pay code</label>
            <select name="pay_code_id" class="form-select">
                <option value="">Default</option>
                <?php foreach ($otTypes as $o): ?><option value="<?= $o['id'] ?>"><?= esc($o['name'] . ' (' . $o['code'] . ')') ?></option><?php endforeach; ?>
            </select>
        </div>
        <?= $reasonBox ?>
        <?= sprintf($autoApprove, 'overtime', 'overtime') ?>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- Training -->
<div class="modal fade" id="req_training" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('request.apply')) ?>
    <input type="hidden" name="type" value="training">
    <div class="modal-header"><h5 class="modal-title">Training request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label><?php $empSelect(); ?></div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">From *</label><input type="date" name="start_date" class="form-control" required></div>
            <div class="col"><label class="form-label tiny">To *</label><input type="date" name="end_date" class="form-control" required></div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Pay code</label>
            <select name="pay_code_id" class="form-select">
                <option value="">Paid (default)</option>
                <?php foreach ($leaveTypes as $lt): ?><option value="<?= $lt['id'] ?>"><?= esc($lt['name'] . ' (' . $lt['code'] . ')') ?></option><?php endforeach; ?>
            </select>
        </div>
        <?= $reasonBox ?>
        <?= sprintf($autoApprove, 'training', 'training') ?>
        <p class="ext-note mt-2 mb-0"><i class="bi bi-info-circle"></i> Approved training days count as attended &amp; paid on the register and payslip.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- Schedule change -->
<div class="modal fade" id="req_schedule" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('request.apply')) ?>
    <input type="hidden" name="type" value="schedule">
    <div class="modal-header"><h5 class="modal-title">Schedule-change request</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label><?php $empSelect(); ?></div>
        <div class="mb-2"><label class="form-label tiny">Date *</label><input type="date" name="sc_date" class="form-control" required></div>
        <div class="mb-2"><label class="form-label tiny">Replacement timetable *</label>
            <select name="timeinterval_id" class="form-select" required>
                <?php foreach ($timetables as $ti): ?><option value="<?= $ti['id'] ?>"><?= esc($ti['alias']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?= $reasonBox ?>
        <?= sprintf($autoApprove, 'schedule', 'schedule') ?>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Submit</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
