<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php $errors = session()->getFlashdata('errors') ?? []; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?= page_head($title, 'Your account details') ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Edit profile</div>
            <div class="card-body">
                <?= form_open(route_to('profile')) ?>
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= esc(old('name', $user['name'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New password <span class="text-muted small">(leave blank to keep current)</span></label>
                        <input type="password" name="password" class="form-control" minlength="6">
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save changes</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body text-center">
                <div class="stat-icon si-indigo mx-auto mb-2" style="width:64px;height:64px;font-size:1.6rem">
                    <i class="bi bi-person"></i>
                </div>
                <h5 class="mb-0"><?= esc($user['name'] ?? '') ?></h5>
                <p class="text-muted"><?= esc($user['email'] ?? '') ?></p>
                <span class="pill pill-muted"><?= esc($user['role'] ?? 'user') ?></span>
                <span class="pill <?= ($user['status'] ?? '') === 'active' ? 'pill-success' : 'pill-danger' ?>">
                    <?= esc($user['status'] ?? '') ?>
                </span>
                <hr>
                <div class="text-muted small text-start">
                    <div>Member since: <?= esc($user['created_at'] ?? '—') ?></div>
                    <div>Last login: <?= esc($user['last_login'] ?? '—') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
