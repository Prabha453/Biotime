<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in &middot; TimeTrack</title>
    <link href="<?= base_url('assets/vendor/fonts/fonts.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') . '?v=' . @filemtime(FCPATH . 'assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="brand-mark"><i class="bi bi-fingerprint"></i></div>
        <h4 class="fw-bold mb-1">TimeTrack</h4>
        <p class="text-muted mb-4">Sign in with your ZKBio&nbsp;Time credentials.</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success py-2"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php $errors = session()->getFlashdata('errors') ?? []; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?= form_open(route_to('login'), ['autocomplete' => 'off']) ?>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" required autofocus
                           autocapitalize="none" autocomplete="username"
                           value="<?= esc(old('username', 'admin')) ?>" placeholder="admin">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" required
                           placeholder="••••••••" value="">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
            </button>
        <?= form_close() ?>

        <p class="text-center text-muted small mt-4 mb-0">
            Same login as the ZKBio&nbsp;Time console.<br>
            <a href="<?= site_url('portal/login') ?>">Employee self-service portal</a>
        </p>
    </div>
</div>
</body>
</html>
