<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$rv = static fn ($v) => $v === null || $v === '' ? '—'
    : (($returnValues[(int) $v] ?? $v) . ' (' . (int) $v . ')');

// per-tab column layout: [data-col key => [header, row-callback]]
$cols = [
    'command' => [
        'device'   => ['Device', fn ($r) => esc($r['device'] ?? '—')],
        'content'  => ['Command', fn ($r) => '<span class="mono small">' . esc($r['content']) . '</span>'],
        'commit'   => ['Committed', fn ($r) => '<span class="mono small text-muted">' . esc($r['commit_at']) . '</span>'],
        'transfer' => ['Transferred', fn ($r) => '<span class="mono small text-muted">' . esc($r['transfer_at'] ?? '—') . '</span>'],
    ],
    'cmdlog' => [
        'device'   => ['Device', fn ($r) => esc($r['device'] ?? '—')],
        'content'  => ['Command', fn ($r) => '<span class="mono small">' . esc($r['content']) . '</span>'],
        'ret'      => ['Return', fn ($r) => (int) ($r['return_value'] ?? -999) === 0
            ? '<span class="badge bg-label-success">' . $rv($r['return_value']) . '</span>'
            : '<span class="badge bg-label-' . ($r['return_value'] === null ? 'secondary' : 'danger') . '">' . $rv($r['return_value']) . '</span>'],
        'commit'   => ['Committed', fn ($r) => '<span class="mono small text-muted">' . esc($r['commit_at']) . '</span>'],
        'ret_at'   => ['Returned', fn ($r) => '<span class="mono small text-muted">' . esc($r['return_at'] ?? '—') . '</span>'],
    ],
    'devlog' => [
        'device' => ['Device', fn ($r) => esc($r['device'] ?? '—')],
        'action' => ['Operation', fn ($r) => esc($devActions[(int) $r['action_name']] ?? ('Action ' . (int) $r['action_name']))],
        'admin'  => ['Operator', fn ($r) => '<span class="mono small">' . esc($r['admin'] ?: '—') . '</span>'],
        'object' => ['Object', fn ($r) => '<span class="mono small">' . esc($r['object'] ?: '—') . '</span>'],
        'at'     => ['Time', fn ($r) => '<span class="mono small text-muted">' . esc($r['action_at'] ?? $r['upload_at'] ?? '—') . '</span>'],
    ],
    'upload' => [
        'device'  => ['Device', fn ($r) => esc($r['device'] ?? '—')],
        'event'   => ['Event', fn ($r) => esc($r['event'])],
        'content' => ['Content', fn ($r) => '<span class="small">' . esc($r['content']) . '</span>'],
        'count'   => ['Count', fn ($r) => '<span class="mono">' . (int) $r['upload_count'] . '</span>'],
        'err'     => ['Errors', fn ($r) => (int) $r['error_count'] ? '<span class="text-danger mono">' . (int) $r['error_count'] . '</span>' : '<span class="mono text-muted">0</span>'],
        'at'      => ['Uploaded', fn ($r) => '<span class="mono small text-muted">' . esc($r['upload_at']) . '</span>'],
    ],
    'errorcmd' => [
        'device' => ['Device', fn ($r) => esc($r['device'] ?? '—')],
        'cmd'    => ['Command', fn ($r) => '<span class="mono small">' . esc($r['cmd'] ?: '—') . '</span>'],
        'code'   => ['Error code', fn ($r) => '<span class="mono small">' . esc($r['error_code'] ?: '—') . '</span>'],
        'msg'    => ['Message', fn ($r) => '<span class="small text-danger">' . esc($r['error_msg'] ?: '—') . '</span>'],
        'at'     => ['Uploaded', fn ($r) => '<span class="mono small text-muted">' . esc($r['upload_at']) . '</span>'],
    ],
][$tab];

$fbFields = [
    'command'  => ['device' => ['label' => 'Device', 'type' => 'text'], 'content' => ['label' => 'Command', 'type' => 'text'], 'commit_time' => ['label' => 'Committed', 'type' => 'date']],
    'cmdlog'   => ['device' => ['label' => 'Device', 'type' => 'text'], 'content' => ['label' => 'Command', 'type' => 'text'], 'commit_time' => ['label' => 'Committed', 'type' => 'date']],
    'devlog'   => ['device' => ['label' => 'Device', 'type' => 'text'], 'admin' => ['label' => 'Operator', 'type' => 'text'], 'object' => ['label' => 'Object', 'type' => 'text'], 'action_time' => ['label' => 'Time', 'type' => 'date']],
    'upload'   => ['device' => ['label' => 'Device', 'type' => 'text'], 'event' => ['label' => 'Event', 'type' => 'text'], 'content' => ['label' => 'Content', 'type' => 'text'], 'upload_time' => ['label' => 'Uploaded', 'type' => 'date']],
    'errorcmd' => ['device' => ['label' => 'Device', 'type' => 'text'], 'cmd' => ['label' => 'Command', 'type' => 'text'], 'error_msg' => ['label' => 'Message', 'type' => 'text'], 'upload_time' => ['label' => 'Uploaded', 'type' => 'date']],
][$tab];
?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'filter'       => '#devlogFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'export'       => ['key' => 'devlog_' . $tab],
    'columns'      => true,
    'right'        => '<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#devlogClear"><i class="bi bi-trash me-1"></i>Clear</button>',
]) ?>
<?= filter_builder('devlogFilter', $fbFields, ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <?php foreach ($cols as $key => [$h, $_]): ?><th data-col="<?= $key ?>"><?= esc($h) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><?php foreach ($cols as [$h, $cb]): ?><td><?= $cb($r) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="<?= count($cols) ?>"><div class="empty-state"><i class="bi bi-journal-text"></i>Nothing logged yet.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="devlogClear" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('devlogs.clear', $tab), ['data-confirm' => 'Clear the selected log entries?']) ?>
    <div class="modal-header"><h5 class="modal-title">Clear <?= esc($tabs[$tab]) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label tiny">Remove entries older than</label>
        <select name="days" class="form-select">
            <option value="30">30 days</option>
            <option value="90" selected>90 days</option>
            <option value="180">180 days</option>
            <option value="365">1 year</option>
            <option value="0">Everything</option>
        </select>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Clear</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
