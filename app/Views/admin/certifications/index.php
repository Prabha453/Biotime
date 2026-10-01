<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$newBtn = $tab === 'types'
    ? modal_btn('ctForm', 'New type', 'plus-lg')
    : modal_btn('ecForm', 'Add certification', 'plus-lg');

function cert_state(?string $expire, $before): array
{
    if (! $expire) {
        return ['No expiry', 'muted'];
    }
    $days = (int) floor((strtotime($expire) - strtotime(date('Y-m-d'))) / 86400);
    $win  = $before !== null && $before !== '' ? (int) $before : 30;
    if ($days < 0)    return ['Expired ' . abs($days) . 'd ago', 'danger'];
    if ($days <= $win) return ['Expires in ' . $days . 'd', 'warning'];
    return ['Valid · ' . $days . 'd left', 'success'];
}
?>

<?= page_head($title, $tab === 'types' ? 'Certification / licence categories' : 'Licences and certifications held by staff, with expiry tracking') ?>
<?= grid_toolbar($tab === 'types'
    ? [
        'add'     => ['label' => 'New type', 'modal' => 'ctForm'],
        'import'  => 'certifications',
        'export'  => ['key' => 'cert_types'],
        'columns' => true,
        'history' => ['prefix' => 'cert.type', 'label' => 'Certification type'],
    ]
    : [
        'add'          => ['label' => 'Add certification', 'modal' => 'ecForm'],
        'import'       => 'employeecerts',
        'export'       => ['key' => 'employee_certs'],
        'columns'      => true,
        'filter'       => '#certFilterPanel',
        'filter_count' => (int) ($fbApplied ?? 0),
        'history'      => ['prefix' => 'cert.record', 'label' => 'Employee certification'],
    ]) ?>
<?php if ($tab !== 'types'): ?>
<?= filter_builder('certFilter', [
    'emp_code'    => ['label' => 'Employee ID', 'type' => 'text'],
    'emp_name'    => ['label' => 'Employee name', 'type' => 'text'],
    'cert_name'   => ['label' => 'Certification', 'type' => 'text'],
    'cert_code'   => ['label' => 'Cert code', 'type' => 'text'],
    'expire_on'   => ['label' => 'Expires on', 'type' => 'date'],
    'email_alert' => ['label' => 'Email alert', 'type' => 'bool'],
], ['button' => false]) ?>
<?php endif; ?>

<?php if ($tab === 'types'): ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th data-col="code">Code</th><th data-col="name">Name</th><th data-col="held" class="text-end">Held by</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono text-muted"><?= esc($r['cert_code']) ?></td>
                <td class="fw-semibold"><?= esc($r['cert_name']) ?></td>
                <td class="mono text-end"><?= (int) $r['held'] ?></td>
                <td class="text-end" style="white-space:nowrap">
                    <button class="btn btn-sm btn-outline-secondary js-edit" data-bs-toggle="modal" data-bs-target="#ctForm"
                        data-id="<?= $r['id'] ?>" data-cert_code="<?= esc($r['cert_code'], 'attr') ?>" data-cert_name="<?= esc($r['cert_name'], 'attr') ?>"><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('cert.type.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this type?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-patch-check"></i>No certification types yet.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>

<div class="modal fade" id="ctForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('cert.type.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Certification type</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2">
            <div class="col-4"><label class="form-label tiny">Code *</label><input name="cert_code" class="form-control mono" required maxlength="20"></div>
            <div class="col"><label class="form-label tiny">Name *</label><input name="cert_name" class="form-control" required maxlength="100" placeholder="First Aid Certificate"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<?php else: /* register */ ?>

<div class="data-toolbar">
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach (['' => 'All', 'expired' => 'Expired', 'expiring' => 'Expiring', 'valid' => 'Valid'] as $k => $lbl): ?>
            <a class="btn <?= ($status ?? '') === $k ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="<?= route_to('certifications') . ($k ? '?status=' . $k : '') ?>"><?= $lbl ?>
                <?php if ($k === 'expired' && ($counts['expired'] ?? 0)): ?><span class="pill pill-danger ms-1"><?= (int) $counts['expired'] ?></span>
                <?php elseif ($k === 'expiring' && ($counts['expiring'] ?? 0)): ?><span class="pill pill-warning ms-1"><?= (int) $counts['expiring'] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th data-col="employee">Employee</th><th data-col="cert">Certification</th><th data-col="ref">Reference</th><th data-col="expires">Expires</th><th data-col="status">Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): [$lbl, $cls] = cert_state($r['expire_on'], $r['before']); ?>
            <tr>
                <td><span class="mono text-muted"><?= esc($r['emp_code']) ?></span> <?= esc($r['emp_name'] ?? '') ?></td>
                <td class="fw-semibold"><?= esc($r['cert_name']) ?>
                    <?php if (! empty($r['file'])): ?>
                        <a href="<?= esc(base_url('assets/documents/' . basename($r['file'])), 'attr') ?>" target="_blank" rel="noopener"
                           class="text-muted ms-1" title="<?= esc($r['file_name'] ?? 'Document', 'attr') ?>"><i class="bi bi-paperclip"></i></a>
                    <?php endif; ?>
                </td>
                <td class="mono text-muted"><?= esc($r['certification_code'] ?? '—') ?></td>
                <td class="mono"><?= esc($r['expire_on'] ?? '—') ?></td>
                <td><span class="pill pill-<?= $cls ?>"><?= esc($lbl) ?></span>
                    <?= ($r['email_alert'] === true || $r['email_alert'] === 't') ? '<i class="bi bi-bell-fill text-muted ms-1" title="Email alert on"></i>' : '' ?>
                </td>
                <td class="text-end" style="white-space:nowrap">
                    <button class="btn btn-sm btn-outline-secondary js-edit" data-bs-toggle="modal" data-bs-target="#ecForm"
                        data-id="<?= $r['id'] ?>" data-employee_id="<?= (int) $r['employee_id'] ?>" data-certification_id="<?= (int) $r['certification_id'] ?>"
                        data-certification_code="<?= esc($r['certification_code'], 'attr') ?>"
                        data-expire_on="<?= esc($r['expire_on'], 'attr') ?>" data-before="<?= esc($r['before'], 'attr') ?>"
                        data-email_alert="<?= ($r['email_alert'] === true || $r['email_alert'] === 't') ? 1 : 0 ?>"
                        data-file_name="<?= esc($r['file_name'] ?? '', 'attr') ?>"
                        data-file="<?= $r['file'] ? esc(base_url('assets/documents/' . basename($r['file'])), 'attr') : '' ?>"><i class="bi bi-pencil"></i></button>
                    <?= form_open(route_to('cert.record.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this record?']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-patch-check"></i>No certification records<?= ($status ?? '') ? ' in this view' : ' yet' ?>.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="ecForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open_multipart(route_to('cert.record.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Certification record</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee *</label>
            <select name="employee_id" class="form-select" required><option value="">Select…</option>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label tiny">Certification *</label>
            <select name="certification_id" class="form-select" required><option value="">Select…</option>
                <?php foreach ($types as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['cert_name']) ?></option><?php endforeach; ?>
            </select>
            <?php if (! $types): ?><div class="small text-muted mt-1">Add a certification type first.</div><?php endif; ?>
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Reference / number</label><input name="certification_code" class="form-control" maxlength="20"></div>
            <div class="col"><label class="form-label tiny">Expiry date</label><input type="date" name="expire_on" class="form-control"></div>
        </div>
        <div class="row g-2 align-items-end mb-2">
            <div class="col"><label class="form-label tiny">Remind (days before)</label><input type="number" name="before" class="form-control" min="0" placeholder="30"></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" name="email_alert" value="1" id="ecMail"><label class="form-check-label small" for="ecMail">Email alert</label></div></div>
        </div>
        <div class="mb-1"><label class="form-label tiny">Scanned document</label>
            <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <div class="form-hint">PDF, image or Word — up to 8 MB.</div>
        </div>
        <div class="small mt-1 js-ec-current" hidden>
            Current: <a href="#" target="_blank" rel="noopener" class="js-ec-file"></a>
            <label class="form-check form-check-inline ms-2"><input type="checkbox" class="form-check-input" name="document_remove" value="1"> <span class="form-check-label">remove</span></label>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('#ctForm, #ecForm').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (ev) {
        var t = ev.relatedTarget, form = modal.querySelector('form');
        form.reset();
        var edit = t && t.classList.contains('js-edit');
        form.querySelector('[name=id]').value = edit ? (t.dataset.id || '') : '';
        var cur = form.querySelector('.js-ec-current');
        if (cur) cur.hidden = true;
        if (!edit) return;
        Object.keys(t.dataset).forEach(function (k) {
            if (['id', 'bsToggle', 'bsTarget', 'file', 'fileName'].indexOf(k) > -1) return;
            var f = form.querySelector('[name=' + k + ']');
            if (!f || t.dataset[k] === '') return;
            if (f.type === 'checkbox') f.checked = t.dataset[k] === '1';
            else f.value = t.dataset[k] === 'null' ? '' : t.dataset[k];
        });
        if (cur && t.dataset.file) {
            var a = cur.querySelector('.js-ec-file');
            a.href = t.dataset.file;
            a.textContent = t.dataset.fileName || 'document';
            cur.hidden = false;
        }
    });
});
</script>
<?= $this->endSection() ?>
