<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('admNew', 'New administrator')) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td class="fw-semibold mono"><?= esc($u['username']) ?><?= $u['id'] == session()->get('userId') ? ' <span class="pill pill-info">you</span>' : '' ?></td>
                    <td><?= esc($u['name'] ?? '—') ?><?php if ($u['email']): ?><div class="small text-muted"><?= esc($u['email']) ?></div><?php endif; ?></td>
                    <td><span class="pill pill-muted"><?= esc($u['role']) ?></span></td>
                    <td><span class="pill <?= $u['status'] === 'active' ? 'pill-success' : 'pill-danger' ?>"><?= esc($u['status']) ?></span></td>
                    <td class="mono small text-muted"><?= esc($u['last_login'] ?? '—') ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#admEdit<?= $u['id'] ?>"><i class="bi bi-pencil"></i></button>
                        <?= form_open(route_to('admin.toggle', $u['id']), ['class' => 'd-inline']) ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Toggle status"><i class="bi bi-toggle-<?= $u['status'] === 'active' ? 'on' : 'off' ?>"></i></button>
                        <?= form_close() ?>
                        <?php if ($u['id'] != session()->get('userId')): ?>
                        <?= form_open(route_to('admin.delete', $u['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this administrator?', 'data-confirm-danger' => '']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php // edit modal ?>
                <div class="modal fade" id="admEdit<?= $u['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <?= form_open(route_to('admin.save')) ?>
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <div class="modal-header"><h5 class="modal-title">Edit <?= esc($u['name']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-2 mb-2">
                            <div class="col"><label class="form-label tiny">Username</label><input name="username" class="form-control mono" value="<?= esc(old('username', $u['username'])) ?>" required autocapitalize="none"></div>
                            <div class="col"><label class="form-label tiny">Full name</label><input name="name" class="form-control" value="<?= esc(old('name', $u['name'])) ?>" required></div>
                        </div>
                        <div class="mb-2"><label class="form-label tiny">Email</label><input type="email" name="email" class="form-control" value="<?= esc(old('email', $u['email'])) ?>"></div>
                        <div class="row g-2">
                            <div class="col"><label class="form-label tiny">Role</label>
                                <select name="role" class="form-select">
                                    <?php foreach (['admin', 'manager', 'viewer'] as $r): ?><option <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col"><label class="form-label tiny">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>active</option>
                                    <option value="disabled" <?= $u['status'] !== 'active' ? 'selected' : '' ?>>disabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2"><label class="form-label tiny">New password <span class="text-muted">(blank = keep)</span></label><input type="password" name="password" class="form-control" minlength="6"></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
                    <?= form_close() ?>
                </div></div></div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<?php // new modal ?>
<div class="modal fade" id="admNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('admin.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New administrator</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Username</label><input name="username" class="form-control mono" value="<?= esc(old('username')) ?>" required autocapitalize="none" placeholder="jsmith"></div>
            <div class="col"><label class="form-label tiny">Full name</label><input name="name" class="form-control" value="<?= esc(old('name')) ?>" required></div>
        </div>
        <div class="mb-2"><label class="form-label tiny">Email</label><input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>"></div>
        <div class="row g-2 mb-2">
            <div class="col"><label class="form-label tiny">Role</label>
                <select name="role" class="form-select"><option>admin</option><option>manager</option><option>viewer</option></select>
            </div>
            <div class="col"><label class="form-label tiny">Password</label><input type="password" name="password" class="form-control" minlength="6" required></div>
        </div>
        <p class="ext-note mt-1 mb-0"><i class="bi bi-info-circle"></i> This creates a ZKBio&nbsp;Time <span class="mono">auth_user</span> — usable in the ZKBio console too.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
