<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $st = [0 => ['Pending', 'warning'], 1 => ['Confirmed', 'success'], 2 => ['Cancelled', 'danger'], -1 => ['Revoked', 'muted']]; ?>
<?= page_head($title, $subtitle, modal_btn('mtgNew', 'New booking', 'plus-lg')) ?>
<?= view('admin/meetings/_subnav', ['nav' => 'bookings']) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Meeting</th><th>Room</th><th>Date</th><th>Time</th><th>Attendees</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $s = $st[(int) $r['approval_status']] ?? ['?', 'muted']; ?>
                <tr>
                    <td>
                        <a href="<?= route_to('meetings.view', $r['id']) ?>" class="fw-semibold text-body"><?= esc($r['alias']) ?></a>
                        <?php if ($r['content']): ?><div class="small text-muted"><?= esc($r['content']) ?></div><?php endif; ?>
                        <span class="mono text-muted small"><?= esc($r['code']) ?></span>
                    </td>
                    <td><?= esc($r['room'] ?? '—') ?></td>
                    <td class="mono small"><?= esc($r['meeting_date']) ?></td>
                    <td class="mono small"><?= esc($r['st']) ?>–<?= esc($r['et']) ?></td>
                    <td class="mono"><?= (int) $r['attendees'] ?></td>
                    <td><span class="pill pill-<?= $s[1] ?>"><?= $s[0] ?></span>
                        <?php if ($r['calculation_time']): ?><div class="small text-muted">calc'd</div><?php endif; ?>
                    </td>
                    <td class="text-end" style="white-space:nowrap">
                        <?= form_open(route_to('meetings.calculate', $r['id']), ['class' => 'd-inline']) ?>
                            <button class="btn btn-sm btn-outline-primary" title="Recalculate attendance"><i class="bi bi-calculator"></i></button>
                        <?= form_close() ?>
                        <?= form_open(route_to('meetings.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this booking?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-easel"></i>No bookings yet.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<div class="modal fade" id="mtgNew" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('meetings.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New booking</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2">
            <div class="col-md-8"><label class="form-label tiny">Title *</label><input name="alias" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Room</label>
                <select name="room_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($rooms as $rm): ?><option value="<?= $rm['id'] ?>"><?= esc($rm['alias']) ?> (<?= (int) $rm['capacity'] ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="form-label tiny">Agenda / notes</label><input name="content" class="form-control"></div>
            <div class="col-md-4"><label class="form-label tiny">Date *</label><input type="date" name="meeting_date" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">Start *</label><input type="time" name="start" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label tiny">End *</label><input type="time" name="end" class="form-control" required></div>
            <div class="col-12"><label class="form-label tiny">Attendees</label>
                <select name="attendees[]" class="form-select" multiple size="6">
                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) ?></option><?php endforeach; ?>
                </select>
                <div class="form-text">Ctrl/Cmd-click to select several.</div>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
