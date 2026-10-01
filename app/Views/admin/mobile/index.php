<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$catLabels = [1 => 'General', 2 => 'HR', 3 => 'Payroll', 4 => 'System'];
$newBtn = match ($tab) {
    'locations'     => modal_btn('locNew', 'New geofence', 'geo-alt'),
    'assignments'   => modal_btn('asgNew', 'Assign geofence', 'plus-lg'),
    'announcements' => modal_btn('annNew', 'New announcement', 'megaphone'),
    default         => '',
};
?>

<?= page_head($title, $subtitle, $newBtn) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('mobile.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'locations'): ?>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Alias</th><th>Address</th><th class="text-end">Latitude</th><th class="text-end">Longitude</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($locations as $l): ?>
            <tr>
                <td><strong><?= esc($l['alias']) ?></strong></td>
                <td class="text-muted small"><?= esc($l['location']) ?></td>
                <td class="mono text-end"><?= esc(round($l['latitude'], 6)) ?></td>
                <td class="mono text-end"><?= esc(round($l['longitude'], 6)) ?></td>
                <td class="text-end" style="white-space:nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="https://maps.google.com/?q=<?= $l['latitude'] ?>,<?= $l['longitude'] ?>" target="_blank" rel="noopener" title="View on map"><i class="bi bi-map"></i></a>
                    <?= form_open(route_to('mobile.location.delete', $l['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this geofence and its assignments?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-secondary" title="Delete"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $locations): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-geo-alt"></i>No geofences defined yet.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>

<?php elseif ($tab === 'assignments'): ?>
<div class="card mb-3">
    <div class="card-header"><strong>Employee geofences</strong></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Geofences</th><th class="text-end">Radius (m)</th><th>Effective</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($empAssign as $g): ?>
            <tr>
                <td><span class="mono text-muted"><?= esc($g['emp_code']) ?></span> <?= esc($g['name'] ?? '') ?></td>
                <td class="small"><?= esc($g['locs'] ?? '—') ?></td>
                <td class="mono text-end"><?= esc($g['distance']) ?></td>
                <td class="small text-muted"><?= esc($g['start_date']) ?> → <?= esc($g['end_date']) ?></td>
                <td class="text-end">
                    <?= form_open(route_to('mobile.assign.delete', 'employee', $g['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this assignment?']) ?>
                        <button class="btn btn-sm btn-outline-secondary" title="Remove"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $empAssign): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-person-x"></i>No employee geofence assignments.</div></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<div class="card">
    <div class="card-header"><strong>Department geofences</strong></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Department</th><th>Geofences</th><th class="text-end">Radius (m)</th><th>Effective</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($deptAssign as $g): ?>
            <tr>
                <td><?= esc($g['dept_name']) ?></td>
                <td class="small"><?= esc($g['locs'] ?? '—') ?></td>
                <td class="mono text-end"><?= esc($g['distance']) ?></td>
                <td class="small text-muted"><?= esc($g['start_date']) ?> → <?= esc($g['end_date']) ?></td>
                <td class="text-end">
                    <?= form_open(route_to('mobile.assign.delete', 'department', $g['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this assignment?']) ?>
                        <button class="btn btn-sm btn-outline-secondary" title="Remove"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $deptAssign): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-diagram-3"></i>No department geofence assignments.</div></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php elseif ($tab === 'announcements'): ?>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Subject</th><th>Audience</th><th>Category</th><th>Posted</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= esc($r['subject']) ?></strong><div class="small text-muted"><?= esc(mb_strimwidth((string) $r['content'], 0, 120, '…')) ?></div></td>
                <td class="small"><?= $r['receiver'] || $r['emp_code'] ? esc(trim(($r['emp_code'] ?? '') . ' ' . ($r['receiver'] ?? ''))) : '<span class="pill pill-info">All staff</span>' ?></td>
                <td class="small text-muted"><?= esc($catLabels[(int) $r['category']] ?? $r['category']) ?></td>
                <td class="small text-muted mono"><?= esc(substr((string) $r['create_time'], 0, 16)) ?></td>
                <td class="text-end">
                    <?= form_open(route_to('mobile.announce.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this announcement?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-secondary" title="Delete"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-megaphone"></i>No announcements yet.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div><?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?></div>

<?php elseif ($tab === 'notifications'): ?>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Recipient</th><th>Content</th><th>Category</th><th>Sent</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="small"><span class="mono text-muted"><?= esc($r['emp_code'] ?? '') ?></span> <?= esc($r['receiver'] ?? '') ?></td>
                <td class="small"><?= esc(mb_strimwidth((string) ($r['content'] ?? ''), 0, 140, '…')) ?></td>
                <td class="small text-muted"><?= esc($catLabels[(int) $r['category']] ?? $r['category']) ?></td>
                <td class="small text-muted mono"><?= esc(substr((string) $r['notification_time'], 0, 16)) ?></td>
                <td><span class="pill pill-<?= (int) $r['read_status'] ? 'success' : 'muted' ?>"><?= (int) $r['read_status'] ? 'Read' : 'Unread' ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-bell"></i>No notifications in the feed.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div><?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?></div>
<p class="ext-note mt-2">This feed is generated by the mobile app and background jobs. Announcements you post above fan out here per employee.</p>

<?php elseif ($tab === 'webpunch'): ?>
<?php $st = [0 => ['Pending', 'warning'], 1 => ['Approved', 'success'], 2 => ['Rejected', 'danger'], -1 => ['Revoked', 'muted']]; ?>
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Punch</th><th>Type</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $s = $st[(int) $r['approval_status']] ?? ['?', 'muted']; ?>
            <tr>
                <td><span class="mono text-muted"><?= esc($r['emp_code'] ?? '') ?></span> <?= esc($r['name'] ?? '') ?></td>
                <td class="mono small"><?= esc($r['punch_time']) ?></td>
                <td class="small"><?= esc(($stateLabels[(int) $r['punch_state']] ?? $r['punch_state'])) ?></td>
                <td class="small text-muted"><?= esc($r['apply_reason'] ?? '') ?></td>
                <td><span class="pill pill-<?= $s[1] ?>"><?= $s[0] ?></span></td>
                <td class="text-end" style="white-space:nowrap">
                    <?php if ((int) $r['approval_status'] === 0): ?>
                        <?= form_open(route_to('mobile.webpunch.decide', $r['id']), ['class' => 'd-inline']) ?>
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-outline-success" title="Approve — creates a punch"><i class="bi bi-check-lg"></i></button>
                        <?= form_close() ?>
                        <?= form_open(route_to('mobile.webpunch.decide', $r['id']), ['class' => 'd-inline']) ?>
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                        <?= form_close() ?>
                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-phone"></i>No web punch requests.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div><?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?></div>
<p class="ext-note mt-2">Approving a web punch writes a real <span class="mono">iclock_transaction</span> row so the attendance engine recalculates that day.</p>
<?php endif; ?>

<?php
$empOptions = function ($name, $required = false, $placeholder = 'All staff') use ($employees) {
    echo '<select name="' . $name . '" class="form-select"' . ($required ? ' required' : '') . '>';
    echo '<option value="">' . esc($placeholder) . '</option>';
    foreach ($employees as $e) {
        echo '<option value="' . $e['id'] . '">' . esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) . '</option>';
    }
    echo '</select>';
};
?>

<!-- New geofence -->
<div class="modal fade" id="locNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('mobile.location.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New geofence</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Alias *</label><input name="alias" class="form-control" required maxlength="100"></div>
        <div class="mb-2"><label class="form-label tiny">Address / description</label><input name="location" class="form-control" maxlength="100"></div>
        <div class="row g-2">
            <div class="col"><label class="form-label tiny">Latitude *</label><input name="latitude" class="form-control mono" required placeholder="25.204849"></div>
            <div class="col"><label class="form-label tiny">Longitude *</label><input name="longitude" class="form-control mono" required placeholder="55.270783"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- Assign geofence -->
<div class="modal fade" id="asgNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('mobile.assign')) ?>
    <div class="modal-header"><h5 class="modal-title">Assign geofence</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Apply to *</label>
            <select name="kind" class="form-select" id="asgKind" onchange="document.getElementById('asgEmp').hidden=this.value!=='employee';document.getElementById('asgDept').hidden=this.value!=='department'">
                <option value="employee">Employee</option><option value="department">Department</option>
            </select>
        </div>
        <div class="mb-2" id="asgEmp"><label class="form-label tiny">Employee *</label>
            <select name="target_id" class="form-select"><option value="">Select…</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2" id="asgDept" hidden><label class="form-label tiny">Department *</label>
            <select name="target_id" class="form-select" disabled><option value="">Select…</option>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <script>document.getElementById('asgKind').addEventListener('change',function(){
            document.querySelector('#asgEmp select').disabled=this.value!=='employee';
            document.querySelector('#asgDept select').disabled=this.value!=='department';
        });</script>
        <div class="mb-2"><label class="form-label tiny">Geofences *</label>
            <select name="location_ids[]" class="form-select" multiple size="4" required>
                <?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>"><?= esc($l['alias']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2">
            <div class="col"><label class="form-label tiny">Radius (m)</label><input type="number" name="distance" class="form-control" value="100" min="10"></div>
            <div class="col"><label class="form-label tiny">From</label><input type="date" name="start_date" class="form-control"></div>
            <div class="col"><label class="form-label tiny">To</label><input type="date" name="end_date" class="form-control"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Assign</button></div>
    <?= form_close() ?>
</div></div></div>

<!-- New announcement -->
<div class="modal fade" id="annNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('mobile.announce')) ?>
    <div class="modal-header"><h5 class="modal-title">New announcement</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Subject *</label><input name="subject" class="form-control" required maxlength="100"></div>
        <div class="mb-2"><label class="form-label tiny">Message *</label><textarea name="content" class="form-control" rows="4" required></textarea></div>
        <div class="row g-2">
            <div class="col"><label class="form-label tiny">Category</label>
                <select name="category" class="form-select"><?php foreach ($catLabels as $cv => $cl): ?><option value="<?= $cv ?>"><?= esc($cl) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col"><label class="form-label tiny">Recipient</label><?php $empOptions('receiver_id', false, 'All staff (broadcast)'); ?></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Post</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
