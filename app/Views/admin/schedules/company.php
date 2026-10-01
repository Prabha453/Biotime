<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('schCompany', 'Add company-wide schedule')) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Company</th><th>Shift</th><th>Range</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($companySched as $c): ?>
                <tr>
                    <td><?= esc($c['company_name']) ?></td>
                    <td><span class="pill pill-info"><?= esc($c['shift']) ?></span></td>
                    <td class="mono small"><?= esc($c['start_date']) ?> → <?= esc($c['end_date']) ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('schedule.company.delete', $c['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this schedule?']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $companySched): ?><tr><td colspan="4"><div class="empty-state"><i class="bi bi-buildings"></i>No company schedules.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="px-3 py-2 border-top small text-muted">
        Not a ZKBioTime feature — a default for every department in the company. A department's own <a href="<?= route_to('schedules.department') ?>">Department Schedule</a> still overrides it, and a personal <a href="<?= route_to('schedules.personal') ?>">Employee Schedule</a> overrides both.
    </div>
</div>

<?php // company modal ?>
<div class="modal fade" id="schCompany" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('schedule.company')) ?>
    <div class="modal-header"><h5 class="modal-title">Company-wide schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Company</label>
            <select name="company_id" class="form-select" required>
                <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= esc($c['company_name']) ?></option><?php endforeach; ?>
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
        <p class="small text-muted mt-2 mb-0">Applies to every employee in the company who has no personal or department schedule for these dates.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Add</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
