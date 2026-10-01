<?php
/** @var string $title */
$active = $active ?? '';
$name   = session()->get('staffName') ?? 'Employee';
$code   = session()->get('staffCode') ?? '';
$nav    = [
    ['dashboard',  'My Dashboard', 'grid-1x2',        route_to('portal')],
    ['attendance', 'Attendance',   'calendar-check',  route_to('portal.attendance')],
    ['payslips',   'Payslips',     'cash-stack',      route_to('portal.payslips')],
    ['requests',   'Requests',     'inbox',           route_to('portal.requests')],
    ['profile',    'Profile',      'person-circle',   route_to('portal.profile')],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <script>(function(){try{var t=localStorage.getItem('tt-theme');if(t){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
    <title><?= esc($title ?? 'Staff Portal') ?> &middot; TimeTrack</title>
    <?= $this->include('partials/head_libs') ?>
    <link href="<?= base_url('assets/css/app.css') . '?v=' . @filemtime(FCPATH . 'assets/css/app.css') ?>" rel="stylesheet">
    <script src="<?= base_url('assets/js/tt.js') . '?v=' . @filemtime(FCPATH . 'assets/js/tt.js') ?>"></script>
    <script src="<?= base_url('assets/vendor/turbo/turbo.umd.min.js') ?>"></script>
    <style>
        body { font-family: Roboto, system-ui, sans-serif; background: var(--bs-body-bg, #f4f5f7); }
        .portal-top { background: #F26522; color: #fff; }
        .portal-top a { color: rgba(255,255,255,.85); text-decoration: none; padding: .35rem .7rem; border-radius: .4rem; font-size: .9rem; white-space: nowrap; }
        .portal-top a.is-active, .portal-top a:hover { background: rgba(255,255,255,.18); color: #fff; }
        .portal-wrap { max-width: 1080px; margin: 0 auto; padding: 1.25rem; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; }
        .stat-grid .card .h4 { margin: 0; }
    </style>
</head>
<body>
<div class="portal-top">
    <div class="portal-wrap d-flex flex-wrap align-items-center gap-2 py-2">
        <span class="fw-bold me-2"><i class="bi bi-fingerprint me-1"></i>TimeTrack</span>
        <?php foreach ($nav as $n): ?>
            <a class="<?= $active === $n[0] ? 'is-active' : '' ?>" href="<?= $n[3] ?>"><i class="bi bi-<?= $n[2] ?> me-1"></i><?= esc($n[1]) ?></a>
        <?php endforeach; ?>
        <span class="ms-auto small d-flex align-items-center gap-2">
            <span><?= esc($name) ?> <span class="opacity-75">· <?= esc($code) ?></span></span>
            <a href="<?= route_to('portal.logout') ?>" class="border border-light-subtle" data-turbo="false"><i class="bi bi-box-arrow-right"></i></a>
        </span>
    </div>
</div>

<div class="portal-wrap">
    <h1 class="h4 mb-3"><?= esc($title ?? '') ?></h1>
    <?php $vErrors = session()->getFlashdata('errors') ?? []; ?>
    <?php if ($vErrors): ?>
        <div class="alert alert-danger"><strong>Please fix:</strong>
            <ul class="mb-0 mt-1 ps-3"><?php foreach ($vErrors as $e): ?><li><?= esc($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $k => $cls): ?>
        <?php if ($m = session()->getFlashdata($k)): ?>
            <div class="alert alert-<?= $cls ?>"><?= esc($m) ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?= $this->renderSection('content') ?>
</div>

<?= $this->renderSection('scripts') ?>
</body>
</html>
