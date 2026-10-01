<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $hm = static fn ($t) => $t ? substr((string) $t, 0, 5) : ''; ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add Normal Timetable', 'modal' => 'ttForm'],
    'right'        => '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ttForm" data-flex="1"><i class="bi bi-plus-lg me-1"></i>Add Flexible Timetable</button>',
    'delete'       => route_to('attshift.timetables.bulk_delete'),
    'export'       => ['key' => 'att_timeinterval'],
    'columns'      => true,
    'filter'       => '#ttFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'timetable', 'label' => 'Timetable'],
]) ?>
<?= filter_builder('ttFilter', [
    'alias'     => ['label' => 'Name', 'type' => 'text'],
    'use_mode'  => ['label' => 'Type', 'type' => 'select', 'options' => $modes],
    'work_type' => ['label' => 'Work Type', 'type' => 'select', 'options' => $workTypes],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
        <th data-col="name">Name</th>
        <th data-col="type">Type</th>
        <th data-col="in">Check-In</th>
        <th data-col="out">Check-Out</th>
        <th data-col="work" class="text-end">Work Time</th>
        <th data-col="break" class="text-end">Break Time</th>
        <th data-col="day" class="text-end">WorkDay</th>
        <th data-col="wtype">Work Type</th>
        <th data-col="ot">Overtime</th>
        <th data-col="comp" class="text-end">Compensate</th>
        <th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $cross = strtotime($hm($r['out_time'])) <= strtotime($hm($r['in_time'])); ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td class="fw-semibold"><?= esc($r['alias']) ?></td>
            <td class="small"><?= esc($modes[(int) $r['use_mode']] ?? '—') ?></td>
            <td class="mono small"><?= esc($hm($r['in_time'])) ?>:00</td>
            <td class="mono small"><?= esc($hm($r['out_time'])) ?>:00<?php if ($cross): ?><sup class="text-primary">+1</sup><?php endif; ?></td>
            <td class="text-end mono"><?= (int) $r['duration'] ?></td>
            <td class="text-end mono"><?= (int) array_sum(array_column($r['breaks'] ?? [], 'duration')) ?></td>
            <td class="text-end mono"><?= rtrim(rtrim(number_format((float) $r['work_day'], 2), '0'), '.') ?></td>
            <td class="small text-muted"><?= esc($workTypes[(int) $r['work_type']] ?? '—') ?></td>
            <td><?= ($r['enable_overtime'] === true || $r['enable_overtime'] === 't')
                ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>' ?></td>
            <td class="text-end mono"><?= (int) ($r['compensate_duration'] ?? 0) ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-tt-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('attshift.timetables.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this timetable?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="12"><div class="empty-state"><i class="bi bi-clock"></i>No timetables yet.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="ttForm" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
    <?= form_open(route_to('attshift.timetables.save'), ['id' => 'ttFormEl']) ?>
    <input type="hidden" name="id" value="">
    <input type="hidden" name="use_mode" id="ttMode" value="0">
    <div class="modal-header"><h5 class="modal-title">Add Normal Timetable</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3 mb-2">
            <?= field_text('alias', 'Name', ['col' => 'col-md-6', 'required' => true, 'maxlength' => 50]) ?>
            <div class="col-md-6"><label class="form-label">Work Hours Assign To <?= req() ?></label>
                <input class="form-control" value="Regular" readonly></div>
        </div>
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ttBasic" type="button">Basic Settings</button></li>
            <li class="nav-item tt-normal-only"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ttBreak" type="button">BreakTime Settings</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ttUnsched" type="button">Unscheduled Time Settings</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ttOt" type="button">Overtime Rule</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ttRule" type="button">Rule Settings</button></li>
            <li class="nav-item tt-normal-only"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ttComp" type="button">Compensate Setting</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="ttBasic">
                <div class="row g-3">
                    <?= field_time('in_time', 'Check-In', ['col' => 'col-md-3', 'required' => true, 'value' => '09:00']) ?>
                    <?= field_time('out_time', 'Check-Out', ['col' => 'col-md-3', 'required' => true, 'value' => '18:00']) ?>
                    <?= field_number('cross_days', 'Check-Out Cross Days', ['col' => 'col-md-3', 'value' => 0, 'min' => 0, 'max' => 1]) ?>
                    <?= field_number('work_day', 'WorkDay (days)', ['col' => 'col-md-3', 'required' => true, 'value' => 1, 'step' => '0.5']) ?>
                    <div class="col-12 tt-normal-only"><hr class="my-1"><p class="form-hint">Valid punch windows — a punch outside these is not paired as the check-in / check-out.</p></div>
                    <?php foreach ([
                        ['in_start', 'Check-In Start Time', '08:00'], ['in_end', 'Check-In End Time', '10:00'],
                        ['out_start', 'Check-Out Start Time', '17:00'], ['out_end', 'Check-Out End Time', '19:00'],
                    ] as $w): ?>
                        <div class="col-md-3 tt-normal-only"><?= field_time($w[0], $w[1], ['value' => $w[2]]) ?></div>
                    <?php endforeach; ?>
                    <?= field_select('work_type', 'Work Type', $workTypes, ['col' => 'col-md-3', 'value' => 0]) ?>
                    <div class="col-md-3"><label class="form-label">Color Settings</label>
                        <input type="color" name="color_setting" class="form-control form-control-color" value="#7ac142"></div>
                    <div class="col-md-3 tt-flex-only"><?= field_number('flex_work_time', 'Work Time (min)', ['value' => 480, 'hint' => 'Flexible: expected minutes per day.']) ?></div>
                </div>
            </div>
            <div class="tab-pane fade tt-normal-only" id="ttBreak">
                <div class="table-responsive"><table class="table table-sm align-middle">
                    <thead><tr><th style="width:2rem"></th><th>Name</th><th>Start Time</th><th>End Time</th><th class="text-end">Duration</th><th>With Salary</th><th>Calculation Type</th></tr></thead>
                    <tbody>
                    <?php $sal = ['Unpaid', 'Paid']; $ct = ['Auto Deduct', 'Required Punch']; foreach ($breakList as $bk):
                        $st = substr((string) $bk['period_start'], 0, 5);
                        $en = date('H:i', strtotime($st) + ((int) $bk['duration'] + (int) ($bk['break_in_above_margin'] ?? 0)) * 60); ?>
                        <tr>
                            <td><input class="form-check-input tt-break" type="checkbox" name="break_ids[]" value="<?= (int) $bk['id'] ?>"></td>
                            <td class="fw-semibold"><?= esc($bk['alias']) ?></td>
                            <td class="mono small"><?= esc($st) ?>:00</td>
                            <td class="mono small"><?= esc($en) ?>:00</td>
                            <td class="text-end mono"><?= (int) $bk['duration'] ?></td>
                            <td class="small"><?= esc($sal[(int) ($bk['with_salary'] ?? 1)] ?? '') ?></td>
                            <td class="small"><?= esc($ct[(int) ($bk['calc_type'] ?? 0)] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $breakList): ?><tr><td colspan="7" class="text-muted small">No break times defined — add them under Break Time first.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
            <div class="tab-pane fade" id="ttUnsched">
                <div class="d-flex flex-column gap-3">
                    <?= field_switch('enable_early_in', 'Early In — count time worked before Check-In', ['checked' => false]) ?>
                    <?= field_switch('enable_late_out', 'Late Out — count time worked after Check-Out', ['checked' => false]) ?>
                    <p class="form-hint mb-0">When off, early-in / late-out minutes are ignored (not paid, not OT).</p>
                </div>
            </div>
            <div class="tab-pane fade" id="ttOt">
                <div class="row g-3">
                    <div class="col-12"><?= field_switch('enable_overtime', 'Overtime', ['checked' => false]) ?></div>
                    <?= field_select('overtime_policy', 'Overtime Approval', $otPolicy, ['col' => 'col-md-4', 'value' => -1]) ?>
                    <?= field_select('ot_pay_code_id', 'Overtime Pay Code', ['' => '—'] + opts_from($payCodes, 'id', 'code'), ['col' => 'col-md-4']) ?>
                    <div class="col-md-4 d-flex align-items-end gap-2"><?= field_switch('enable_max_ot_limit', 'Max OT', ['checked' => false]) ?></div>
                    <?= field_number('max_ot_limit', 'Max OT (min)', ['col' => 'col-md-4', 'value' => 240]) ?>
                    <?= field_number('overtime_lv1', 'OT Level 1 (hrs)', ['col' => 'col-md-4', 'value' => 0]) ?>
                    <?= field_number('overtime_lv2', 'OT Level 2 (hrs)', ['col' => 'col-md-4', 'value' => 0]) ?>
                    <?= field_number('overtime_lv3', 'OT Level 3 (hrs)', ['col' => 'col-md-4', 'value' => 0]) ?>
                </div>
            </div>
            <div class="tab-pane fade" id="ttRule">
                <div class="row g-3">
                    <?= field_select('in_required', 'Clock-In Is Required', ['1' => 'Yes', '0' => 'No'], ['col' => 'col-md-4', 'value' => '1']) ?>
                    <?= field_select('out_required', 'Clock-Out Is Required', ['1' => 'Yes', '0' => 'No'], ['col' => 'col-md-4', 'value' => '1']) ?>
                    <?= field_select('func_key', 'Punch State Based', ['0' => 'No', '1' => 'Yes'], ['col' => 'col-md-4', 'value' => '0']) ?>
                    <?= field_number('allow_late', 'Allow Late-In (min)', ['col' => 'col-md-4', 'value' => 0]) ?>
                    <?= field_number('allow_leave_early', 'Allow Early-Out (min)', ['col' => 'col-md-4', 'value' => 0]) ?>
                    <?= field_select('multiple_punch', 'Multiple In/Out', ['0' => 'No', '1' => 'Yes'], ['col' => 'col-md-4', 'value' => '0']) ?>
                    <?= field_time('day_change', 'Day Change Time', ['col' => 'col-md-4', 'value' => '00:00']) ?>
                </div>
            </div>
            <div class="tab-pane fade tt-normal-only" id="ttComp">
                <?= field_number('compensate_duration', 'Compensate Duration (min)', ['col' => 'col-md-4', 'value' => 0, 'hint' => 'Forgives up to N minutes of lateness and pushes the expected clock-out out by the same amount.']) ?>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Confirm</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var m = document.getElementById('ttForm'), f = document.getElementById('ttFormEl');
    var mins = function (t) { var x = (t || '00:00').slice(0, 5).split(':'); return (+x[0]) * 60 + (+x[1] || 0); };
    var hhmm = function (n) { n = ((n % 1440) + 1440) % 1440; return ('0' + Math.floor(n / 60)).slice(-2) + ':' + ('0' + (n % 60)).slice(-2); };

    function setMode(flex) {
        document.getElementById('ttMode').value = flex ? '1' : '0';
        m.querySelectorAll('.tt-normal-only').forEach(function (e) { e.classList.toggle('d-none', !!flex); });
        m.querySelectorAll('.tt-flex-only').forEach(function (e) { e.classList.toggle('d-none', !flex); });
        m.querySelector('.modal-title').textContent = (f.querySelector('[name=id]').value ? 'Edit ' : 'Add ') + (flex ? 'Flexible' : 'Normal') + ' Timetable';
    }
    function reset() {
        f.reset();
        f.querySelector('[name=id]').value = '';
        m.querySelector('[data-bs-target="#ttBasic"]').click();
    }
    function fill(d) {
        reset();
        var flex = String(d.use_mode) === '1';
        f.querySelector('[name=id]').value = d.id || '';
        f.querySelector('[name=alias]').value = d.alias || '';
        f.querySelector('[name=in_time]').value = (d.in_time || '09:00:00').slice(0, 5);
        var inM = mins(d.in_time), dur = +d.duration || 480;
        f.querySelector('[name=out_time]').value = hhmm(inM + dur);
        f.querySelector('[name=cross_days]').value = (inM + dur) >= 1440 ? 1 : 0;
        f.querySelector('[name=in_start]').value  = hhmm(inM - (+d.in_ahead_margin || 0));
        f.querySelector('[name=in_end]').value    = hhmm(inM + (+d.in_above_margin || 0));
        f.querySelector('[name=out_start]').value = hhmm(inM + dur - (+d.out_ahead_margin || 0));
        f.querySelector('[name=out_end]').value   = hhmm(inM + dur + (+d.out_above_margin || 0));
        f.querySelector('[name=flex_work_time]').value = d.work_time_duration || dur;
        f.querySelector('[name=day_change]').value = (d.day_change || '00:00:00').slice(0, 5);
        f.querySelector('[name=color_setting]').value = d.color_setting || '#7ac142';
        ['work_day','work_type','allow_late','allow_leave_early','overtime_policy','ot_pay_code_id','max_ot_limit','overtime_lv1','overtime_lv2','overtime_lv3','compensate_duration'].forEach(function (k) {
            var el = f.querySelector('[name="' + k + '"]'); if (el) el.value = d[k] == null ? '' : d[k];
        });
        [['in_required','1'],['out_required','1'],['func_key','0'],['multiple_punch','0']].forEach(function (p) {
            var el = f.querySelector('[name="' + p[0] + '"]'); if (el) el.value = d[p[0]] == null ? p[1] : String(d[p[0]]);
        });
        f.querySelector('[name=enable_overtime]').checked = d.enable_overtime === true || d.enable_overtime === 't';
        f.querySelector('[name=enable_max_ot_limit]').checked = d.enable_max_ot_limit === true || d.enable_max_ot_limit === 't';
        f.querySelector('[name=enable_early_in]').checked = d.enable_early_in === true || d.enable_early_in === 't';
        f.querySelector('[name=enable_late_out]').checked = d.enable_late_out === true || d.enable_late_out === 't';
        var have = (d.breaks || []).map(function (x) { return String(x.id); });
        f.querySelectorAll('.tt-break').forEach(function (c) { c.checked = have.indexOf(c.value) > -1; });
        setMode(flex);
    }
    document.querySelectorAll('.js-tt-edit').forEach(function (b) {
        b.addEventListener('click', function () { fill(JSON.parse(b.dataset.json || '{}')); new bootstrap.Modal(m).show(); });
    });
    m.addEventListener('show.bs.modal', function (ev) {
        if (ev.relatedTarget && !ev.relatedTarget.classList.contains('js-tt-edit')) {
            reset();
            setMode(ev.relatedTarget.getAttribute('data-flex') === '1');
        }
    });
})();
</script>
<?= $this->endSection() ?>
