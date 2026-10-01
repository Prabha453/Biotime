<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Portal &middot; TimeTrack</title>
    <link href="<?= base_url('assets/vendor/fonts/fonts.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <style>
        body { font-family: Roboto, system-ui, sans-serif; min-height: 100vh; display: grid; place-items: center;
               background: linear-gradient(135deg, #F26522, #b5470f); margin: 0; }
        .login-card { width: 100%; max-width: 380px; background: #fff; border-radius: .9rem; padding: 2rem;
                      box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .brand { font-weight: 700; font-size: 1.15rem; color: #F26522; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="brand mb-1"><i class="bi bi-fingerprint me-1"></i>TimeTrack</div>
    <div class="text-muted small mb-3">Employee self-service sign-in</div>

    <?php if ($e = session()->getFlashdata('error')): ?>
        <div class="alert alert-danger py-2 small"><?= esc($e) ?></div>
    <?php endif; ?>
    <?php foreach (session()->getFlashdata('errors') ?? [] as $er): ?>
        <div class="alert alert-danger py-2 small"><?= esc($er) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= route_to('portal.login') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Employee code</label>
            <input name="emp_code" class="form-control" autofocus required value="<?= esc(old('emp_code')) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn w-100 text-white" style="background:#F26522">Sign in</button>
    </form>
    <div class="text-center mt-3 small">
        <a href="<?= site_url('login') ?>" class="text-muted">Administrator sign-in</a>
    </div>
</div>
</body>
</html>
