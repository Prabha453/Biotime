<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('holNew', 'Add holiday')) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Holiday</th><th>Start</th><th>End</th><th>Days</th><th>Scope</th><th>OT</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($r['alias']) ?></td>
                    <td class="mono"><?= esc($r['start_date']) ?></td>
                    <td class="mono"><?= esc($r['end_date']) ?></td>
                    <td class="mono"><?= (int) $r['duration_day'] ?></td>
                    <td><?= esc($r['dept_name'] ?? 'Company-wide') ?></td>
                    <td><?= $r['enable_ot_rule'] === 't' || $r['enable_ot_rule'] === true ? '<span class="pill pill-info">Yes</span>' : '<span class="pill pill-muted">No</span>' ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#holEdit<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <?= form_open(route_to('holiday.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this holiday?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
                <?= view('admin/holidays/_form', ['id' => 'holEdit' . $r['id'], 'row' => $r, 'departments' => $departments]) ?>
            <?php endforeach; ?>
            <?php if (! $rows): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-event"></i>
                    No holidays yet. Added days are marked <span class="pill pill-muted">Holiday</span> in the daily register.
                </div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<?= view('admin/holidays/_form', ['id' => 'holNew', 'row' => null, 'departments' => $departments]) ?>
<?= $this->endSection() ?>
