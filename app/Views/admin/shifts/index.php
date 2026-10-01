<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle,
    modal_btn('ttNew', 'New timetable', 'clock') . modal_btn('shNew', 'New shift', 'diagram-3', 'btn-outline-secondary')) ?>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    The engine resolves each employee's timetable via a <strong>personal schedule</strong> → <strong>department schedule</strong>.
    A shift with no day-by-day rows uses its first timetable every working day. Timezone <code><?= esc($cfg->timezone) ?></code>,
    late grace +<?= (int) $cfg->extraLateGrace ?>m / early +<?= (int) $cfg->extraEarlyGrace ?>m.
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">Timetables</div><div class="card-body">
            <?php foreach ($timetables as $t): ?>
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <strong><?= esc($t['alias']) ?></strong>
                        <div class="d-flex align-items-center gap-2">
                            <span class="mono"><?= esc(substr($t['in_time'], 0, 5)) ?> – <?= esc(substr($t['out_time'], 0, 5)) ?></span>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ttEdit<?= $t['id'] ?>"><i class="bi bi-pencil"></i></button>
                        </div>
                    </div>
                    <div class="small text-muted mt-1">
                        Work time <?= mins((int) $t['duration']) ?> h · grace late <?= (int) $t['allow_late'] ?>m / early <?= (int) $t['allow_leave_early'] ?>m
                    </div>
                    <?php if ($t['breaks']): ?>
                        <div class="mt-2">
                            <?php foreach ($t['breaks'] as $b): ?>
                                <span class="punch-chip brk"><span class="dot"></span><?= esc($b['alias']) ?> <?= esc(substr($b['period_start'], 0, 5)) ?> · <?= (int) $b['duration'] ?>m <?= (int) $b['with_salary'] === 1 ? '(paid)' : '(unpaid)' ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal fade" id="ttEdit<?= $t['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <?= form_open(route_to('timetable.save')) ?>
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <div class="modal-header"><h5 class="modal-title">Edit <?= esc($t['alias']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-2"><label class="form-label tiny">Name</label><input name="alias" class="form-control" value="<?= esc($t['alias']) ?>" required></div>
                        <div class="row g-2">
                            <div class="col"><label class="form-label tiny">Check-in</label><input type="time" name="in_time" class="form-control" value="<?= esc(substr($t['in_time'], 0, 5)) ?>" required></div>
                            <div class="col"><label class="form-label tiny">Work minutes</label><input type="number" name="duration" class="form-control" value="<?= (int) $t['duration'] ?>" required></div>
                        </div>
                        <div class="row g-2 mt-1">
                            <div class="col"><label class="form-label tiny">Late grace (min)</label><input type="number" name="allow_late" class="form-control" value="<?= (int) $t['allow_late'] ?>"></div>
                            <div class="col"><label class="form-label tiny">Early grace (min)</label><input type="number" name="allow_early" class="form-control" value="<?= (int) $t['allow_leave_early'] ?>"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
                    <?= form_close() ?>
                </div></div></div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">Shifts</div>
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Shift</th><th>Weekend</th><th>Detail rows</th><th>Assigned</th></tr></thead>
                <tbody>
                <?php foreach ($shifts as $s): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($s['alias']) ?></td>
                        <td><?= $s['work_weekend'] === 't' ? '<span class="pill pill-success">Works</span>' : '<span class="pill pill-muted">Off</span>' ?></td>
                        <td class="mono"><?= (int) $s['detail_count'] ?></td>
                        <td class="mono"><?= (int) $s['emp_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-7">
        <div class="card"><div class="card-header">Personal schedules</div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Code</th><th>Employee</th><th>Shift</th><th>From</th><th>To</th></tr></thead>
                <tbody>
                <?php foreach ($personal as $p): ?>
                    <tr><td class="mono"><?= esc($p['emp_code']) ?></td><td><?= esc($p['name'] ?? '—') ?></td><td><?= esc($p['shift']) ?></td>
                        <td class="mono small"><?= esc($p['start_date']) ?></td><td class="mono small"><?= esc($p['end_date']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if (isset($personalPager)): ?><div class="px-3 pb-2"><?= pager_bar($personalPager, [10, 15, 25, 50, 100], 'personal') ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-header">Department schedules</div>
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Department</th><th>Shift</th><th>Range</th></tr></thead>
                <tbody>
                <?php foreach ($deptSched as $d): ?>
                    <tr><td><?= esc($d['dept_name']) ?></td><td><?= esc($d['shift']) ?></td><td class="mono small"><?= esc($d['start_date']) ?> → <?= esc($d['end_date']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (! $deptSched): ?><tr><td colspan="3" class="text-muted text-center py-3">None</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<div class="modal fade" id="ttNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('timetable.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New timetable</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label><input name="alias" class="form-control" required></div>
        <div class="row g-2">
            <div class="col"><label class="form-label tiny">Check-in *</label><input type="time" name="in_time" class="form-control" value="09:00" required></div>
            <div class="col"><label class="form-label tiny">Work minutes *</label><input type="number" name="duration" class="form-control" value="480" required></div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col"><label class="form-label tiny">Late grace</label><input type="number" name="allow_late" class="form-control" value="5"></div>
            <div class="col"><label class="form-label tiny">Early grace</label><input type="number" name="allow_early" class="form-control" value="10"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    <?= form_close() ?>
</div></div></div>

<div class="modal fade" id="shNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('shift.save')) ?>
    <div class="modal-header"><h5 class="modal-title">New shift</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Name *</label><input name="alias" class="form-control" required></div>
        <div class="mb-2"><label class="form-label tiny">Timetable (applied every day)</label>
            <select name="timetable_id" class="form-select">
                <option value="">None yet</option>
                <?php foreach ($timetables as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['alias']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="work_weekend" value="1" id="sw" checked><label class="form-check-label small" for="sw">Works weekends</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="work_day_off" value="1" id="sd" checked><label class="form-check-label small" for="sd">Works on days off</label></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
