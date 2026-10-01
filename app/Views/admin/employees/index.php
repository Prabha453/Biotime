<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$qs = static fn (array $over = []) => route_to('employees') . '?' . http_build_query(array_merge(
    ['q' => $q, 'dept' => $dept ?: '', 'pos' => $pos ?: '', 'status' => $status, 'sort' => $sort], $over
));
$activeCount = ($q !== '' ? 1 : 0) + ($dept ? 1 : 0) + ($pos ? 1 : 0) + ($status !== '' ? 1 : 0) + ($sort !== '' ? 1 : 0) + (int) ($fbApplied ?? 0);

$deptOpts   = ['' => 'All departments'] + opts_from($departments, 'id', 'dept_name');
$posOpts    = ['' => 'All designations'] + opts_from($positions, 'id', 'position_name');
$statusOpts = ['' => 'All', 'active' => 'Active', 'resigned' => 'Resigned', 'inactive' => 'Attendance disabled'];
$sortOpts   = ['' => 'Employee code', 'name' => 'Name', 'recent' => 'Newest joiners'];
?>
<?php
$deptBulk = opts_from($departments, 'id', 'dept_name');
$posBulk  = ['' => '— clear —'] + opts_from($positions, 'id', 'position_name');
$areaBulk = opts_from($areas, 'id', 'area_name');
?>
<?= page_head($title, $subtitle) ?>
<?php
$empLeft = '<a href="' . route_to('employee.new') . '" class="btn btn-sm btn-primary" data-turbo="false"><i class="bi bi-person-plus me-1"></i>Add employee</a>'
    . '<div class="btn-group"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-upload me-1"></i>Import</button>'
    . '<ul class="dropdown-menu">'
    . '<li><a class="dropdown-item" href="' . route_to('imports.tab', 'employees') . '" data-turbo="true">Employees</a></li>'
    . '<li><a class="dropdown-item" href="' . route_to('imports.tab', 'photos') . '" data-turbo="true">Employee photos (ZIP)</a></li>'
    . '<li><a class="dropdown-item" href="' . route_to('imports.tab', 'documents') . '" data-turbo="true">Certification documents (ZIP)</a></li>'
    . '<li><a class="dropdown-item" href="' . route_to('imports.tab', 'leavebalance') . '" data-turbo="true">Leave balances</a></li>'
    . '</ul></div>';
?>
<?= grid_toolbar([
    'left'             => $empLeft,
    'filter'           => '#empFilterPanel',
    'filter_count'     => $activeCount,
    'filter_count_id'  => 'empFilterCount',
    'export'           => ['key' => 'employees'],
    'export_qs'        => ['q' => $q, 'dept' => $dept ?: '', 'pos' => $pos ?: '', 'status' => $status],
    'columns'          => true,
    'history'          => ['prefix' => 'employee', 'label' => 'Employee'],
]) ?>

<div class="kpi-row">
    <?= stat_tile('Total employees', number_format((int) $stats['total']), 'people', 'si-indigo') ?>
    <?= stat_tile('Active', number_format((int) $stats['active']), 'person-check', 'si-green', '', $qs(['status' => 'active'])) ?>
    <?= stat_tile('Resigned', number_format((int) ($stats['resigned'] ?? 0)), 'person-dash', 'si-red', '', $qs(['status' => 'resigned'])) ?>
    <?= stat_tile('Joined in 90 days', number_format((int) $stats['recent']), 'person-add', 'si-blue', '', $qs(['sort' => 'recent'])) ?>
</div>

<!-- ============== Bulk-action bar (shown once rows are ticked) ============== -->
<?= form_open(route_to('employee.bulk'), ['id' => 'empBulk', 'class' => 'card mb-3 d-none', 'data-turbo' => 'false', 'data-confirm' => 'Apply this action to the selected employees?']) ?>
<div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
    <span class="fw-semibold small"><span id="empSelCount">0</span> selected</span>
    <select name="op" id="empBulkOp" class="form-select form-select-sm w-auto" data-native>
        <option value="">Choose an action…</option>
        <optgroup label="Personnel Transfer">
            <option value="dept">Transfer department</option>
            <option value="position">Transfer position</option>
            <option value="area_set">Adjust area (replace)</option>
            <option value="area_add">Assign area (add)</option>
        </optgroup>
        <optgroup label="Status">
            <option value="probation">Pass probation → Permanent</option>
            <option value="app_enable">Enable app</option>
            <option value="app_disable">Disable app</option>
            <option value="resign">Resign</option>
        </optgroup>
        <optgroup label="Device">
            <option value="resync">Re-sync to device</option>
            <option value="reupload">Re-upload from device</option>
            <option value="clear_bio">Delete biometric templates</option>
        </optgroup>
        <optgroup label="Danger">
            <option value="delete">Delete (no history only)</option>
        </optgroup>
    </select>
    <select name="department_id" class="form-select form-select-sm w-auto bulk-arg" data-op="dept" data-native hidden>
        <option value="">Department…</option>
        <?php foreach ($deptBulk as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?>
    </select>
    <select name="position_id" class="form-select form-select-sm w-auto bulk-arg" data-op="position" data-native hidden>
        <?php foreach ($posBulk as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?>
    </select>
    <select name="area_id" class="form-select form-select-sm w-auto bulk-arg" data-op="area_set area_add" data-native hidden>
        <option value="">Area…</option>
        <?php foreach ($areaBulk as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="resign_date" class="form-control form-control-sm w-auto bulk-arg" data-op="resign" value="<?= date('Y-m-d') ?>" data-native hidden>
    <input type="text" name="reason" class="form-control form-control-sm w-auto bulk-arg" data-op="resign" placeholder="Reason" hidden>
    <label class="form-check form-check-inline small bulk-arg" data-op="resign" hidden><input class="form-check-input" type="checkbox" name="disable_att" value="1" checked> disable attendance</label>
    <button class="btn btn-sm btn-primary" id="empBulkGo">Apply</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="empBulkClear">Clear</button>
    <span id="empBulkIds"></span>
</div>
<?= form_close() ?>

<div data-crud="<?= route_to('employees') ?>">
    <div id="empResults" data-crud-results class="ajax-region">
        <?= view('admin/employees/_list', ['rows' => $rows, 'pager' => $pager,
            'q' => $q, 'dept' => $dept, 'pos' => $pos, 'status' => $status, 'sort' => $sort]) ?>
    </div>

    <!-- ============== Right-side filter panel ============== -->
    <?php
    $fbFields = json_encode([
        'emp_code'   => ['label' => 'Employee ID', 'type' => 'text'],
        'first_name' => ['label' => 'First name', 'type' => 'text'],
        'last_name'  => ['label' => 'Last name', 'type' => 'text'],
        'email'      => ['label' => 'Email', 'type' => 'text'],
        'mobile'     => ['label' => 'Mobile', 'type' => 'text'],
        'card_no'    => ['label' => 'Card no.', 'type' => 'text'],
        'hire_date'  => ['label' => 'Hired date', 'type' => 'date'],
        'birthday'   => ['label' => 'Birthday', 'type' => 'date'],
        'gender'     => ['label' => 'Gender', 'type' => 'select', 'options' => ['M' => 'Male', 'F' => 'Female', 'O' => 'Other']],
        'emp_type'   => ['label' => 'Employment type', 'type' => 'select', 'options' => [1 => 'Permanent', 2 => 'Temporary', 3 => 'Probation']],
        'is_active'  => ['label' => 'Active', 'type' => 'bool'],
    ], JSON_UNESCAPED_SLASHES);
    ?>
    <div class="offcanvas offcanvas-end" tabindex="-1" id="empFilterPanel" aria-labelledby="empFilterTitle"
         data-filter-builder data-fields="<?= esc($fbFields, 'attr') ?>">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="empFilterTitle"><i class="bi bi-funnel me-2"></i>Filter employees</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="btn-group btn-group-sm flex-grow-1">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle w-100" data-bs-toggle="dropdown"><i class="bi bi-bookmark me-1"></i>Bookmarks</button>
                    <ul class="dropdown-menu w-100" data-bookmark-list></ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bookmark-save title="Save current filter"><i class="bi bi-bookmark-plus"></i></button>
            </div>
            <form method="get" id="empFilter" data-crud-filter class="d-flex flex-column gap-3 h-100">
                <?= field_text('q', 'Search', ['value' => $q, 'placeholder' => 'Name, code, email or phone', 'col' => '']) ?>
                <div class="row g-2">
                    <div class="col-6"><?= field_select('dept', 'Department', $deptOpts, ['value' => $dept ?: '', 'col' => '']) ?></div>
                    <div class="col-6"><?= field_select('pos', 'Designation', $posOpts, ['value' => $pos ?: '', 'col' => '']) ?></div>
                    <div class="col-6"><?= field_select('status', 'Status', $statusOpts, ['value' => $status, 'col' => '']) ?></div>
                    <div class="col-6"><?= field_select('sort', 'Sort by', $sortOpts, ['value' => $sort, 'col' => '']) ?></div>
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">Conditions</label>
                    <div data-filter-rows class="d-flex flex-column gap-2"></div>
                    <button type="button" class="btn btn-sm btn-link text-decoration-none align-self-start px-0" data-filter-add><i class="bi bi-plus-circle me-1"></i>Add condition</button>
                </div>
                <div class="mt-auto d-flex gap-2 pt-2 border-top">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <button type="button" class="btn btn-outline-secondary" id="empFilterReset" data-filter-reset>Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const results = document.getElementById('empResults');
    const form    = document.getElementById('empFilter');
    const panelEl = document.getElementById('empFilterPanel');
    const badge   = document.getElementById('empFilterCount');
    if (!results || !form) return;

    // ---- grid / list toggle (re-bound after every crud swap) ----
    function bindView() {
        const wrap = results.querySelector('#empView');
        if (!wrap) return;
        const panes = results.querySelectorAll('[data-view-pane]');
        const apply = (m) => {
            panes.forEach(p => p.hidden = p.dataset.viewPane !== m);
            wrap.querySelectorAll('button').forEach(b => b.classList.toggle('is-on', b.dataset.view === m));
            try { localStorage.setItem('tt-emp-view', m); } catch (e) {}
        };
        wrap.querySelectorAll('button').forEach(b => b.addEventListener('click', () => apply(b.dataset.view)));
        let mode = 'list';
        try { mode = localStorage.getItem('tt-emp-view') || 'list'; } catch (e) {}
        apply(mode);
    }
    bindView();
    if (!window.__empViewBound) { window.__empViewBound = 1; document.addEventListener('tt:swapped', bindView); }

    // ---- filter panel glue (the fetch itself is done by the generic [data-crud] wiring) ----
    const activeFilters = () =>
        [...new FormData(form)].filter(([k, v]) => v && !/^csrf/.test(k)).length;
    const syncBadge = () => {
        const n = activeFilters();
        badge.textContent = n;
        badge.classList.toggle('d-none', n === 0);
    };
    const closePanel = () => window.bootstrap && bootstrap.Offcanvas.getInstance(panelEl)?.hide();

    form.addEventListener('submit', () => { syncBadge(); closePanel(); });
    form.querySelectorAll('select').forEach(s => s.addEventListener('change', syncBadge));
    form.querySelector('[name="q"]')?.addEventListener('input', syncBadge);

    document.getElementById('empFilterReset')?.addEventListener('click', () => {
        form.reset();
        form.querySelectorAll('select').forEach(s => { s.selectedIndex = 0; if (s.tomselect) s.tomselect.setValue('', true); });
        const q = form.querySelector('[name="q"]'); if (q) q.value = '';
        syncBadge();
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        closePanel();
    });
    syncBadge();

    // ---- bulk-select toolbar --------------------------------------------------
    const bar = document.getElementById('empBulk');
    if (bar) {
        const count  = document.getElementById('empSelCount');
        const opSel  = document.getElementById('empBulkOp');
        const selCount = () => results.querySelectorAll('.emp-check:checked').length;
        const refresh = () => {
            const n = selCount();
            count.textContent = n;
            bar.classList.toggle('d-none', n === 0);
            const all = results.querySelector('#empCheckAll');
            const boxes = results.querySelectorAll('.emp-check');
            if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
        };
        const showArgs = () => {
            const op = opSel.value;
            bar.querySelectorAll('.bulk-arg').forEach(el => {
                el.hidden = !(el.dataset.op || '').split(' ').includes(op);
            });
        };
        results.addEventListener('change', e => {
            if (e.target.id === 'empCheckAll') {
                results.querySelectorAll('.emp-check').forEach(b => b.checked = e.target.checked);
            }
            if (e.target.classList.contains('emp-check') || e.target.id === 'empCheckAll') refresh();
        });
        opSel.addEventListener('change', showArgs);
        document.getElementById('empBulkClear').addEventListener('click', () => {
            results.querySelectorAll('.emp-check, #empCheckAll').forEach(b => { b.checked = false; b.indeterminate = false; });
            refresh();
        });
        bar.addEventListener('submit', e => {
            const ids = [...results.querySelectorAll('.emp-check:checked')].map(b => b.value);
            if (!ids.length || !opSel.value) { e.preventDefault(); e.stopImmediatePropagation(); return; }
            const box = document.getElementById('empBulkIds');
            box.innerHTML = ids.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
        });
        document.addEventListener('tt:swapped', refresh);
        refresh();
    }
})();
</script>
<?= $this->endSection() ?>
