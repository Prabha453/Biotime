<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$step = static function (string $icon, string $title, string $desc, string $href, int $done): string {
    return '<a href="' . esc($href, 'attr') . '" class="wb-step" data-turbo="true">'
        . '<div class="wb-ico"><i class="bi bi-' . $icon . '"></i></div>'
        . '<div class="wb-title">' . esc($title) . '</div>'
        . '<div class="wb-desc">' . esc($desc) . '</div>'
        . '<div class="wb-count' . ($done ? ' ok' : '') . '">' . ($done ? $done . ' set' : 'not set') . '</div>'
        . '</a>';
};
?>
<?= page_head($title, $subtitle) ?>

<h6 class="text-muted small text-uppercase mb-2" style="letter-spacing:.06em">Schedule setting steps</h6>
<div class="wb-flow">
    <?= $step('cup-hot', 'Set Break Time', 'Break windows for employees', route_to('attshift.breaks'), $counts['breaks']) ?>
    <span class="wb-arrow"><i class="bi bi-arrow-right"></i></span>
    <?= $step('clock', 'Set Timetable', 'When employees start and end work', route_to('attshift.timetables'), $counts['timetables']) ?>
    <span class="wb-arrow"><i class="bi bi-arrow-right"></i></span>
    <?= $step('calendar-week', 'Set Shift', 'Rotate timetables over a cycle', route_to('attshift.shifts'), $counts['shifts']) ?>
    <span class="wb-arrow"><i class="bi bi-arrow-right"></i></span>
    <?= $step('diagram-3', 'Set Schedule', 'Assign shifts to employees', route_to('schedules'), $counts['schedules']) ?>
</div>

<div class="row g-3 mt-3">
    <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-2" style="letter-spacing:.06em">Attendance calculation</h6>
        <a href="<?= route_to('attcalc') ?>" class="wb-step wb-wide" data-turbo="true">
            <div class="wb-ico"><i class="bi bi-calculator"></i></div>
            <div class="wb-title">Attendance Calculation</div>
            <div class="wb-desc">Score the punch records against the schedule</div>
            <div class="wb-count<?= $counts['timecards'] ? ' ok' : '' ?>"><?= number_format($counts['timecards']) ?> timecards · <?= number_format($counts['txns']) ?> punches</div>
        </a>
    </div>
    <div class="col-md-6">
        <h6 class="text-muted small text-uppercase mb-2" style="letter-spacing:.06em">Generate report</h6>
        <a href="<?= route_to('att.monthly') ?>" class="wb-step wb-wide" data-turbo="true">
            <div class="wb-ico"><i class="bi bi-file-earmark-bar-graph"></i></div>
            <div class="wb-title">Attendance Report</div>
            <div class="wb-desc">View the total time card of employees</div>
            <div class="wb-count">Monthly timesheet</div>
        </a>
    </div>
</div>

<style>
.wb-flow { display:flex; align-items:stretch; gap:10px; flex-wrap:wrap; }
.wb-step {
    flex:1 1 180px; min-width:180px;
    display:block; text-decoration:none; color:inherit;
    border:1px solid var(--border, #e6e2d8); border-radius:10px;
    padding:20px 16px; background:var(--card, #fff);
    transition:border-color .12s, transform .12s;
}
.wb-step:hover { border-color:var(--primary, #f26522); transform:translateY(-2px); }
.wb-wide { flex-basis:100%; }
.wb-ico { font-size:1.9rem; color:var(--primary, #f26522); margin-bottom:10px; }
.wb-title { font-weight:600; margin-bottom:2px; }
.wb-desc { font-size:12.5px; color:var(--muted, #6d716f); line-height:1.4; }
.wb-count { font-size:11.5px; margin-top:12px; color:var(--muted, #6d716f); font-family:monospace; }
.wb-count.ok { color:var(--bs-success, #198754); }
.wb-arrow { display:flex; align-items:center; color:var(--primary, #f26522); font-size:1.2rem; }
@media (max-width:720px){ .wb-arrow{ transform:rotate(90deg); align-self:center; } }
</style>
<?= $this->endSection() ?>
