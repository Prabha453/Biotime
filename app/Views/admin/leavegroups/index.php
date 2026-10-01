<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('lgNew', 'New group', 'plus-lg')) ?>

<?php if (! $groups): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="bi bi-collection"></i>
        No leave groups yet. Create one, then add a per-type annual entitlement.
    </div></div></div>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><strong><?= esc($g['name']) ?></strong> <span class="mono text-muted small"><?= esc($g['code']) ?></span>
            <span class="text-muted small">· <?= (int) $g['members'] ?> employees · <?= (int) $g['types'] ?> types</span>
        </div>
        <div class="d-flex gap-1">
            <?= modal_btn('lgd' . $g['id'], 'Add entitlement', 'plus', 'btn-outline-primary btn-sm') ?>
            <?= form_open(route_to('leavegroup.delete', $g['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this group?', 'data-confirm-danger' => '']) ?>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
            <?= form_close() ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Leave type</th><th>Mode</th><th>Annual days</th><th>Min</th><th>Carry over</th><th>Exceed limit</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($g['details'] as $d): ?>
                <tr>
                    <td><span class="pill pill-info"><?= esc($d['pay_code'] ?? '?') ?></span></td>
                    <td class="small text-muted"><?= esc($modes[(int) $d['leave_type']] ?? '—') ?></td>
                    <td class="mono"><?= (int) $d['allow_leave_day'] ?></td>
                    <td class="mono"><?= esc(rtrim(rtrim((string) $d['min_leave_day'], '0'), '.')) ?></td>
                    <td><?= (int) $d['allow_balance'] ? 'Yes' . ($d['max_balance'] ? ' (max ' . (int) $d['max_balance'] . ')' : '') : '—' ?></td>
                    <td><?= (int) $d['allow_exceed_limit'] ? 'Allowed' : 'Blocked' ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('leavegroup.detail.delete', $d['id']), ['class' => 'd-inline']) ?>
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $g['details']): ?><tr><td colspan="7" class="text-muted small">No entitlements — leave for this group is uncapped.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="lgd<?= $g['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <?= form_open(route_to('leavegroup.detail.save')) ?>
        <input type="hidden" name="leave_group_id" value="<?= $g['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Entitlement · <?= esc($g['name']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-2 mb-2">
                <div class="col"><label class="form-label tiny">Leave type *</label>
                    <select name="pay_code_id" class="form-select" required>
                        <?php foreach ($leaveTypes as $lt): ?><option value="<?= $lt['id'] ?>"><?= esc($lt['name'] . ' (' . $lt['code'] . ')') ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col"><label class="form-label tiny">Calculation mode *</label>
                    <select name="leave_type" class="form-select" required>
                        <?php foreach ($modes as $mk => $mv): ?><option value="<?= $mk ?>" <?= $mk === 2 ? 'selected' : '' ?>><?= esc($mv) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col"><label class="form-label tiny">Annual days *</label><input type="number" min="0" name="allow_leave_day" class="form-control" required></div>
                <div class="col"><label class="form-label tiny">Min per request</label><input type="number" step="0.5" min="0" name="min_leave_day" class="form-control" value="0.5"></div>
                <div class="col"><label class="form-label tiny">Max carry over</label><input type="number" min="0" name="max_balance" class="form-control"></div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col"><label class="form-label tiny">Entitlement (days)</label><input type="number" min="0" name="leave_entitlement" class="form-control" placeholder="e.g. 21"></div>
                <div class="col"><label class="form-label tiny">Renews every (years)</label><input type="number" min="1" name="leave_interval" class="form-control" value="1"></div>
                <div class="col"><label class="form-label tiny">Cycle start (MM-DD)</label><input name="start_day" class="form-control" value="01-01" pattern="\d{2}-\d{2}" placeholder="01-01"></div>
            </div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_balance" value="1" id="ab<?= $g['id'] ?>"><label class="form-check-label small" for="ab<?= $g['id'] ?>">Unused days carry to next year</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_exceed_limit" value="1" id="ae<?= $g['id'] ?>"><label class="form-check-label small" for="ae<?= $g['id'] ?>">Allow requests beyond the balance</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="deduct_holiday_day" value="1" id="dh<?= $g['id'] ?>"><label class="form-check-label small" for="dh<?= $g['id'] ?>">Count holidays that fall within a leave request</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="set_hire_day" value="1" id="sh<?= $g['id'] ?>"><label class="form-check-label small" for="sh<?= $g['id'] ?>">Cycle anchored to hire date, not the date above</label></div>
            <p class="form-hint mt-2">"Based On Service" (seniority-tiered entitlement) isn't editable here yet — set it up in the real ZKBioTime admin if you need it; this form won't touch that data.</p>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
        <?= form_close() ?>
    </div></div></div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="lgNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('leavegroup.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New leave group</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2">
            <div class="col-4"><label class="form-label tiny">Code *</label><input name="code" class="form-control" required></div>
            <div class="col-8"><label class="form-label tiny">Name *</label><input name="name" class="form-control" required></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
