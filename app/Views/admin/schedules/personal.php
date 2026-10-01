<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, modal_btn('schAssign', 'Assign shift')) ?>

<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Search employee</label><input name="q" value="<?= esc($q) ?>" class="form-control" placeholder="Name or code"></div>
        <div class="col-md-3"><label class="form-label">Company</label>
            <select name="company" class="form-select" data-native>
                <option value="">All companies</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int) $company === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="<?= route_to('schedules.personal') ?>" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Employee</th><th>Shift</th><th>From</th><th>To</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><?= esc($r['emp_code']) ?></td>
                    <td><a href="<?= route_to('employee.view', $r['emp_id']) ?>"><?= esc($r['name'] ?? '—') ?></a></td>
                    <td><span class="pill pill-info"><?= esc($r['shift']) ?></span></td>
                    <td class="mono small"><?= esc($r['start_date']) ?></td>
                    <td class="mono small"><?= esc($r['end_date']) ?></td>
                    <td class="text-end">
                        <?= form_open(route_to('schedule.delete', $r['id']), ['class' => 'd-inline', 'data-confirm' => 'Remove this schedule?']) ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar-week"></i>No schedules.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (isset($pager)): ?><div class="px-3 pb-2"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>

<?php // assign modal ?>
<div class="modal fade" id="schAssign" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <?= form_open(route_to('schedule.assign')) ?>
    <div class="modal-header"><h5 class="modal-title">Assign shift to employee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label tiny">Employee(s)</label>
            <select name="employee_id[]" class="form-select" multiple required data-select-all>
                <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= esc($e['emp_code'] . ' · ' . ($e['name'] ?? '')) ?></option><?php endforeach; ?>
            </select>
            <p class="form-hint mb-0">Select one or more employees — the same shift and date range will be applied to each. Use "Select all" then uncheck any you don't want.</p>
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
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Assign</button></div>
    <?= form_close() ?>
</div></div></div>
<?= $this->endSection() ?>
