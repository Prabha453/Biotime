<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('schDept', 'Add department schedule')) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Department</th><th>Shift</th><th>Range</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($deptSched as $d): ?>
                <tr>
                    <td><?= esc($d['dept_name']) ?></td>
                    <td><span class="pill pill-info"><?= esc($d['shift']) ?></span></td>
                    <td class="mono small"><?= esc($d['start_date']) ?> → <?= esc($d['end_date']) ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('schedule.dept.delete', $d['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this schedule?']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $deptSched): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-building"></i>No department schedules.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php // dept modal ?>
<div class="modal fade" id="schDept" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('schedule.dept')) ?>
    <div class="modal-header"><h5 class="modal-title">Department-wide schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Department</label>
            <select name="department_id" class="form-select" required>
                <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label tiny">Shift</label>
            <select name="shift_id" class="form-select" required>
                <?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>"><?= esc($s['alias']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2">
            <div class="col"><label class="form-label tiny">From</label><input type="date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>" required></div>
            <div class="col"><label class="form-label tiny">To</label><input type="date" name="end_date" class="form-control" value="<?= date('Y-12-31', strtotime('+1 year')) ?>" required></div>
        </div>
        <p class="small text-muted mt-2 mb-0">Applies to any employee in the department without a personal schedule. For a whole company at once, use <a href="<?= route_to('schedules.company') ?>">Company Schedule</a>.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
