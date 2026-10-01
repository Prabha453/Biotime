<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle ?? '') ?>

<?php $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Attendance engine</span>
                <span class="text-muted small">att_attpolicy + base_sysparam</span>
            </div>
            <div class="card-body">
                <?php if (! $canEdit): ?>
                    <p class="ext-note mb-3"><i class="bi bi-lock me-1"></i>Only an <strong>admin</strong> can change these.</p>
                <?php endif; ?>

                <?= form_open(route_to('settings.save')) ?>
                <fieldset <?= $canEdit ? '' : 'disabled' ?>>

                    <label class="form-label">Weekend days</label>
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <?php foreach ($days as $i => $d): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="weekend_days[]" value="<?= $i ?>"
                                       id="wd<?= $i ?>" <?= in_array($i, $pol->weekendDays, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wd<?= $i ?>"><?= $d ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="overtime_enabled" value="1"
                                       id="otEn" <?= $pol->overtimeEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label" for="otEn">Overtime enabled</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Incomplete-day pay</label>
                            <select name="incomplete_policy" class="form-select">
                                <?php foreach (['present' => 'Pay as present', 'half' => 'Pay half', 'absent' => 'Treat as absent'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $pol->incompletePolicy === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <div class="row g-3">
                        <?php
                        $vals = [
                            'day_change_hour'      => $pol->dayChangeHour,
                            'extra_late_grace'     => $pol->extraLateGrace,
                            'extra_early_grace'    => $pol->extraEarlyGrace,
                        ];
                        foreach ($kvLabels as $k => $label): ?>
                            <div class="col-sm-6 col-md-4">
                                <label class="form-label small"><?= esc($label) ?></label>
                                <input type="number" min="0" class="form-control" name="<?= $k ?>" value="<?= (int) $vals[$k] ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($canEdit): ?>
                        <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save settings</button></div>
                    <?php endif; ?>
                </fieldset>
                <?= form_close() ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Device timetable</span><span class="text-muted small">att_timeinterval</span>
            </div>
            <div class="card-body">
                <?php if ($timetable): ?>
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-muted">Name</dt><dd class="col-6"><?= esc($timetable['alias']) ?></dd>
                        <dt class="col-6 text-muted">Check-in</dt><dd class="col-6 mono"><?= esc(substr((string) $timetable['in_time'], 0, 5)) ?></dd>
                        <dt class="col-6 text-muted">Work time</dt><dd class="col-6 mono"><?= mins((int) $timetable['duration']) ?> h</dd>
                        <dt class="col-6 text-muted">Late grace</dt><dd class="col-6"><?= (int) $timetable['allow_late'] ?> min</dd>
                        <dt class="col-6 text-muted">Early grace</dt><dd class="col-6"><?= (int) $timetable['allow_leave_early'] ?> min</dd>
                        <dt class="col-6 text-muted">In window</dt><dd class="col-6">−<?= (int) $timetable['in_ahead_margin'] ?> / +<?= (int) $timetable['in_above_margin'] ?> min</dd>
                        <dt class="col-6 text-muted">Out window</dt><dd class="col-6">−<?= (int) $timetable['out_ahead_margin'] ?> / +<?= (int) $timetable['out_above_margin'] ?> min</dd>
                    </dl>
                    <p class="ext-note mt-3 mb-0">Edit timetables under <a href="<?= route_to('shifts') ?>">Shifts &amp; Timetables</a>.</p>
                <?php else: ?>
                    <p class="text-muted mb-0">No timetable configured.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Timezone</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6 text-muted">Engine / DB session</dt><dd class="col-6 mono"><?= esc($pol->timezone) ?></dd>
                </dl>
                <p class="ext-note mt-2 mb-0">Set in <span class="mono">app/Config/Attendance.php</span>.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
