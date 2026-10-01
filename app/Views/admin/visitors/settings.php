<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, '<a class="btn btn-sm btn-outline-secondary" href="' . route_to('visitors') . '"><i class="bi bi-arrow-left me-1"></i>Register</a>') ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Visit reasons <span class="ms-auto"><?= modal_btn('rsForm', 'Add', 'plus-lg', 'btn-outline-primary') ?></span></div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Reason</th><th class="text-end">Used</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($reasons as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($r['visit_reason']) ?></td>
                        <td class="text-end mono"><?= (int) $r['uses'] ?></td>
                        <td class="text-end" style="white-space:nowrap">
                            <button class="btn btn-sm btn-outline-secondary js-rs" data-id="<?= $r['id'] ?>" data-name="<?= esc($r['visit_reason'], 'attr') ?>"><i class="bi bi-pencil"></i></button>
                            <?= form_open(route_to('visitor.reason.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete?', 'data-confirm-danger' => '']) ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            <?= form_close() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (! $reasons): ?><tr><td colspan="3" class="text-muted small py-3">No reasons yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <div class="fw-semibold mb-3">Registration policy</div>
            <?= form_open(route_to('visitor.config.save')) ?>
            <div class="row g-2 mb-3">
                <div class="col-4"><label class="form-label tiny">Code prefix</label><input name="code_prefix" class="form-control mono" maxlength="5" value="<?= esc($config['code_prefix'] ?? 'V') ?>"></div>
                <div class="col"><label class="form-label tiny">Code digits</label><input name="code_width" type="number" min="4" max="12" class="form-control" value="<?= (int) ($config['code_width'] ?? 8) ?>"></div>
            </div>
            <div class="mb-3"><label class="form-label tiny">QR self pre-registration</label>
                <select name="qr_code_policy" class="form-select">
                    <option value="1" <?= (int) ($config['qr_code_policy'] ?? 1) === 1 ? 'selected' : '' ?>>Pending — needs staff approval</option>
                    <option value="2" <?= (int) ($config['qr_code_policy'] ?? 1) === 2 ? 'selected' : '' ?>>Auto-approved</option>
                </select>
                <p class="form-hint mb-0"><i class="bi bi-info-circle me-1"></i>Governs the QR self pre-registration flow, which isn't built in this app yet — front-desk check-in below doesn't use it.</p>
            </div>
            <div class="mb-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="auto_delete_data" value="1" <?= ! empty($config['auto_delete_data']) ? 'checked' : '' ?>>
                <span class="form-check-label">Auto-purge old visitor records</span></label></div>
            <div class="mb-3"><label class="form-label tiny">Keep records for (days)</label>
                <input name="data_retention" type="number" min="30" class="form-control" style="max-width:140px" value="<?= (int) ($config['data_retention'] ?? 365) ?>">
                <p class="form-hint mb-0"><i class="bi bi-info-circle me-1"></i>Saved but not enforced yet — needs a scheduled purge job.</p>
            </div>
            <button class="btn btn-primary">Save policy</button>
            <?= form_close() ?>
        </div></div>
    </div>
</div>

<div class="modal fade" id="rsForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('visitor.reason.save')) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Visit reason</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label tiny">Reason *</label><input name="visit_reason" class="form-control" required maxlength="100"></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.js-rs').forEach(function(b){
  b.addEventListener('click', function(){
    var m = document.getElementById('rsForm'), form = m.querySelector('form'); form.reset();
    form.querySelector('[name=id]').value = b.dataset.id;
    form.querySelector('[name=visit_reason]').value = b.dataset.name;
    new bootstrap.Modal(m).show();
  });
});
</script>
<?= $this->endSection() ?>
