<?php $r = $row; ?>
<div class="modal fade" id="<?= esc($id, 'attr') ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('holiday.save')) ?>
    <?php if ($r): ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><?php endif; ?>
    <div class="modal-header">
        <h5 class="modal-title"><?= $r ? 'Edit holiday' : 'Add holiday' ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label>
            <input name="alias" class="form-control" required value="<?= esc($r['alias'] ?? '') ?>">
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Start *</label>
                <input type="date" name="start_date" class="form-control" required value="<?= esc($r['start_date'] ?? '') ?>">
            </div>
            <div class="col"><label class="form-label tiny">End *</label>
                <input type="date" name="end_date" class="form-control" required value="<?= esc($r['end_date'] ?? '') ?>">
            </div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Scope</label>
            <select name="department_id" class="form-select">
                <option value="">Company-wide</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($r['department_id'] ?? null) == $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="enable_ot_rule" value="1" id="ot<?= esc($id, 'attr') ?>"
                <?= ($r['enable_ot_rule'] ?? '') === 't' || ($r['enable_ot_rule'] ?? false) === true ? 'checked' : '' ?>>
            <label class="form-check-label small" for="ot<?= esc($id, 'attr') ?>">Count work on this day as overtime</label>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><?= $r ? 'Save' : 'Add' ?></button></div>
    <?= form_close() ?>
</div></div></div>
