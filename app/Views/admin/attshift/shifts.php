<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $hm = static fn ($t) => $t ? substr((string) $t, 0, 5) : ''; ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add', 'modal' => 'shForm'],
    'delete'       => route_to('attshift.shifts.bulk_delete'),
    'export'       => ['key' => 'att_shift'],
    'columns'      => true,
    'filter'       => '#shFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'shift', 'label' => 'Shift'],
]) ?>
<?= filter_builder('shFilter', [
    'alias'      => ['label' => 'Name', 'type' => 'text'],
    'cycle_unit' => ['label' => 'Cycle unit', 'type' => 'select', 'options' => $cycleUnits],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
        <th data-col="name">Name</th>
        <th data-col="cycle">Cycle</th>
        <th data-col="timetables">Timetables</th>
        <th data-col="weekend">Weekend / day-off</th>
        <th data-col="emp" class="text-end">Assigned</th>
        <th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td class="fw-semibold"><?= esc($r['alias']) ?></td>
            <td class="small"><?= (int) $r['shift_cycle'] ?> <?= esc(strtolower($cycleUnits[(int) $r['cycle_unit']] ?? '')) ?><?= (int) $r['shift_cycle'] === 1 ? '' : 's' ?></td>
            <td class="small text-muted">
                <?php if ($r['details']): foreach (array_slice($r['details'], 0, 8) as $d): ?>
                    <span class="badge bg-label-secondary">D<?= (int) $d['day_index'] + 1 ?>: <?= esc($d['alias']) ?></span>
                <?php endforeach; if (count($r['details']) > 8): ?><span class="text-muted">+<?= count($r['details']) - 8 ?></span><?php endif;
                else: ?>—<?php endif; ?>
            </td>
            <td class="small text-muted">
                <?= ($r['work_weekend'] === true || $r['work_weekend'] === 't') ? 'Weekend: ' . esc($otOffday[(int) $r['weekend_type']] ?? 'work') : 'Weekend: off' ?><br>
                <?= ($r['work_day_off'] === true || $r['work_day_off'] === 't') ? 'Day-off: ' . esc($otOffday[(int) $r['day_off_type']] ?? 'work') : 'Day-off: off' ?>
            </td>
            <td class="text-end mono"><?= (int) $r['emp_count'] ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-sh-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('attshift.shifts.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this shift?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-week"></i>No shifts yet.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="shForm" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('attshift.shifts.save'), ['id' => 'shFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Add</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <?= field_text('alias', 'Shift Name', ['col' => 'col-md-6', 'required' => true, 'maxlength' => 50]) ?>
            <div class="col-md-6 d-flex align-items-end"><?= field_switch('auto_shift', 'Auto Shift — pick the best-fitting timetable from the punches', ['checked' => false, 'col' => '']) ?></div>
            <?= field_select('cycle_unit', 'Unit', $cycleUnits, ['col' => 'col-md-3', 'value' => 1, 'id' => 'shCycleUnit']) ?>
            <?= field_number('shift_cycle', 'Cycle', ['col' => 'col-md-3', 'required' => true, 'value' => 1, 'min' => 1, 'id' => 'shCycleLen', 'hint' => 'Day ≤366 · Week ≤52 · Month ≤12']) ?>
        </div>
        <h6 class="mt-3 mb-2 text-muted small text-uppercase">General Setting — timetable per day of the cycle</h6>
        <p class="form-hint">Pick the timetable for each day. Leave a day blank to make it a rest day.</p>
        <div class="table-responsive"><table class="table table-sm align-middle" id="shSlots">
            <thead><tr><th style="width:5rem">Day</th><th>Timetable</th></tr></thead>
            <tbody></tbody>
        </table></div>
        <h6 class="mt-3 mb-2 text-muted small text-uppercase">Non-working days</h6>
        <div class="row g-3">
            <div class="col-md-3 d-flex align-items-end"><?= field_switch('work_weekend', 'Work on weekend', ['checked' => false]) ?></div>
            <?= field_select('weekend_type', 'Count weekend work as', $otOffday, ['col' => 'col-md-3', 'value' => 0]) ?>
            <div class="col-md-3 d-flex align-items-end"><?= field_switch('work_day_off', 'Work on rest day', ['checked' => false]) ?></div>
            <?= field_select('day_off_type', 'Count rest-day work as', $otOffday, ['col' => 'col-md-3', 'value' => 0]) ?>
        </div>

        <h6 class="mt-3 mb-2 text-muted small text-uppercase">Overtime Rule</h6>
        <div class="row g-3">
            <div class="col-12"><?= field_switch('enable_ot_rule', 'Enable overtime rule — tier the day\'s overtime by hours worked', ['checked' => false, 'id' => 'shOtEnable']) ?></div>
        </div>
        <div id="shOtTiers" class="table-responsive mt-2" hidden>
            <table class="table table-sm align-middle">
                <thead><tr><th style="width:5rem">Tier</th><th>Pay Code</th><th style="width:9rem">Hours From</th><th style="width:9rem">Hours To</th></tr></thead>
                <tbody>
                <?php foreach ([1 => 'OT1', 2 => 'OT2', 3 => 'OT3'] as $lv => $label): ?>
                    <tr>
                        <td class="text-muted small"><?= $label ?></td>
                        <td><?= field_select("t{$lv}_pay_code", '', opts_from($otPayCodes, 'id', 'name'), ['col' => '', 'placeholder' => '— none —']) ?></td>
                        <td><?= field_number("t{$lv}_hrs_from", '', ['col' => '', 'step' => '0.5', 'min' => 0, 'value' => 0]) ?></td>
                        <td><?= field_number("t{$lv}_hrs_to", '', ['col' => '', 'step' => '0.5', 'min' => 0, 'value' => 0]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="form-hint">E.g. OT1 = the first 2 hours of daily overtime, OT2 = the next 2, OT3 = beyond that — each paid under its own pay code.</p>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var TT = <?= json_encode(array_map(fn ($t) => ['id' => (int) $t['id'], 'alias' => $t['alias']], $timetables)) ?>;
    var WEEK = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    var body = document.querySelector('#shSlots tbody');
    var unit = document.getElementById('shCycleUnit'), len = document.getElementById('shCycleLen');

    function slotLabel(unitVal, i) {
        if (unitVal === '1') { return WEEK[i % 7] + (i >= 7 ? ' (wk ' + (Math.floor(i / 7) + 1) + ')' : ''); }
        if (unitVal === '2') { return 'Day ' + (i + 1); }
        return 'Day ' + (i + 1);
    }
    function slotCount() {
        var n = Math.max(1, parseInt(len.value, 10) || 1);
        return unit.value === '0' ? n : (unit.value === '1' ? 7 * n : Math.min(31 * n, 62));
    }
    function render(existing) {
        existing = existing || {};
        var count = slotCount();
        body.innerHTML = '';
        for (var i = 0; i < count; i++) {
            var opts = '<option value="">— rest day —</option>' + TT.map(function (t) {
                return '<option value="' + t.id + '"' + (String(existing[i]) === String(t.id) ? ' selected' : '') + '>' + t.alias + '</option>';
            }).join('');
            body.insertAdjacentHTML('beforeend',
                '<tr><td class="small text-muted">' + slotLabel(unit.value, i) + '</td>' +
                '<td><select class="form-select form-select-sm" name="slot[' + i + ']">' + opts + '</select></td></tr>');
        }
    }
    unit.addEventListener('change', function () { render(); });
    len.addEventListener('input', function () { render(); });

    var otEnable = document.getElementById('shOtEnable'), otTiers = document.getElementById('shOtTiers');
    otEnable.addEventListener('change', function () { otTiers.hidden = ! otEnable.checked; });

    function fill(d) {
        var f = document.getElementById('shFormEl');
        f.reset();
        f.querySelector('[name=id]').value = d.id || '';
        f.querySelector('[name=alias]').value = d.alias || '';
        unit.value = d.cycle_unit == null ? '1' : d.cycle_unit;
        len.value = d.shift_cycle || 1;
        f.querySelector('[name=weekend_type]').value = d.weekend_type || 0;
        f.querySelector('[name=day_off_type]').value = d.day_off_type || 0;
        f.querySelector('[name=work_weekend]').checked = d.work_weekend === true || d.work_weekend === 't';
        f.querySelector('[name=work_day_off]').checked = d.work_day_off === true || d.work_day_off === 't';
        f.querySelector('[name=auto_shift]').checked = d.auto_shift == null ? true : String(d.auto_shift) === '1';
        otEnable.checked = d.enable_ot_rule === true || d.enable_ot_rule === 't';
        otTiers.hidden = ! otEnable.checked;
        [1, 2, 3].forEach(function (lv) {
            var row = (d.ot_tiers || []).find(function (t) { return String(t.mode) === String(lv); });
            f.querySelector('[name=t' + lv + '_pay_code]').value = row ? (row.pay_code_id || '') : '';
            f.querySelector('[name=t' + lv + '_hrs_from]').value = row ? row.hrs_from : 0;
            f.querySelector('[name=t' + lv + '_hrs_to]').value = row ? row.hrs_to : 0;
        });
        var ex = {};
        (d.details || []).forEach(function (x) { ex[x.day_index] = x.time_interval_id || x.id; });
        // details from the list query only carry alias/in_time; re-map by alias
        (d.details || []).forEach(function (x) {
            var m = TT.find(function (t) { return t.alias === x.alias; });
            if (m) ex[x.day_index] = m.id;
        });
        render(ex);
    }
    document.querySelectorAll('.js-sh-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            fill(JSON.parse(b.dataset.json || '{}'));
            document.querySelector('#shForm .modal-title').textContent = 'Edit Shift';
            new bootstrap.Modal(document.getElementById('shForm')).show();
        });
    });
    document.querySelector('[data-bs-target="#shForm"]')?.addEventListener('click', function () {
        fill({});
        document.querySelector('#shForm .modal-title').textContent = 'Add Shift';
    });
    render();
})();
</script>
<?= $this->endSection() ?>
