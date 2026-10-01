<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>

<div class="subnav mb-3">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= route_to('attconfig.tab', $k) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'global'): ?>
<?php
$dowOpts = [7 => 'None'] + $dow;
$g = static fn (string $k, $d = '') => $pol[$k] ?? $d;
$gt = static fn (string $k) => in_array($pol[$k] ?? false, [true, 't', '1', 1], true);
?>
<?= form_open(route_to('attconfig.global.save')) ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar3 me-1"></i>Week &amp; day boundary</span>
                <span class="badge bg-label-info fw-normal"><?= (int) $weekendCount ?> weekend day<?= $weekendCount === 1 ? '' : 's' ?> in <?= esc($currentMonthLabel) ?></span>
            </h6>
            <div class="row g-3">
                <?= field_select('weekend1', 'Weekend day 1', $dowOpts, ['col' => 'col-sm-6', 'value' => (int) $g('weekend1', 7)]) ?>
                <?= field_select('weekend2', 'Weekend day 2', $dowOpts, ['col' => 'col-sm-6', 'value' => (int) $g('weekend2', 7)]) ?>
                <?= field_select('start_of_week', 'First day of the week', $dow, ['col' => 'col-sm-6', 'value' => (int) $g('start_of_week', 0), 'hint' => 'Saved for the Weekly Report, which hasn\'t been built yet.']) ?>
                <?= field_time('day_change', 'Day-change time', ['col' => 'col-sm-6', 'value' => substr((string) $g('day_change', '00:00:00'), 0, 5), 'hint' => 'A punch before this hour counts on the previous day.']) ?>
                <?= field_number('max_hrs', 'Max hours counted per day', ['col' => 'col-sm-6', 'value' => rtrim(rtrim(number_format((float) $g('max_hrs', 20), 1), '0'), '.'), 'step' => '0.5', 'min' => 1, 'max' => 24, 'hint' => 'Flexible-day work-fraction denominator.']) ?>
                <?= field_number('punch_period', 'Duplicate-punch window (min)', ['col' => 'col-sm-6', 'value' => (int) $g('punch_period', 1), 'min' => 0, 'hint' => 'Repeat punches inside this window are merged.']) ?>
                <?= field_select('paring_rule', 'Punch pairing', $paring, ['col' => 'col-12', 'value' => (int) $g('paring_rule', 0), 'hint' => 'Saved but not yet applied — scheduled days already pair by check-in/out window; unscheduled days already prefer punch state automatically.']) ?>
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-hourglass-split me-1"></i>Overtime</h6>
            <div class="row g-3">
                <div class="col-12"><?= field_switch('use_ot', 'Enable overtime calculation', ['checked' => $gt('use_ot')]) ?></div>
                <?= field_select('overtime_policy', 'Overtime policy', $otPolicy, ['col' => 'col-sm-6', 'value' => (int) $g('overtime_policy', 2), 'hint' => '"Needs approval" only credits OT with an approved request; automatic detection is off.']) ?>
                <?= field_select('ot_pay_code_id', 'Overtime pay code', ['' => '— none —'] + opts_from($payCodes, 'id', 'name'), ['col' => 'col-sm-6', 'value' => $g('ot_pay_code_id'), 'hint' => 'Not wired into payroll yet — currently informational only.']) ?>
                <div class="col-12 d-flex flex-wrap gap-4 pt-1">
                    <?= field_switch('daily_ot', 'Daily OT', ['checked' => $gt('daily_ot')]) ?>
                    <?= field_switch('weekly_ot', 'Weekly OT', ['checked' => $gt('weekly_ot')]) ?>
                    <?= field_switch('weekend_ot', 'Weekend OT', ['checked' => $gt('weekend_ot')]) ?>
                    <?= field_switch('holiday_ot', 'Holiday OT', ['checked' => $gt('holiday_ot')]) ?>
                </div>
                <p class="form-hint col-12 mb-0"><i class="bi bi-info-circle me-1"></i>Weekly OT isn't implemented yet (it needs cross-day weekly totals) — the switch saves but has no effect.</p>
                <div class="col-12"><?= field_switch('enable_compensatory', 'Allow compensatory (time-off in lieu)', ['checked' => $gt('enable_compensatory')]) ?></div>
                <p class="form-hint col-12 mb-0"><i class="bi bi-info-circle me-1"></i>Compensatory time-off isn't implemented yet — OT is always paid, never banked as leave.</p>
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-exclamation-triangle me-1"></i>Late / early / absence</h6>
            <div class="row g-3">
                <?= field_select('miss_in', 'Missing Check-In as', $missInModes, ['col' => 'col-sm-6', 'value' => (int) $g('miss_in', 1)]) ?>
                <?= field_number('late_in_hrs', 'Missing check-in penalty (min)', ['col' => 'col-sm-6', 'value' => (int) $g('late_in_hrs', 0), 'min' => 0, 'id' => 'id_late_in_hrs', 'hint' => 'Only applied when Missing Check-In is set to Late.']) ?>
                <?= field_select('miss_out', 'Missing Check-Out as', $missOutModes, ['col' => 'col-sm-6', 'value' => (int) $g('miss_out', 1)]) ?>
                <?= field_number('early_out_hrs', 'Missing check-out penalty (min)', ['col' => 'col-sm-6', 'value' => (int) $g('early_out_hrs', 0), 'min' => 0, 'id' => 'id_early_out_hrs', 'hint' => 'Only applied when Missing Check-Out is set to Early Leave.']) ?>
                <?= field_number('late_in2absence', 'Late-in ≥ this → mark absent (min)', ['col' => 'col-sm-6', 'value' => (int) $g('late_in2absence', 100), 'min' => 0]) ?>
                <?= field_number('early_out2absence', 'Early-out ≥ this → mark absent (min)', ['col' => 'col-sm-6', 'value' => (int) $g('early_out2absence', 100), 'min' => 0]) ?>
                <div class="col-12">
                    <p class="form-hint mb-2"><i class="bi bi-info-circle me-1"></i>The three limits below are saved but not enforced yet — the scheduled alert-email feature they belong to hasn't been built.</p>
                </div>
                <?= field_number('max_late_in', 'Max late-ins / month (0 = no limit)', ['col' => 'col-sm-4', 'value' => (int) $g('max_late_in', 0), 'min' => 0]) ?>
                <?= field_number('max_early_out', 'Max early-outs / month', ['col' => 'col-sm-4', 'value' => (int) $g('max_early_out', 0), 'min' => 0]) ?>
                <?= field_number('max_absent', 'Max absences / month', ['col' => 'col-sm-4', 'value' => (int) $g('max_absent', 0), 'min' => 0]) ?>
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6 class="mb-3"><i class="bi bi-fingerprint me-1"></i>Punch requirements</h6>
            <div class="d-flex flex-column gap-2">
                <?= field_switch('require_punch_state', 'Require an in / out state on every punch', ['checked' => $gt('require_punch_state')]) ?>
                <?= field_switch('require_work_code', 'Require a work code on every punch', ['checked' => $gt('require_work_code')]) ?>
                <?= field_switch('require_capture', 'Require a photo capture on every punch', ['checked' => $gt('require_capture')]) ?>
            </div>
            <p class="form-hint mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Saved but not enforced yet — they belong on a punch-submission screen (mobile / web punch / kiosk), which doesn't exist in this app yet.</p>
        </div></div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save global rule</button>
    </div>
</div>
<?= form_close() ?>

<?php else: ?>
<div class="card" style="max-width:640px"><div class="card-body">
    <?= form_open(route_to('attconfig.reporting.save')) ?>
    <div class="mb-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="filter_by_hire_date" value="1" <?= ! empty($setting['filter_by_hire_date']) ? 'checked' : '' ?>>
        <span class="form-check-label">Only include days on or after each employee's hire date</span></label></div>
    <div class="mb-3"><label class="form-label tiny">Resigned employees in reports</label>
        <select name="resign_emp" class="form-select">
            <option value="0" <?= (int) ($setting['resign_emp'] ?? 0) === 0 ? 'selected' : '' ?>>Exclude</option>
            <option value="1" <?= (int) ($setting['resign_emp'] ?? 0) === 1 ? 'selected' : '' ?>>Include up to resign date</option>
            <option value="2" <?= (int) ($setting['resign_emp'] ?? 0) === 2 ? 'selected' : '' ?>>Always include</option>
        </select></div>
    <div class="row g-2 mb-3">
        <div class="col"><label class="form-label tiny">Date format</label>
            <select name="short_date" class="form-select"><option value="0" <?= (int)($setting['short_date']??0)===0?'selected':'' ?>>Full</option><option value="1" <?= (int)($setting['short_date']??0)===1?'selected':'' ?>>Short (dd)</option></select></div>
        <div class="col"><label class="form-label tiny">Time format</label>
            <select name="short_time" class="form-select"><option value="0" <?= (int)($setting['short_time']??0)===0?'selected':'' ?>>HH:MM</option><option value="1" <?= (int)($setting['short_time']??0)===1?'selected':'' ?>>Decimal hours</option></select></div>
    </div>
    <div class="mb-3"><label class="form-check"><input type="checkbox" class="form-check-input" name="auto_calculate" value="1" <?= ! empty($setting['auto_calculate']) ? 'checked' : '' ?>>
        <span class="form-check-label">Auto-recalculate attendance nightly</span></label></div>
    <div class="mb-3"><label class="form-label tiny">Nightly recalculation time</label>
        <input name="calculate_time" type="time" class="form-control" style="max-width:160px" value="<?= esc(substr((string) ($setting['calculate_time'] ?? '02:00'), 0, 5)) ?>"></div>
    <button class="btn btn-primary">Save report setting</button>
    <?= form_close() ?>
</div></div>
<p class="ext-note mt-2"><i class="bi bi-info-circle"></i> These settings feed the exported ZKBio-style reports and the nightly recalculation job.</p>
<?php endif; ?>

<?= $this->endSection() ?>
<?php if ($tab === 'global'): ?>
<?= $this->section('scripts') ?>
<script>
(function () {
    function sync(selId, fieldId) {
        var sel = document.getElementById(selId), f = document.getElementById(fieldId);
        if (!sel || !f) return;
        var apply = function () { f.disabled = sel.value !== '1'; };
        sel.addEventListener('change', apply);
        apply();
    }
    sync('f_miss_in', 'id_late_in_hrs');
    sync('f_miss_out', 'id_early_out_hrs');
})();
</script>
<?= $this->endSection() ?>
<?php endif; ?>
