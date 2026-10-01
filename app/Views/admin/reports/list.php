<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?= page_head($title, $subtitle ?? '', '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>') ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"></div>
        <div class="col-md-3"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Run</button></div>
        <div class="col-md-3 text-end small text-muted align-self-center"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 text-nowrap">
            <thead><tr>
                <th data-col="date">Date</th><th data-col="employee">Employee</th><th data-col="code">Code</th>
                <?php if ($kind === 'late'): ?><th data-col="sched_in">Scheduled in</th><th data-col="clock_in">Clocked in</th><th data-col="late" class="text-danger">Late</th>
                <?php elseif ($kind === 'early'): ?><th data-col="sched_out">Scheduled out</th><th data-col="clock_out">Clocked out</th><th data-col="early" class="text-warning">Early</th>
                <?php elseif ($kind === 'overtime'): ?><th data-col="worked">Worked</th><th data-col="ot" class="text-success">Overtime</th>
                <?php elseif ($kind === 'worked'): ?><th data-col="timetable">Timetable</th><th data-col="duty" class="text-end">Duty</th><th data-col="worked" class="text-end">Worked</th><th data-col="ot" class="text-end text-success">OT</th><th data-col="short" class="text-end text-danger">Short</th>
                <?php elseif ($kind === 'firstlast'): ?><th data-col="first_punch">First punch</th><th data-col="last_punch">Last punch</th><th data-col="span" class="text-end">Span</th><th data-col="punches">Punches</th>
                <?php elseif ($kind === 'fio'): ?><th data-col="first_in">First In</th><th data-col="last_out">Last Out</th><th data-col="total_hours" class="text-end">Total Hours</th>
                <?php elseif ($kind === 'exception'): ?><th data-col="sched">Scheduled</th><th data-col="clocked">Clocked</th><th data-col="late" class="text-danger">Late</th><th data-col="early" class="text-warning">Early</th>
                <?php else: ?><th data-col="shift">Shift</th><th data-col="sched">Scheduled</th><?php endif; ?>
                <th data-col="status">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $d): $e = $d['employee']; ?>
                <tr>
                    <td class="mono"><?= esc($d['date']) ?> <span class="text-muted small"><?= esc($d['weekday']) ?></span></td>
                    <td><a href="<?= route_to('employee.view', $e['id']) ?>" class="text-decoration-none"><?= esc(emp_name($e)) ?></a></td>
                    <td class="mono"><?= esc($e['emp_code']) ?></td>
                    <?php if ($kind === 'late'): ?>
                        <td class="mono"><?= esc($d['scheduled']['in'] ?? '—') ?></td>
                        <td class="mono"><?= esc($d['first_in'] ?? '—') ?></td>
                        <td class="mono text-danger fw-bold"><?= mins($d['late_minutes']) ?></td>
                    <?php elseif ($kind === 'early'): ?>
                        <td class="mono"><?= esc($d['scheduled']['out'] ?? '—') ?></td>
                        <td class="mono"><?= esc($d['last_out'] ?? '—') ?></td>
                        <td class="mono text-warning fw-bold"><?= mins($d['early_minutes']) ?></td>
                    <?php elseif ($kind === 'overtime'): ?>
                        <td class="mono"><?= mins($d['worked_minutes']) ?></td>
                        <td class="mono text-success fw-bold"><?= mins($d['ot_minutes']) ?></td>
                    <?php elseif ($kind === 'worked'): ?>
                        <td class="small text-muted"><?= esc($d['scheduled']['timetable'] ?? '—') ?></td>
                        <td class="mono text-end"><?= $d['scheduled'] ? mins($d['scheduled']['work_minutes']) : '—' ?></td>
                        <td class="mono text-end fw-semibold"><?= mins($d['worked_minutes']) ?></td>
                        <td class="mono text-end text-success"><?= mins($d['ot_minutes']) ?></td>
                        <td class="mono text-end text-danger"><?= mins($d['short_minutes']) ?></td>
                    <?php elseif ($kind === 'firstlast'): ?>
                        <?php $pk = $d['punches']; $pf = $pk[0]['time'] ?? null; $pl = $pk ? end($pk)['time'] : null;
                              $sp = ($pf && $pl && $pf !== $pl) ? (strtotime("1970-01-01 $pl") - strtotime("1970-01-01 $pf")) / 60 : 0;
                              if ($sp < 0) { $sp += 1440; } ?>
                        <td class="mono"><?= esc($pf ?? '—') ?></td>
                        <td class="mono"><?= esc($pl ?? '—') ?></td>
                        <td class="mono text-end"><?= $sp > 0 ? mins($sp) : '—' ?></td>
                        <td><?php foreach ($d['punches'] as $p): ?><?= punch_chip($p) ?><?php endforeach; ?></td>
                    <?php elseif ($kind === 'fio'): ?>
                        <?php $fi = $d['first_in'] ?? null; $fo = $d['last_out'] ?? null;
                              $th = ($fi && $fo && $fi !== $fo) ? (strtotime("1970-01-01 $fo") - strtotime("1970-01-01 $fi")) / 60 : 0;
                              if ($th < 0) { $th += 1440; } ?>
                        <td class="mono"><?= esc($fi ?? '—') ?></td>
                        <td class="mono"><?= esc($fo ?? '—') ?></td>
                        <td class="mono text-end"><?= $th > 0 ? mins($th) : '—' ?></td>
                    <?php elseif ($kind === 'exception'): ?>
                        <td class="mono small"><?= $d['scheduled'] ? esc($d['scheduled']['in'] . '–' . $d['scheduled']['out']) : '—' ?></td>
                        <td class="mono"><?= esc($d['first_in'] ?? '—') ?><?= $d['last_out'] ? ' – ' . esc($d['last_out']) : '' ?></td>
                        <td class="mono text-danger"><?= mins($d['late_minutes']) ?></td>
                        <td class="mono text-warning"><?= mins($d['early_minutes']) ?></td>
                    <?php else: ?>
                        <td class="small text-muted"><?= esc($d['scheduled']['timetable'] ?? '—') ?></td>
                        <td class="mono small"><?= $d['scheduled'] ? esc($d['scheduled']['in'] . '–' . $d['scheduled']['out']) : '—' ?></td>
                    <?php endif; ?>
                    <td><?= status_pill($d['status'], $d['status_class']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (! $rows): ?><tr><td colspan="10" class="text-center text-muted py-4">Nothing found in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (! empty($pager)): ?><div class="card-footer"><?= pager_bar($pager) ?></div><?php endif; ?>
</div>
<?= $this->endSection() ?>
