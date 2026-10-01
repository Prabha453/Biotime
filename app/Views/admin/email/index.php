<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$byEvent = [];
foreach ($templates as $t) { $byEvent[(int) $t['event']] = $t; }
?>

<?= page_head($title, $subtitle) ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">
            SMTP server
            <?= $configured ? '<span class="pill pill-success ms-auto">Configured</span>' : '<span class="pill pill-muted ms-auto">Not set</span>' ?>
        </div>
        <div class="card-body">
            <?= form_open(route_to('email.save')) ?>
                <div class="row g-2 mb-2">
                    <div class="col-8"><label class="form-label tiny">Host *</label><input name="host" class="form-control" value="<?= esc($cfg['host'] ?? '') ?>" required placeholder="smtp.gmail.com"></div>
                    <div class="col-4"><label class="form-label tiny">Port *</label><input name="port" type="number" class="form-control" value="<?= esc($cfg['port'] ?? 587) ?>" required></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col"><label class="form-label tiny">Username</label><input name="user" class="form-control" value="<?= esc($cfg['user'] ?? '') ?>" autocomplete="off"></div>
                    <div class="col"><label class="form-label tiny">Password</label><input name="pass" type="password" class="form-control" value="<?= esc($cfg['pass'] ?? '') ?>" autocomplete="new-password"></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4"><label class="form-label tiny">Encryption</label>
                        <select name="crypto" class="form-select">
                            <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= ($cfg['crypto'] ?? 'tls') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col"><label class="form-label tiny">From address *</label><input name="from" type="email" class="form-control" value="<?= esc($cfg['from'] ?? '') ?>" required></div>
                </div>
                <div class="mb-2"><label class="form-label tiny">From name</label><input name="fromName" class="form-control" value="<?= esc($cfg['fromName'] ?? 'TimeTrack') ?>"></div>
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save settings</button>
            <?= form_close() ?>
        </div></div>

        <div class="card mt-3"><div class="card-header">Send a test</div>
        <div class="card-body">
            <?= form_open(route_to('email.test'), ['class' => 'row g-2 align-items-end']) ?>
                <div class="col"><label class="form-label tiny">Send test email to</label><input name="to" type="email" class="form-control" placeholder="you@example.com" required></div>
                <div class="col-auto"><button class="btn btn-outline-secondary"<?= $configured ? '' : ' disabled' ?>><i class="bi bi-send me-1"></i>Send</button></div>
            <?= form_close() ?>
            <?php if (! $configured): ?><p class="small text-muted mt-2 mb-0">Save a host and from-address first.</p><?php endif; ?>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card"><div class="card-header">Notification templates</div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Event</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev => $label): $t = $byEvent[$ev] ?? null; ?>
                <tr>
                    <td class="fw-semibold"><?= esc($label) ?>
                        <?php if ($t && $t['subject']): ?><div class="small text-muted"><?= esc($t['subject']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if (! $t): ?><span class="pill pill-muted">Not set</span>
                        <?php elseif ($t['enable'] === true || $t['enable'] === 't'): ?><span class="pill pill-success">On</span>
                        <?php else: ?><span class="pill pill-warning">Off</span><?php endif; ?>
                    </td>
                    <td class="text-end" style="white-space:nowrap">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tpl<?= $ev ?>"><i class="bi bi-pencil"></i></button>
                        <?php if ($t): ?>
                            <?= form_open(route_to('email.template.delete', $t['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this template?', 'data-confirm-danger' => '']) ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            <?= form_close() ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></div>
        <p class="small text-muted mt-2">Placeholders: <code>{{employee}}</code> <code>{{type}}</code> <code>{{dates}}</code> <code>{{status}}</code> <code>{{approver}}</code> <code>{{remark}}</code></p>
    </div>
</div>

<?php foreach ($events as $ev => $label): $t = $byEvent[$ev] ?? null; ?>
<div class="modal fade" id="tpl<?= $ev ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('email.template.save')) ?>
    <input type="hidden" name="event" value="<?= $ev ?>">
    <input type="hidden" name="id" value="<?= $t['id'] ?? '' ?>">
    <div class="modal-header"><h5 class="modal-title"><?= esc($label) ?> — email</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Subject</label>
            <input name="subject" class="form-control" maxlength="100" value="<?= esc($t['subject'] ?? '') ?>" placeholder="Your {{type}} request was {{status}}">
        </div>
        <div class="mb-2"><label class="form-label tiny">Body *</label>
            <textarea name="template" class="form-control" rows="7" required placeholder="Hi {{employee}},&#10;&#10;Your {{type}} request for {{dates}} was {{status}} by {{approver}}.&#10;{{remark}}"><?= esc($t['template'] ?? '') ?></textarea>
        </div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="enable" value="1" id="en<?= $ev ?>" <?= ($t && ($t['enable'] === true || $t['enable'] === 't')) ? 'checked' : '' ?>><label class="form-check-label small" for="en<?= $ev ?>">Send this email when the event happens</label></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save template</button></div>
    <?= form_close() ?>
</div></div></div>
<?php endforeach; ?>

<?= $this->endSection() ?>
