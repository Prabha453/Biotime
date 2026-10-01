<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('system.log.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?php
$sysOps = [1 => 'Backup', 2 => 'Restore', 14 => 'Auto-calc', 20 => 'Import', 21 => 'Export'];
$fmt    = static fn ($v, $n = 19) => esc(substr((string) ($v ?? ''), 0, $n));
?>

<div class="card"><div class="table-responsive">
<?php if ($tab === 'system'): ?>
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Time</th><th>Operation</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono small"><?= $fmt($r['execute_time'], 16) ?></td>
                <td><?= esc($sysOps[(int) $r['operation']] ?? ('#' . $r['operation'])) ?></td>
                <td><?= (int) $r['execute_status'] === 0 ? label('OK', 'success') : label('failed', 'danger') ?></td>
                <td class="text-muted small"><?= esc($r['description'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-journal-text"></i>No system-log entries.</div></td></tr><?php endif; ?>
        </tbody>
    </table>

<?php elseif ($tab === 'activity'): ?>
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Detail</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono small"><?= $fmt($r['op_time'], 16) ?></td>
                <td class="mono"><?= esc($r['username'] ?? '—') ?></td>
                <td><span class="mono small"><?= esc($r['action']) ?></span></td>
                <td class="small"><?= esc($r['targets_repr'] ?? '') ?></td>
                <td class="text-muted small"><?= esc($r['description'] ?? '') ?></td>
                <td class="mono small text-muted"><?= esc($r['ip_address'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-lines-fill"></i>No admin activity yet.</div></td></tr><?php endif; ?>
        </tbody>
    </table>

<?php elseif ($tab === 'notifications'): ?>
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Sent</th><th>Category</th><th>Content</th><th>Read</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono small"><?= $fmt($r['send_time'], 16) ?></td>
                <td class="mono"><?= (int) $r['category'] ?>/<?= (int) $r['event'] ?></td>
                <td class="small"><?= esc(mb_substr((string) $r['content'], 0, 120)) ?></td>
                <td><?= $r['read_time'] ? label('read', 'secondary') : label('unread', 'info') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-bell"></i>No notifications.</div></td></tr><?php endif; ?>
        </tbody>
    </table>

<?php elseif ($tab === 'devices'): ?>
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Time</th><th>Device</th><th>Event</th><th>Content</th><th class="text-end">Uploaded</th><th class="text-end">Errors</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="mono small"><?= $fmt($r['upload_time'], 16) ?></td>
                <td class="fw-semibold"><?= esc($r['device'] ?? '—') ?></td>
                <td><?= esc($r['event']) ?></td>
                <td class="text-muted small"><?= esc($r['content']) ?></td>
                <td class="text-end mono"><?= (int) $r['upload_count'] ?></td>
                <td class="text-end mono <?= (int) $r['error_count'] > 0 ? 'text-danger' : '' ?>"><?= (int) $r['error_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-hdd-network"></i>No device upload logs.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php if (! empty($commands)): ?>
    <div class="p-3 border-top"><div class="fw-semibold small mb-2 text-muted">Recent commands</div>
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Device</th><th>Command</th><th>Queued</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($commands as $c): ?>
                <tr>
                    <td><?= esc($c['device'] ?? '—') ?></td>
                    <td class="mono small"><?= esc(mb_substr((string) $c['content'], 0, 70)) ?></td>
                    <td class="mono small"><?= $fmt($c['commit_time'], 16) ?></td>
                    <td><?= $c['return_time'] ? ((int) $c['return_value'] === 0 ? label('OK', 'success') : label('err', 'danger')) : label('pending', 'warning') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'tasks'): ?>
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Task</th><th>Schedule</th><th>Status</th><th>Last run</th><th class="text-end">Runs</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $sched = $r['every'] ? ('every ' . $r['every'] . ' ' . $r['period'])
                : trim(($r['minute'] ?? '') . ' ' . ($r['hour'] ?? '') . ' ' . ($r['day_of_month'] ?? '') . ' ' . ($r['month_of_year'] ?? '') . ' ' . ($r['day_of_week'] ?? '')); ?>
            <tr>
                <td class="fw-semibold"><?= esc($r['name']) ?><div class="mono text-muted small"><?= esc($r['task']) ?></div></td>
                <td class="mono small"><?= esc($sched) ?></td>
                <td><?= ($r['enabled'] === true || $r['enabled'] === 't') ? label('enabled', 'success') : label('disabled', 'secondary') ?></td>
                <td class="mono small"><?= $fmt($r['last_run_at'], 16) ?: 'never' ?></td>
                <td class="text-end mono"><?= (int) $r['total_run_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-clock-history"></i>No scheduled tasks.</div></td></tr><?php endif; ?>
        </tbody>
    </table>

<?php else: /* params */ ?>
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Type</th><th>Name</th><th>Value</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="text-muted small"><?= esc($r['para_type'] ?? '') ?></td>
                <td class="mono"><?= esc($r['para_name']) ?></td>
                <td class="mono small"><?= esc($r['para_value'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="3"><div class="empty-state"><i class="bi bi-sliders"></i>No parameters.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php if (($total ?? 0) > $perPage):
    $pages = (int) ceil($total / $perPage);
    $from  = max(1, $page - 3);
    $to    = min($pages, $page + 3); ?>
<div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
    <span class="text-muted">Page <?= $page ?> of <?= $pages ?> · <?= number_format($total) ?> rows</span>
    <div class="btn-group btn-group-sm">
        <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?page=<?= $page - 1 ?>">Prev</a><?php endif; ?>
        <?php for ($i = $from; $i <= $to; $i++): ?>
            <a class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
