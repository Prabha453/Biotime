<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Send message', 'modal' => 'msgForm'],
    'filter'       => '#msgFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'columns'      => true,
]) ?>
<?= filter_builder('msgFilter', $tab === 'private'
    ? ['emp_code' => ['label' => 'Employee ID', 'type' => 'text'], 'target' => ['label' => 'Employee', 'type' => 'text'], 'message' => ['label' => 'Message', 'type' => 'text'], 'start_time' => ['label' => 'Start', 'type' => 'date']]
    : ['target' => ['label' => 'Device', 'type' => 'text'], 'message' => ['label' => 'Message', 'type' => 'text'], 'start_time' => ['label' => 'Start', 'type' => 'date']],
    ['button' => false]) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('devmsg.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th data-col="target"><?= $tab === 'private' ? 'Employee' : 'Device' ?></th>
        <th data-col="message">Message</th>
        <th data-col="duration" class="text-end">Duration (s)</th>
        <th data-col="start">Start</th>
        <th data-col="sent">Last sent</th>
        <th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?php if ($tab === 'private'): ?><span class="fw-semibold"><?= esc($r['target'] ?: $r['emp_code']) ?></span> <span class="text-muted small mono"><?= esc($r['emp_code'] ?? '') ?></span><?php else: ?><?= esc($r['target'] ?? '—') ?><?php endif; ?></td>
            <td class="small"><?= esc($r['message']) ?></td>
            <td class="text-end mono"><?= (int) $r['duration'] ?></td>
            <td class="mono small text-muted"><?= esc($r['start_at'] ?? '—') ?></td>
            <td class="mono small text-muted"><?= esc($r['sent_at'] ?? 'not yet') ?></td>
            <td class="text-end">
                <?= form_open(route_to('devmsg.delete', $tab, $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Withdraw this message?', 'data-confirm-danger' => '']) ?>
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-chat-dots"></i>No <?= strtolower($tabs[$tab]) ?>s.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="msgForm" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('devmsg.send', $tab)) ?>
    <div class="modal-header"><h5 class="modal-title">Send <?= strtolower($tabs[$tab]) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label tiny">Message *</label><textarea name="message" class="form-control" rows="3" maxlength="320" required></textarea></div>
        <div class="col-12">
            <label class="form-label tiny"><?= $tab === 'private' ? 'Employees' : 'Devices' ?> *</label>
            <select name="targets[]" class="form-select" multiple size="5" required data-select-all>
                <?php if ($tab === 'private'): ?>
                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc(($e['name'] ?: $e['emp_code']) . ' (' . $e['emp_code'] . ')') ?></option><?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($devices as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['alias']) ?></option><?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-6"><label class="form-label tiny">Start time</label><input type="datetime-local" name="start_time" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
        <div class="col-6"><label class="form-label tiny">Duration (s) *</label><input type="number" name="duration" class="form-control" value="60" min="1" required></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Send</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
