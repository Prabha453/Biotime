<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle) ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <?= form_open(route_to('attcalc.run'), ['data-confirm' => 'Recalculate the timecards for this range? Existing timecards in the range are replaced.']) ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label tiny">From</label><input type="date" name="from" class="form-control" value="<?= esc($from) ?>" required></div>
                <div class="col-md-4"><label class="form-label tiny">To</label><input type="date" name="to" class="form-control" value="<?= esc($to) ?>" required></div>
                <div class="col-md-4"><label class="form-label tiny">Department</label>
                    <select name="dept" class="form-select" data-native>
                        <option value="">All departments</option>
                        <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>"><?= esc($d['dept_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary"><i class="bi bi-calculator me-1"></i>Calculate</button>
            </div>
            <?= form_close() ?>
            <p class="form-hint mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>The engine scores <code>iclock_transaction</code> punches against each employee's resolved timetable and writes one <code>att_payloadtimecard</code> row per worked day. Reports read those rows.</p>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h6 class="mb-3">Current state</h6>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted">Timecards stored</dt><dd class="col-6 mono"><?= number_format($stats['timecards']) ?></dd>
                <dt class="col-6 text-muted">Covering</dt><dd class="col-6 mono"><?= esc(($stats['range']['a'] ?? '—') . ' … ' . ($stats['range']['b'] ?? '—')) ?></dd>
                <dt class="col-6 text-muted">Punches in system</dt><dd class="col-6 mono"><?= number_format($stats['txns']) ?></dd>
                <dt class="col-6 text-muted">Last calculated</dt><dd class="col-6 mono"><?= esc($lastRun ?? 'never') ?></dd>
            </dl>
        </div></div>
    </div>
</div>
<?= $this->endSection() ?>
