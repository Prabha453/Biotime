<?php
/**
 * Shared front-end libraries — all self-hosted under public/assets/vendor
 * (no CDN, so the app works on an offline / LAN-only ZKBioTime box).
 * Load tt.js + Turbo AFTER this from the layout (order matters).
 *
 *   Bootstrap 5.3.8 · Bootstrap Icons 1.13.1 · Chart.js 4.5.1
 *   Tom Select 2.6.2 · Flatpickr 4.6.13
 */
$v = static fn (string $p) => base_url('assets/vendor/' . $p);
?>
<link href="<?= $v('fonts/fonts.css') ?>" rel="stylesheet">
<link href="<?= $v('bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= $v('bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= $v('tom-select/tom-select.bootstrap5.min.css') ?>" rel="stylesheet">
<link href="<?= $v('flatpickr/flatpickr.min.css') ?>" rel="stylesheet">
<script src="<?= $v('bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $v('chartjs/chart.umd.min.js') ?>"></script>
<script src="<?= $v('tom-select/tom-select.complete.min.js') ?>"></script>
<script src="<?= $v('flatpickr/flatpickr.min.js') ?>"></script>
