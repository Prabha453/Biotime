<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('tmpNew', 'Set temporary schedule')) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Employee</th><th>Timetable</th><th></th></tr></thead>
            <tbody>
            <?php foreach (($tempSched ?? []) as $t): ?>
                <tr>
                    <td class="mono small"><?= esc($t['att_date']) ?></td>
                    <td class="small"><span class="mono text-muted"><?= esc($t['emp_code']) ?></span> <?= esc($t['name'] ?? '') ?></td>
                    <td><?= $t['timetable'] ? '<span class="pill pill-info">' . esc($t['timetable']) . '</span>' : '<span class="pill pill-muted">Day off</span>' ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('schedule.temp.delete', $t['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove?']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tempSched)): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-calendar-day"></i>No temporary schedules in the last 60 days.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php // temporary schedule modal ?>
<div class="modal fade" id="tmpNew" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('schedule.temp')) ?>
    <div class="modal-header"><h5 class="modal-title">Temporary schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee(s)</label>
            <select name="employee_id[]" class="form-select" multiple required data-select-all>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) ?></option><?php endforeach; ?>
            </select>
            <p class="form-hint mb-0">Select one or more employees — the same timetable override will be applied to each.</p>
        </div>
        <div class="mb-2"><label class="form-label tiny">Timetable</label>
            <select name="time_interval_id" class="form-select" required>
                <?php foreach (($timetables ?? []) as $ti): ?><option value="<?= $ti['id'] ?>"><?= esc($ti['alias']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="row g-2">
            <div class="col"><label class="form-label tiny">From</label><input type="date" name="from_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col"><label class="form-label tiny">To</label><input type="date" name="to_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <p class="small text-muted mt-2 mb-0">Overrides the resolved timetable for each day in the range — used for a one-off shift swap or a training day. Max two months.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Set</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
