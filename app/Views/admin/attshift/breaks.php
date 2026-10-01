<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php $hm = static fn ($t) => $t ? substr((string) $t, 0, 5) : ''; ?>
<?= page_head($title, $subtitle) ?>
<?= grid_toolbar([
    'add'          => ['label' => 'Add', 'modal' => 'btForm'],
    'delete'       => route_to('attshift.breaks.bulk_delete'),
    'export'       => ['key' => 'att_breaktime'],
    'columns'      => true,
    'filter'       => '#btFilterPanel',
    'filter_count' => (int) ($fbApplied ?? 0),
    'history'      => ['prefix' => 'att.breaktime', 'label' => 'Break Time'],
]) ?>
<?= filter_builder('btFilter', [
    'alias'       => ['label' => 'Name', 'type' => 'text'],
    'calc_type'   => ['label' => 'Calculation Type', 'type' => 'select', 'options' => $calcTypes],
    'with_salary' => ['label' => 'With Salary', 'type' => 'select', 'options' => $salary],
], ['button' => false]) ?>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr>
        <th style="width:2.2rem"><input type="checkbox" class="form-check-input" id="gridCheckAll"></th>
        <th data-col="name">Name</th>
        <th data-col="start_date">Start Date</th>
        <th data-col="end_date">End Date</th>
        <th data-col="start">Break-Out</th>
        <th data-col="end">Break-In</th>
        <th data-col="duration" class="text-end">Duration</th>
        <th data-col="salary">With Salary</th>
        <th data-col="calc">Calculation Type</th>
        <th class="text-end">Action</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" class="form-check-input grid-check" value="<?= (int) $r['id'] ?>"></td>
            <td class="fw-semibold"><?= esc($r['alias']) ?></td>
            <td class="mono small"><?= esc($r['start_date'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="mono small"><?= esc($r['end_date'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
            <td class="mono small"><?= esc($hm($r['period_start'])) ?>:00</td>
            <td class="mono small"><?= esc($hm($r['break_in_time'])) ?>:00</td>
            <td class="text-end mono"><?= (int) $r['duration'] ?></td>
            <td><?= (int) $r['with_salary'] === 1
                ? '<i class="bi bi-check-circle-fill text-success"></i>'
                : '<i class="bi bi-dash-circle text-muted"></i>' ?></td>
            <td class="small"><?= esc($calcTypes[(int) $r['calc_type']] ?? '—') ?></td>
            <td class="text-end" style="white-space:nowrap">
                <button class="icon-ghost js-bt-edit" title="Edit" data-json='<?= esc(json_encode($r), 'attr') ?>'><i class="bi bi-pencil"></i></button>
                <?= form_open(route_to('attshift.breaks.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Delete this break time?', 'data-confirm-danger' => '']) ?>
                    <button class="icon-ghost"><i class="bi bi-trash"></i></button>
                <?= form_close() ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (! $rows): ?><tr><td colspan="10"><div class="empty-state"><i class="bi bi-cup-hot"></i>No break times yet.</div></td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= pager_bar($pager) ?>

<div class="modal fade" id="btForm" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <?= form_open(route_to('attshift.breaks.save'), ['id' => 'btFormEl']) ?>
    <input type="hidden" name="id" value="">
    <div class="modal-header"><h5 class="modal-title">Add</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3 mb-2">
            <?= field_text('alias', 'Name', ['col' => 'col-md-3', 'required' => true, 'maxlength' => 50]) ?>
            <?= field_select('calc_type', 'Calculation Type', $calcTypes, ['col' => 'col-md-3', 'required' => true, 'value' => 0]) ?>
            <?= field_date('start_date', 'Start Date', ['col' => 'col-md-3', 'hint' => 'Blank = always effective.']) ?>
            <?= field_date('end_date', 'End Date', ['col' => 'col-md-3', 'hint' => 'Blank = no expiry.']) ?>
        </div>
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#btBasic" type="button">Basic Settings</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#btRule" type="button">Rule Settings</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="btBasic">
                <div class="row g-3">
                    <?= field_time('period_start', 'Break-Out', ['col' => 'col-md-3', 'required' => true, 'value' => '12:00']) ?>
                    <?= field_time('break_in', 'Break-In', ['col' => 'col-md-3', 'required' => true, 'value' => '13:00']) ?>
                    <?= field_number('break_cross_days', 'Break-In Cross Days', ['col' => 'col-md-3', 'value' => 0, 'min' => 0, 'max' => 1]) ?>
                    <?= field_select('with_salary', 'With Salary', $salary, ['col' => 'col-md-3', 'required' => true, 'value' => 1]) ?>
                    <div class="col-12"><hr class="my-1"><p class="form-hint">Valid punch windows — a punch outside these is not paired as the break-out / break-in.</p></div>
                    <?php foreach ([
                        ['break_out_start', 'Break-Out Start Time', '11:45'], ['break_out_end', 'Break-Out End Time', '12:15'],
                        ['break_in_start', 'Break-In Start Time', '12:45'], ['break_in_end', 'Break-In End Time', '13:15'],
                    ] as $w): ?>
                        <?= field_time($w[0], $w[1], ['col' => 'col-md-3','value' => $w[2]]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="tab-pane fade" id="btRule">
                <div class="row g-3">
                    <?= field_number('minimum_duration', 'Minimum Break (min)', ['col' => 'col-md-4', 'value' => 0]) ?>
                    <?= field_number('available_interval', 'Duplicate-Punch Period (min)', ['col' => 'col-md-4', 'value' => 1]) ?>
                    <?= field_select('late_in', 'Late Back From Break', $lateIn, ['col' => 'col-md-4', 'value' => 0]) ?>
                    <div class="col-12 d-flex flex-wrap gap-4">
                        <?= field_switch('multiple_punch', 'Allow Multiple In/Out', ['checked' => false]) ?>
                        <?= field_switch('func_key', 'Match By Punch-State Key', ['checked' => false]) ?>
                    </div>
                </div>
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
    var m = document.getElementById('btForm'), f = document.getElementById('btFormEl');
    var mins = function (t) { var x = (t || '00:00').slice(0, 5).split(':'); return (+x[0]) * 60 + (+x[1] || 0); };
    var hhmm = function (n) { n = ((n % 1440) + 1440) % 1440; return ('0' + Math.floor(n / 60)).slice(-2) + ':' + ('0' + (n % 60)).slice(-2); };
    function reset(title) {
        f.reset();
        f.querySelector('[name=id]').value = '';
        m.querySelector('.modal-title').textContent = title;
        m.querySelector('[data-bs-target="#btBasic"]').click();
    }
    document.querySelectorAll('.js-bt-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = JSON.parse(b.dataset.json || '{}');
            reset('Edit');
            f.querySelector('[name=id]').value = d.id || '';
            f.querySelector('[name=alias]').value = d.alias || '';
            f.querySelector('[name=start_date]').value = d.start_date || '';
            f.querySelector('[name=end_date]').value = d.end_date || '';
            f.querySelector('[name=period_start]').value = (d.period_start || '12:00:00').slice(0, 5);
            var stM = mins(d.period_start), dur = +d.duration || 60, enM = stM + dur;
            f.querySelector('[name=break_in]').value = hhmm(enM);
            f.querySelector('[name=break_cross_days]').value = enM >= 1440 ? 1 : 0;
            f.querySelector('[name=break_out_start]').value = hhmm(stM - (+d.break_out_ahead_margin || 0));
            f.querySelector('[name=break_out_end]').value   = hhmm(stM + (+d.break_out_above_margin || 0));
            f.querySelector('[name=break_in_start]').value  = hhmm(enM - (+d.break_in_ahead_margin || 0));
            f.querySelector('[name=break_in_end]').value    = hhmm(enM + (+d.break_in_above_margin || 0));
            ['minimum_duration', 'available_interval', 'calc_type', 'with_salary', 'late_in'].forEach(function (k) {
                var el = f.querySelector('[name="' + k + '"]'); if (el) el.value = d[k] == null ? '' : d[k];
            });
            f.querySelector('[name=multiple_punch]').checked = String(d.multiple_punch) === '1';
            f.querySelector('[name=func_key]').checked = String(d.func_key) === '1';
            new bootstrap.Modal(m).show();
        });
    });
    document.querySelector('[data-bs-target="#btForm"]')?.addEventListener('click', function () { reset('Add'); });
})();
</script>
<?= $this->endSection() ?>
