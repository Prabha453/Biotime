<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $clsMap = [1 => 'info', 2 => 'success', 3 => 'warning', 4 => 'muted']; ?>

<?= page_head($title, $subtitle,
    form_open(route_to('notifications.readall'), ['class' => 'd-inline'])
    . '<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all me-1"></i>Mark all read</button>'
    . form_close()) ?>

<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Type</th><th>Notification</th><th>Sent</th><th>Read</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr<?= (int) $r['status'] === 0 ? ' class="fw-semibold"' : '' ?>>
                <td><span class="pill pill-<?= $clsMap[(int) $r['category']] ?? 'muted' ?>"><?= esc($labels[(int) $r['category']] ?? 'Info') ?></span></td>
                <td>
                    <?= esc($r['content']) ?>
                    <?php if ($r['link']): ?><a href="<?= esc($r['link'], 'attr') ?>" class="ms-1 small">open →</a><?php endif; ?>
                </td>
                <td class="mono small text-muted"><?= esc($r['send_time']) ?></td>
                <td class="mono small text-muted"><?= esc($r['read_time'] ?? '—') ?></td>
                <td class="text-end">
                    <?php if ((int) $r['status'] === 0): ?>
                        <?= form_open(route_to('notification.read', $r['id']), ['class' => 'd-inline']) ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Mark read"><i class="bi bi-check-lg"></i></button>
                        <?= form_close() ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-bell-slash"></i>No notifications.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
