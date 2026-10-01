<?php $r = $row; ?>
<div class="modal fade" id="<?= esc($id, 'attr') ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('access.level.save')) ?>
    <?php if ($r): ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><?php endif; ?>
    <div class="modal-header">
        <h5 class="modal-title"><?= $r ? 'Edit access level' : 'New access level' ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-md-6"><label class="form-label tiny">Area / doors *</label>
                <select name="area_id" class="form-select" required>
                    <option value="">Select…</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (int) ($r['area_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= esc($a['area_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label tiny">Group No. *</label>
                <input type="number" min="1" name="group_no" class="form-control" required value="<?= esc((string) ($r['group_no'] ?? $nextGroupNo)) ?>">
            </div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Name *</label>
            <input name="group_name" class="form-control" required value="<?= esc($r['group_name'] ?? '') ?>">
        </div>
        <div class="mb-2"><label class="form-label tiny">Verify mode</label>
            <select name="verify_mode" class="form-select">
                <?php foreach ($vm as $k => $v): ?>
                    <option value="<?= $k ?>" <?= (int) ($r['verify_mode'] ?? 0) === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2 mb-2">
            <?php foreach ([1 => true, 2 => false, 3 => false] as $n => $required): ?>
                <div class="col-4">
                    <label class="form-label tiny">Time zone <?= $n ?><?= $required ? ' *' : '' ?></label>
                    <select name="timezone<?= $n ?>" class="form-select" <?= $required ? 'required' : '' ?>>
                        <option value=""><?= $required ? 'Select…' : '— none —' ?></option>
                        <?php foreach ($timezones as $z): ?>
                            <option value="<?= $z['id'] ?>" <?= (int) ($r['timezone' . $n] ?? 0) === (int) $z['id'] ? 'selected' : '' ?>><?= esc('#' . $z['timezone_no'] . ' ' . $z['timezone_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">Any one of the (up to 3) time zones matching lets the door open — like ZKBioTime, at least one is required.</p>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_include_holiday" value="1" id="hol<?= esc($id, 'attr') ?>"
                <?= (int) ($r['is_include_holiday'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label small" for="hol<?= esc($id, 'attr') ?>">Access allowed on holidays</label>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><?= $r ? 'Save' : 'Create' ?></button></div>
    <?= form_close() ?>
</div></div></div>
