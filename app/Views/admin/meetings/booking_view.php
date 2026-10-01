<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle, '<a class="btn btn-sm btn-outline-secondary" href="' . route_to('meetings.bookings') . '"><i class="bi bi-arrow-left me-1"></i>Bookings</a>') ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Room</dt><dd class="col-7"><?= esc($m['room'] ?? '—') ?></dd>
                <dt class="col-5 text-muted">Date</dt><dd class="col-7 mono"><?= esc($m['meeting_date']) ?></dd>
                <dt class="col-5 text-muted">Start</dt><dd class="col-7 mono"><?= esc(substr((string) $m['start_time'], 11, 5)) ?></dd>
                <dt class="col-5 text-muted">End</dt><dd class="col-7 mono"><?= esc(substr((string) $m['end_time'], 11, 5)) ?></dd>
                <dt class="col-5 text-muted">Duration</dt><dd class="col-7"><?= mins((int) $m['duration']) ?> h</dd>
                <?php if ($m['content']): ?><dt class="col-5 text-muted">Agenda</dt><dd class="col-7"><?= esc($m['content']) ?></dd><?php endif; ?>
                <dt class="col-5 text-muted">Calculated</dt><dd class="col-7"><?= $m['calculation_time'] ? esc(substr((string) $m['calculation_time'], 0, 16)) : 'never' ?></dd>
            </dl>
            <?= form_open(route_to('meetings.calculate', $m['workflowinstance_ptr_id']), ['class' => 'mt-3']) ?>
                <button class="btn btn-sm btn-primary"><i class="bi bi-calculator me-1"></i>Recalculate attendance</button>
            <?= form_close() ?>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Attendee attendance <span class="text-muted small">meeting_meetingtransaction</span></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Employee</th><th>In</th><th>Out</th><th>Late (min)</th><th>Early (min)</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($attendance as $a): ?>
                        <tr>
                            <td><span class="mono text-muted"><?= esc($a['emp_code']) ?></span> <?= esc($a['name'] ?? '') ?></td>
                            <td class="mono small"><?= esc($a['clock_in'] ?? '—') ?></td>
                            <td class="mono small"><?= esc($a['clock_out'] ?? '—') ?></td>
                            <td class="mono"><?= $a['late_in'] ?: '' ?></td>
                            <td class="mono"><?= $a['early_out'] ?: '' ?></td>
                            <td><span class="pill pill-<?= $a['status'] === 'Attended' ? 'success' : ($a['status'] === 'Absent' ? 'danger' : 'warning') ?>"><?= esc($a['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $attendance): ?><tr><td colspan="6" class="text-muted small">No attendees on this booking.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (! empty($attenders)): ?>
            <div class="card-footer">
                <?= form_open(route_to('meetings.manuallog', $m['workflowinstance_ptr_id']), ['class' => 'row g-2 align-items-end']) ?>
                    <div class="col-md-4"><label class="form-label tiny">Attendee</label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <?php foreach ($attenders as $a): ?><option value="<?= $a['id'] ?>"><?= esc($a['emp_code'] . ' · ' . $a['name']) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-3"><label class="form-label tiny">Time</label><input name="punch_time" type="datetime-local" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="form-label tiny">Direction</label>
                        <select name="punch_state" class="form-select form-select-sm"><option value="0">In</option><option value="1">Out</option></select></div>
                    <div class="col-md-2"><label class="form-label tiny">Reason</label><input name="reason" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
                <?= form_close() ?>
                <div class="text-muted small mt-1">Adds a manual meeting punch (source: manual) and recalculates.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
