<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$present = $counts['Present'];
$rate = $totalEmp > 0 ? round(($present + $counts['Late']) / $totalEmp * 100) : 0;
$cards = [
    ['stat' => 'employees',   'label' => 'Employees',         'value' => $totalEmp, 'icon' => 'people',            'cls' => 'si-indigo', 'sub' => $dept ? 'In this department' : 'All employees'],
    ['stat' => 'present',     'label' => 'Present',     'value' => $present + $counts['Late'], 'icon' => 'person-check', 'cls' => 'si-green', 'sub' => $rate . '% of ' . $totalEmp . ' employees'],
    ['stat' => 'absent',      'label' => 'Absent',            'value' => $counts['Absent'], 'icon' => 'person-x',  'cls' => 'si-red', 'sub' => $counts['Incomplete'] . ' incomplete · ' . $counts['Leave'] . ' on leave'],
    ['stat' => 'late',        'label' => 'Late arrivals',     'value' => $counts['Late'], 'icon' => 'alarm',       'cls' => 'si-amber', 'sub' => mins($lateMinutes) . ' lost'],
    ['stat' => 'ot',          'label' => 'Overtime',          'value' => mins($otMinutes), 'icon' => 'hourglass-split', 'cls' => 'si-blue', 'sub' => 'Across all staff'],
    ['stat' => 'punches',     'label' => 'Punches logged',    'value' => number_format($totalPunch), 'icon' => 'fingerprint', 'cls' => 'si-slate', 'sub' => 'Raw device events on selected date'],
    ['stat' => 'approvals',   'label' => 'Pending approvals', 'value' => $pendingApprovals ?? 0, 'icon' => 'inbox', 'cls' => ($pendingApprovals ?? 0) ? 'si-amber' : 'si-slate', 'sub' => 'Leave · punch · OT · web punch', 'href' => route_to('requests')],
];
$_certExp = ($certAlerts['expired'] ?? 0) + ($certAlerts['expiring'] ?? 0);
$cards[] = ['stat' => 'cert', 'label' => 'Certifications', 'value' => $_certExp, 'icon' => 'patch-exclamation',
        'cls' => ($certAlerts['expired'] ?? 0) ? 'si-red' : 'si-amber',
        'sub' => ($certAlerts['expired'] ?? 0) . ' expired · ' . ($certAlerts['expiring'] ?? 0) . ' expiring',
        'href' => route_to('certifications') . '?status=expiring'];
?>
<?= page_head($title, $subtitle) ?>
<form class="filter-bar mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-auto"><label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= esc($today) ?>" max="<?= esc(date('Y-m-d')) ?>"></div>
        <div class="col-auto"><label class="form-label">Department</label>
            <select name="dept" class="form-select" data-native>
                <option value="">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int) $dept === (int) $d['id'] ? 'selected' : '' ?>><?= esc($d['dept_name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="<?= route_to('dashboard') ?>" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>
<?php if (! $isLatest): ?>
    <div class="ext-note mb-3"><i class="bi bi-info-circle"></i> Showing <?= esc(date('l, j M Y', strtotime($today))) ?> — live auto-refresh is paused while viewing a past date.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body text-center py-2">
        <div class="small text-muted mb-1">Present</div>
        <div style="position:relative; height:160px"><canvas id="chartPresent"></canvas></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body text-center py-2">
        <div class="small text-muted mb-1">Schedule</div>
        <div style="position:relative; height:160px"><canvas id="chartSchedule"></canvas></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body text-center py-2">
        <div class="small text-muted mb-1">Approvals (all dates / departments)</div>
        <div style="position:relative; height:160px"><canvas id="chartApproval"></canvas></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body text-center py-2">
        <div class="small text-muted mb-1">Device Status</div>
        <div style="position:relative; height:160px"><canvas id="chartDeviceStatus"></canvas></div>
    </div></div></div>
</div>

<div class="row g-3 mb-3" id="dashCards">
    <?php foreach ($cards as $c): ?>
    <div class="col-6 col-lg-2">
        <?php $tag = isset($c['href']) ? 'a' : 'div'; ?>
        <<?= $tag ?> class="card stat-card h-100 text-decoration-none text-reset"<?= isset($c['href']) ? ' href="' . esc($c['href']) . '"' : '' ?>><div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label"><?= esc($c['label']) ?></div>
                    <div class="stat-value" data-stat="<?= $c['stat'] ?>"><?= esc($c['value']) ?></div>
                </div>
                <div class="stat-icon <?= $c['cls'] ?>" data-stat-icon="<?= $c['stat'] ?>"><i class="bi bi-<?= $c['icon'] ?>"></i></div>
            </div>
            <div class="stat-sub mt-1" data-stat-sub="<?= $c['stat'] ?>"><?= esc($c['sub']) ?></div>
        </div></<?= $tag ?>>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Attendance trend · last 14 days <span class="dot-status dot-online" id="liveDot" title="Live"></span></span>
                <a href="<?= route_to('att.daily') ?>?date=<?= esc($today) ?>" class="btn btn-sm btn-outline-primary">Open register</a>
            </div>
            <div class="card-body"><div style="position:relative; height:280px"><canvas id="trendChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Devices</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <tbody id="dashDevices">
                    <?= view('admin/_dashboard_devices', ['devices' => $devices]) ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-12">
        <div class="card">
            <div class="card-header">Exceptions · last 14 days <span class="text-muted small">— late, early leave, absent</span></div>
            <div class="card-body"><div style="position:relative; height:240px"><canvas id="exceptionChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Latest biometric punches on selected date</span>
        <a href="<?= route_to('punchlogs') ?>" class="btn btn-sm btn-outline-secondary">All punch logs</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>Employee</th><th>Code</th><th>Event</th><th>Device</th></tr></thead>
            <tbody id="dashRecent">
            <?= view('admin/_dashboard_recent', ['recent' => $recent, 'stateLabels' => $stateLabels]) ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var _cs = getComputedStyle(document.documentElement);
var BRAND = (_cs.getPropertyValue('--primary') || '#7ac142').trim();
var MUTEDBAR = (_cs.getPropertyValue('--border-2') || '#d6d9dd').trim();
var RED = '#d73728', AMBER = '#ff9326', BLUE = '#3b82f6', SLATE = '#94a3b8';

// ---- "Today" gauges — small 2/3-slice doughnuts, matching ZKBioTime's own
// dashboard (chartPresent / chartSchedule / chartApproval / chartDeviceStatus).
// Every status keeps its own legend entry and its own "0" value even when
// the whole chart is empty — Chart.js's default legend already lists every
// label regardless of value, so nothing here needs to be collapsed away.
// A ring with nothing but zeros just draws no arcs; a faint placeholder
// circle (plugin below) fills that gap so the card never looks blank.
var emptyRingPlugin = {
    id: 'emptyRing',
    afterDatasetsDraw: function (chart) {
        var ds = chart.data.datasets[0] || {};
        var total = (ds.data || []).reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
        if (total > 0) return;
        var meta = chart.getDatasetMeta(0), arc = meta && meta.data && meta.data[0];
        if (!arc) return;
        var ctx = chart.ctx;
        ctx.save();
        ctx.beginPath();
        ctx.arc(arc.x, arc.y, arc.outerRadius, 0, 2 * Math.PI);
        ctx.arc(arc.x, arc.y, arc.innerRadius, 0, 2 * Math.PI, true);
        ctx.closePath();
        ctx.fillStyle = MUTEDBAR;
        ctx.fill();
        ctx.restore();
    }
};
function donut(canvasId, labels, data, colors) {
    return new Chart(document.getElementById(canvasId), {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 0 }] },
        plugins: [emptyRingPlugin],
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
        }
    });
}
var presentChart = donut('chartPresent', ['Present', 'Absent'], [<?= (int) $presentDonut['present'] ?>, <?= (int) $presentDonut['absent'] ?>], [BRAND, RED]);
var scheduleChart = donut('chartSchedule', ['Scheduled', 'No schedule'], [<?= (int) $scheduleDonut['scheduled'] ?>, <?= (int) $scheduleDonut['unscheduled'] ?>], [BLUE, SLATE]);
var approvalChart = donut('chartApproval', ['Approved', 'Pending', 'Rejected'], [<?= (int) $approvalDonut['approved'] ?>, <?= (int) $approvalDonut['pending'] ?>, <?= (int) $approvalDonut['rejected'] ?>], [BRAND, AMBER, RED]);
var deviceChart = donut('chartDeviceStatus', ['Online', 'Offline'], [<?= (int) $deviceDonut['online'] ?>, <?= (int) $deviceDonut['offline'] ?>], [BRAND, SLATE]);

var trendChart = new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($trend, 'date')) ?>,
        datasets: [
            { label: 'Present', data: <?= json_encode(array_column($trend, 'present')) ?>, backgroundColor: BRAND, borderRadius: 4 },
            { label: 'Absent',  data: <?= json_encode(array_column($trend, 'absent')) ?>,  backgroundColor: MUTEDBAR, borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { position: 'bottom' } }
    }
});

// ---- Exceptions trend — late / early leave / absent, matching ZKBioTime's
// own "chartCompare" exception line chart.
var exceptionChart = new Chart(document.getElementById('exceptionChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trend, 'date')) ?>,
        datasets: [
            { label: 'Late', data: <?= json_encode(array_column($trend, 'late')) ?>, borderColor: AMBER, backgroundColor: AMBER, tension: 0.3 },
            { label: 'Early leave', data: <?= json_encode(array_column($trend, 'early')) ?>, borderColor: BLUE, backgroundColor: BLUE, tension: 0.3 },
            { label: 'Absent', data: <?= json_encode(array_column($trend, 'absent')) ?>, borderColor: RED, backgroundColor: RED, tension: 0.3 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { position: 'bottom' } }
    }
});

// ---- live auto-refresh: repolls the numbers/tables above every few
// seconds so the dashboard reflects new punches without a page reload,
// matching ZKBioTime's own real-time monitor (no full re-render, just
// the pieces that can actually change between polls).
(function () {
    var isLatest = <?= $isLatest ? 'true' : 'false' ?>;
    var url = <?= json_encode(route_to('dashboard.live') . '?' . http_build_query(array_filter(['dept' => $dept, 'date' => request()->getGet('date') ? $today : null]))) ?>;
    var selectedDate = <?= json_encode($today) ?>;
    var pending = false;
    var stopped = false;
    var dot = document.getElementById('liveDot');
    var timer = null;

    if (!isLatest) {
        // viewing a past date — nothing to auto-refresh
        if (dot) { dot.classList.remove('dot-online'); dot.classList.add('dot-offline'); dot.title = 'Paused (viewing a past date)'; }
        return;
    }

    function apply(d) {
        if (d.date !== selectedDate) { window.location.reload(); return; }
        Object.keys(d.stats || {}).forEach(function (key) {
            var v = document.querySelector('[data-stat="' + key + '"]');
            var s = document.querySelector('[data-stat-sub="' + key + '"]');
            if (v) v.textContent = d.stats[key].value;
            if (s) s.textContent = d.stats[key].sub;
        });
        var devices = document.getElementById('dashDevices');
        if (devices && d.devicesHtml != null) devices.innerHTML = d.devicesHtml;
        var recent = document.getElementById('dashRecent');
        if (recent && d.recentHtml != null) recent.innerHTML = d.recentHtml;
        if (d.todayTrend && trendChart.data.labels.length) {
            var last = trendChart.data.labels.length - 1;
            trendChart.data.datasets[0].data[last] = d.todayTrend.present;
            trendChart.data.datasets[1].data[last] = d.todayTrend.absent;
            trendChart.update('none');
        }
        if (d.todayTrend && exceptionChart.data.labels.length) {
            var lastE = exceptionChart.data.labels.length - 1;
            exceptionChart.data.datasets[0].data[lastE] = d.todayTrend.late;
            exceptionChart.data.datasets[1].data[lastE] = d.todayTrend.early;
            exceptionChart.data.datasets[2].data[lastE] = d.todayTrend.absent;
            exceptionChart.update('none');
        }
        function setDonut(chart, obj) {
            if (!obj) return;
            chart.data.datasets[0].data = Object.values(obj).map(Number);
            chart.update('none');
        }
        setDonut(presentChart, d.presentDonut);
        setDonut(scheduleChart, d.scheduleDonut);
        setDonut(approvalChart, d.approvalDonut);
        setDonut(deviceChart, d.deviceDonut);
    }

    function poll() {
        if (pending || stopped || document.hidden) return;
        pending = true;
        fetch(url, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (stopped) return;
                if (d) { apply(d); if (dot) { dot.classList.remove('dot-offline'); dot.classList.add('dot-online'); dot.title = 'Updated ' + new Date().toLocaleTimeString(); } }
                else if (dot) { dot.classList.remove('dot-online'); dot.classList.add('dot-offline'); }
            })
            .catch(function () { if (dot) { dot.classList.remove('dot-online'); dot.classList.add('dot-offline'); dot.title = 'Refresh failed; retrying'; } })
            .finally(function () { pending = false; });
    }

    timer = setInterval(poll, 10000);
    document.addEventListener('turbo:before-cache', function () { stopped = true; clearInterval(timer); }, { once: true });
})();
</script>
<?= $this->endSection() ?>
