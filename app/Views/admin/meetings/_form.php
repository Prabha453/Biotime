<?php $r = $row; ?>
<div class="modal fade" id="<?= esc($id, 'attr') ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('meeting.save')) ?>
    <?php if ($r): ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><?php endif; ?>
    <div class="modal-header">
        <h5 class="modal-title"><?= $r ? 'Edit room' : 'New meeting room' ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label>
            <input name="alias" class="form-control" required value="<?= esc($r['alias'] ?? '') ?>">
        </div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Capacity *</label>
                <input type="number" min="1" name="capacity" class="form-control" required value="<?= esc($r['capacity'] ?? 8) ?>">
            </div>
            <div class="col"><label class="form-label tiny">Location</label>
                <input name="location" class="form-control" value="<?= esc($r['location'] ?? '') ?>">
            </div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Check-in terminal</label>
            <select name="device_id" class="form-select">
                <option value="">None</option>
                <?php foreach ($devices as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int) ($r['device_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['alias']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label tiny">Note</label>
            <input name="remark" class="form-control" value="<?= esc($r['remark'] ?? '') ?>">
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="enable_room" value="1" id="en<?= esc($id, 'attr') ?>"
                <?= ! $r || $r['enable_room'] === true || $r['enable_room'] === 't' ? 'checked' : '' ?>>
            <label class="form-check-label small" for="en<?= esc($id, 'attr') ?>">Room is active / bookable</label>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><?= $r ? 'Save' : 'Create' ?></button></div>
    <?= form_close() ?>
</div></div></div>
