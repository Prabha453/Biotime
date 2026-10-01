<?php

/**
 * Data-grid chrome that mirrors ZKBioTime's list pages: a toolbar with
 * Add / Delete / Import on the left and Filter / Export / Columns / History
 * on the right, plus the import + history modals those buttons open.
 *
 * The matching behaviour lives in public/assets/js/tt-grid.js (bulk-select,
 * column chooser, lazy history feed), re-bound after every [data-crud] swap.
 */

if (! function_exists('grid_toolbar')) {
    /**
     * @param array $cfg keys:
     *   add     => ['label'=>?, 'modal'=>?id] | ['label'=>?, 'href'=>?url]
     *   delete  => url            (POST ids[]; list needs .grid-check + #gridCheckAll)
     *   import  => type string    (opens #importModal)
     *   ret     => url            (where the import returns to; defaults to current)
     *   export  => key string     (Exports::run/<key>) | ['key'=>, 'label'=>]
     *   filter  => '#panelId'     (button toggles it — page supplies the panel)
     *   columns => true           (enables the column chooser; th/td need data-col)
     *   history => ['prefix'=>'org.department', 'label'=>'Department']
     *   right   => raw html appended to the right cluster
     */
    function grid_toolbar(array $cfg): string
    {
        $left  = '';
        $right = '';

        if (! empty($cfg['left'])) {
            $left .= $cfg['left'];
        }

        if (! empty($cfg['add'])) {
            $a = $cfg['add'];
            $lbl = esc($a['label'] ?? 'Add');
            if (! empty($a['modal'])) {
                $left .= '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#' . esc($a['modal'], 'attr') . '"><i class="bi bi-plus-lg me-1"></i>' . $lbl . '</button>';
            } elseif (! empty($a['href'])) {
                $left .= '<a href="' . esc($a['href'], 'attr') . '" class="btn btn-sm btn-primary" data-turbo="false"><i class="bi bi-plus-lg me-1"></i>' . $lbl . '</a>';
            }
        }

        if (! empty($cfg['delete'])) {
            $left .= '<button type="button" class="btn btn-sm btn-outline-danger" data-grid-delete data-url="'
                . esc($cfg['delete'], 'attr') . '" disabled><i class="bi bi-trash me-1"></i>Delete <span data-grid-count>0</span></button>';
        }

        if (! empty($cfg['import'])) {
            $left .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"'
                . ' data-import-type="' . esc($cfg['import'], 'attr') . '"'
                . ' data-import-ret="' . esc($cfg['ret'] ?? current_url(), 'attr') . '">'
                . '<i class="bi bi-upload me-1"></i>Import</button>';
        }

        if (! empty($cfg['filter'])) {
            $badge = '';
            if (isset($cfg['filter_count'])) {
                $n = (int) $cfg['filter_count'];
                $badge = '<span class="filter-count' . ($n ? '' : ' d-none') . '" id="' . esc($cfg['filter_count_id'] ?? 'gridFilterCount', 'attr') . '">' . $n . '</span>';
            }
            $right .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="'
                . esc($cfg['filter'], 'attr') . '"><i class="bi bi-funnel me-1"></i>Filter' . $badge . '</button>';
        }

        if (! empty($cfg['export'])) {
            $e = is_array($cfg['export']) ? $cfg['export'] : ['key' => $cfg['export']];
            $right .= export_menu($e['key'], $cfg['export_qs'] ?? []);
        }

        if (! empty($cfg['columns'])) {
            $right .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-grid-columns><i class="bi bi-layout-three-columns me-1"></i>Columns</button>';
        }

        if (! empty($cfg['history'])) {
            $h = $cfg['history'];
            $right .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal"'
                . ' data-history-prefix="' . esc($h['prefix'], 'attr') . '"'
                . ' data-history-label="' . esc($h['label'] ?? 'records', 'attr') . '">'
                . '<i class="bi bi-clock-history me-1"></i>History</button>';
        }

        if (! empty($cfg['right'])) {
            $right .= $cfg['right'];
        }

        return '<div class="grid-toolbar d-flex flex-wrap gap-2 align-items-center mb-3" data-grid>'
            . $left
            . ($right !== '' ? '<span class="ms-auto d-flex flex-wrap gap-2">' . $right . '</span>' : '')
            . '</div>'
            . grid_shared_modals();
    }
}

if (! function_exists('filter_builder')) {
    /**
     * ZKBioTime-style advanced Filter builder in an offcanvas panel, plus the
     * "Filter" toolbar button (with a live count) and Bookmarks (saved filter
     * sets, kept per-page in localStorage). The matching row/bookmark logic is
     * in tt-grid.js; the server side is BaseController::filterBuilder().
     *
     * @param string $id     unique panel id, e.g. 'empFilter'
     * @param array  $fields  key => ['label'=>, 'type'=>text|number|date|bool|select, 'options'=>[val=>label]]
     * @param array  $opts    'button' => bool (also render the toolbar button, default true)
     *                        'count'  => int  (applied-clause count for the badge)
     *                        'extra'  => string html injected above the rows (legacy quick filters)
     */
    function filter_builder(string $id, array $fields, array $opts = []): string
    {
        $count  = (int) ($opts['count'] ?? 0);
        $button = $opts['button'] ?? true;

        $fieldJson = [];
        foreach ($fields as $k => $f) {
            $fieldJson[$k] = [
                'label'   => $f['label'] ?? ucfirst(str_replace('_', ' ', $k)),
                'type'    => $f['type'] ?? 'text',
                'options' => $f['options'] ?? null,
            ];
        }
        $json = esc(json_encode($fieldJson, JSON_UNESCAPED_SLASHES), 'attr');

        $btn = $button
            ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#' . esc($id, 'attr') . 'Panel">'
                . '<i class="bi bi-funnel me-1"></i>Filter'
                . '<span class="filter-count' . ($count ? '' : ' d-none') . '" data-filter-count>' . $count . '</span></button>'
            : '';

        $extra = $opts['extra'] ?? '';

        $panel = <<<HTML
<div class="offcanvas offcanvas-end" tabindex="-1" id="{$id}Panel" data-filter-builder data-fields="{$json}">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title"><i class="bi bi-funnel me-2"></i>Filter</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column gap-3">
    <div class="d-flex align-items-center gap-2">
      <div class="btn-group btn-group-sm flex-grow-1">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle w-100" data-bs-toggle="dropdown">
          <i class="bi bi-bookmark me-1"></i>Bookmarks
        </button>
        <ul class="dropdown-menu w-100" data-bookmark-list></ul>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bookmark-save title="Save current filter">
        <i class="bi bi-bookmark-plus"></i>
      </button>
    </div>
    {$extra}
    <form method="get" data-crud-filter class="d-flex flex-column gap-2 flex-grow-1">
      <div data-filter-rows class="d-flex flex-column gap-2"></div>
      <button type="button" class="btn btn-sm btn-link text-decoration-none align-self-start px-0" data-filter-add>
        <i class="bi bi-plus-circle me-1"></i>Add condition
      </button>
      <div class="mt-auto d-flex gap-2 pt-3 border-top">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Apply</button>
        <button type="button" class="btn btn-outline-secondary" data-filter-reset>Reset</button>
      </div>
    </form>
  </div>
</div>
HTML;

        return $btn . $panel;
    }
}

if (! function_exists('export_menu')) {
    /**
     * ZKBioTime-style Export dropdown — CSV / Excel / PDF / TXT, each a link to
     * Exports::run/<key> with the current list filters plus ?format=.
     *
     * @param array $qs GET filters to carry into the export (e.g. year, dept, q)
     */
    function export_menu(string $key, array $qs = []): string
    {
        $base = route_to('exports.run', $key);
        $link = static function (string $fmt) use ($base, $qs): string {
            $q = http_build_query($qs + ['format' => $fmt]);

            return esc($base . '?' . $q, 'attr');
        };

        return '<div class="btn-group">'
            . '<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">'
            . '<i class="bi bi-box-arrow-up me-1"></i>Export</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            . '<li><a class="dropdown-item" href="' . $link('csv') . '"><i class="bi bi-filetype-csv me-2"></i>CSV Export</a></li>'
            . '<li><a class="dropdown-item" href="' . $link('pdf') . '" target="_blank"><i class="bi bi-filetype-pdf me-2"></i>PDF Export</a></li>'
            . '<li><a class="dropdown-item" href="' . $link('xlsx') . '"><i class="bi bi-file-earmark-excel me-2"></i>Excel Export</a></li>'
            . '<li><a class="dropdown-item" href="' . $link('txt') . '"><i class="bi bi-filetype-txt me-2"></i>TXT Export</a></li>'
            . '</ul></div>';
    }
}

if (! function_exists('grid_shared_modals')) {
    /** The Import + History modal shells — emitted once per page by grid_toolbar(). */
    function grid_shared_modals(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;

        $csrf = csrf_field();
        $importAction = site_url('admin/imports/__TYPE__/run');

        return <<<HTML
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post" enctype="multipart/form-data" id="importModalForm" action="">
        {$csrf}
        <input type="hidden" name="return" value="">
        <div class="modal-header"><h5 class="modal-title">Import</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label tiny">Import File <span class="text-danger">*</span></label>
                    <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.csv,.txt" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label tiny">Language <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" disabled><option>English</option></select>
                </div>
                <div class="col-md-5">
                    <label class="form-label tiny">Existing Data <span class="text-danger">*</span></label>
                    <select name="existing" class="form-select form-select-sm">
                        <option value="ignore">Ignore</option>
                        <option value="overwrite">Overwrite</option>
                    </select>
                </div>
                <div class="col-md-7 d-flex align-items-end">
                    <a href="#" id="importModalTemplate" class="small"><i class="bi bi-download me-1"></i>Download Template</a>
                </div>
            </div>
            <div class="mt-3">
                <div class="tiny text-muted mb-1">Template Demo</div>
                <div class="table-responsive"><table class="table table-sm table-bordered mb-0 tiny" id="importModalDemo"><tbody></tbody></table></div>
            </div>
            <div class="mt-3">
                <div class="tiny text-muted mb-1">Description</div>
                <ul class="tiny text-muted mb-0 ps-3" id="importModalNotes"></ul>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </form>
</div></div></div>
<script type="application/json" id="importModalSpecs">{}</script>
<script>window.__importAction = "{$importAction}";</script>

<div class="modal fade" id="historyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Target</th></tr></thead>
            <tbody id="historyModalBody"><tr><td colspan="4" class="text-muted small py-3">Loading…</td></tr></tbody>
        </table></div>
    </div>
</div></div></div>
HTML;
    }
}

if (! function_exists('grid_import_specs_json')) {
    /**
     * Emit the per-type import spec (headers, sample rows, notes) as JSON for
     * the modal's demo table. Call once on any page that shows an Import button.
     */
    function grid_import_specs_json(array $types): string
    {
        $out = [];
        foreach ($types as $t) {
            $s = \App\Controllers\Imports::spec($t);
            if ($s) {
                $out[$t] = [
                    'label'   => $s['label'],
                    'headers' => array_keys($s['cols']),
                    'sample'  => $s['sample'],
                    'notes'   => $s['notes'],
                ];
            }
        }

        return '<script>Object.assign(JSON.parse(document.getElementById("importModalSpecs").textContent||"{}"),'
            . json_encode($out) . ') && (document.getElementById("importModalSpecs").textContent = JSON.stringify('
            . json_encode($out) . '));</script>';
    }
}
