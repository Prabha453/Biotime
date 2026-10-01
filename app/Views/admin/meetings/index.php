<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('roomNew', 'New room')) ?>
<?= view('admin/meetings/_subnav', ['nav' => 'rooms']) ?>

<div class="ext-note"><i class="bi bi-info-circle"></i>
    Rooms and their check-in terminal (<code>meeting_meetingroom</code>). Create bookings on the <a href="<?= route_to('meetings.bookings') ?>">Bookings</a> tab.
</div>

<div class="row g-3">
    <?php foreach ($rows as $r): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold"><?= esc($r['alias']) ?></div>
                        <div class="small text-muted"><?= esc($r['location'] ?: 'No location set') ?></div>
                    </div>
                    <?= $r['enable_room'] === true || $r['enable_room'] === 't'
                        ? '<span class="pill pill-success">Active</span>'
                        : '<span class="pill pill-muted">Disabled</span>' ?>
                </div>
                <dl class="dl-grid mt-3">
                    <dt>Capacity</dt><dd><?= (int) $r['capacity'] ?> people</dd>
                    <dt>Terminal</dt><dd><?= esc($r['device_name'] ?? '—') ?></dd>
                    <dt>Code</dt><dd class="mono"><?= esc($r['code']) ?></dd>
                </dl>
                <?php if ($r['remark']): ?><p class="small text-muted mt-2 mb-0"><?= esc($r['remark']) ?></p><?php endif; ?>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#roomEdit<?= $r['id'] ?>"><i class="bi bi-pencil me-1"></i>Edit</button>
                    <?= form_open(route_to('meeting.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this room?', 'data-confirm-danger' => '']) ?>
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    <?= form_close() ?>
                </div>
            </div></div>
        </div>
        <?= view('admin/meetings/_form', ['id' => 'roomEdit' . $r['id'], 'row' => $r, 'devices' => $devices]) ?>
    <?php endforeach; ?>
    <?php if (! $rows): ?>
        <div class="col-12"><div class="card"><div class="empty-state"><i class="bi bi-easel"></i>No meeting rooms yet.</div></div></div>
    <?php endif; ?>
</div>

<?= view('admin/meetings/_form', ['id' => 'roomNew', 'row' => null, 'devices' => $devices]) ?>
<?= $this->endSection() ?>
