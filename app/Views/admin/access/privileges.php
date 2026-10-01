<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('privNew', 'Grant access')) ?>
<?= view('admin/access/_subnav', ['pages' => $pages, 'tab' => $tab]) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Employee</th><th>Code</th><th>Access level</th><th>Area</th><th>Verify</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($privileges as $pr): ?>
            <tr>
                <td class="fw-semibold"><?= esc($pr['name'] ?: $pr['first_name'] ?: '—') ?></td>
                <td class="mono"><?= esc($pr['emp_code']) ?></td>
                <td><?= esc($pr['group_name']) ?> <span class="mono text-muted">#<?= (int) $pr['group_no'] ?></span></td>
                <td><?= esc($pr['area_name'] ?? '—') ?></td>
                <td class="small"><?= (int) $pr['is_group_timezone'] === 1 ? 'Level default' : esc($vm[(int) $pr['verify_mode']] ?? '—') ?></td>
                <td class="text-end">
                    <?= form_open(route_to('access.priv.revoke', $pr['id']), ['class' => 'd-inline', 'data-confirm' => 'Revoke this access?']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Revoke</button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $privileges): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-lock"></i>No employee privileges.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (isset($privPager)): ?><div class="px-3 pb-2"><?= $privPager->links('priv') ?></div><?php endif; ?>
</div>
<div class="modal fade" id="privNew" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('access.priv.assign')) ?>
    <div class="modal-header"><h5 class="modal-title">Adjust — grant door access</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2">
            <label class="form-label tiny">Employees *</label>
            <input type="text" class="form-control form-control-sm mb-1" id="privEmpSearch" placeholder="Search name or code…">
            <div class="border rounded p-2" style="max-height:220px;overflow-y:auto" id="privEmpList">
                <?php foreach ($employees as $e): ?>
                    <label class="form-check d-block priv-emp-row" data-search="<?= esc(strtolower(($e['name'] ?: $e['first_name'] ?: '') . ' ' . $e['emp_code']), 'attr') ?>">
                        <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= $e['id'] ?>">
                        <span class="form-check-label small"><?= esc($e['name'] ?: $e['first_name'] ?: '#') ?> <span class="text-muted mono">· <?= esc($e['emp_code']) ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Group (access level) *</label>
            <select name="group_id" class="form-select" required><option value="">Select…</option>
                <?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>"><?= esc($g['group_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label tiny d-block">Verification Mode</label>
                <div class="btn-group btn-group-sm mb-2" role="group">
                    <input type="radio" class="btn-check" name="verify_scope" id="vsGroup" value="group" checked>
                    <label class="btn btn-outline-secondary" for="vsGroup">Group</label>
                    <input type="radio" class="btn-check" name="verify_scope" id="vsPersonnel" value="personnel">
                    <label class="btn btn-outline-secondary" for="vsPersonnel">Personnel</label>
                </div>
                <select name="verify_mode" class="form-select form-select-sm" id="privVerifyMode" disabled>
                    <?php foreach ($vm as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label tiny d-block">Time Period</label>
                <div class="btn-group btn-group-sm mb-2" role="group">
                    <input type="radio" class="btn-check" name="tz_scope" id="tsGroup" value="group" checked>
                    <label class="btn btn-outline-secondary" for="tsGroup">Group</label>
                    <input type="radio" class="btn-check" name="tz_scope" id="tsPersonnel" value="personnel">
                    <label class="btn btn-outline-secondary" for="tsPersonnel">Personnel</label>
                </div>
                <?php foreach ([1, 2, 3] as $n): ?>
                    <select name="timezone<?= $n ?>" class="form-select form-select-sm priv-tz mb-1" disabled>
                        <option value="">Time Period <?= $n ?> — none —</option>
                        <?php foreach ($timezones as $z): ?><option value="<?= $z['id'] ?>"><?= esc('#' . $z['timezone_no'] . ' ' . $z['timezone_name']) ?></option><?php endforeach; ?>
                    </select>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Grant</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    var search = document.getElementById('privEmpSearch');
    if (search) {
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            document.querySelectorAll('.priv-emp-row').forEach(function (row) {
                row.hidden = q !== '' && row.dataset.search.indexOf(q) === -1;
            });
        });
    }
    function scopeToggle(radioName, selector) {
        var els = document.querySelectorAll(selector);
        document.querySelectorAll('input[name="' + radioName + '"]').forEach(function (r) {
            r.addEventListener('change', function () {
                var personnel = document.querySelector('input[name="' + radioName + '"]:checked').value === 'personnel';
                els.forEach(function (el) { el.disabled = !personnel; });
            });
        });
    }
    scopeToggle('verify_scope', '#privVerifyMode');
    scopeToggle('tz_scope', '.priv-tz');
})();
</script>
<?= $this->endSection() ?>
