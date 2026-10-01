<?php

use App\Libraries\AttendanceCalculator;

if (! function_exists('fdate')) {
    /**
     * Display any date / datetime value (DB rows come back 'YYYY-MM-DD...')
     * as dd/mm/yyyy — the app-wide default date format. Pass $withTime to
     * also append HH:MM. Blank/null input returns the dash placeholder.
     */
    function fdate($value, bool $withTime = false, string $blank = '—'): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return $blank;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return esc($value);
        }

        return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    }
}

if (! function_exists('mins')) {
    /** Minutes -> "7:24" ; blank when zero/empty. */
    function mins($minutes, bool $blankZero = true): string
    {
        $minutes = (int) $minutes;
        if ($blankZero && $minutes <= 0) {
            return '—';
        }

        return AttendanceCalculator::hhmm($minutes);
    }
}

if (! function_exists('status_pill')) {
    function status_pill(string $status, string $class): string
    {
        return '<span class="pill pill-' . esc($class, 'attr') . '">' . esc($status) . '</span>';
    }
}

if (! function_exists('emp_avatar')) {
    /** Photo <img> or initials block. $emp needs name/first_name + optional photo. */
    function emp_avatar(array $emp, string $size = ''): string
    {
        $name  = $emp['name'] ?? trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) ?: ($emp['emp_code'] ?? '?');
        $cls   = 'emp-photo' . ($size ? ' ' . $size : '');
        $photo = $emp['photo'] ?? null;
        if ($photo) {
            // ZKBioTime stores "photo/12.jpg" – media not shipped, fall through to initials
            $url = base_url('assets/photos/' . basename($photo));
            return '<img src="' . esc($url, 'attr') . '" class="' . $cls . '" alt="" '
                . 'onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\'' . $cls . ' emp-initials\',textContent:\'' . esc(strtoupper(mb_substr($name, 0, 1)), 'js') . '\'}))">';
        }
        $init = strtoupper(mb_substr($name, 0, 1));

        return '<span class="' . $cls . ' emp-initials">' . esc($init) . '</span>';
    }
}

if (! function_exists('emp_name')) {
    function emp_name(array $emp): string
    {
        $n = $emp['name'] ?? null;
        if ($n) {
            return $n;
        }
        $n = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));

        return $n !== '' ? $n : ('#' . ($emp['emp_code'] ?? $emp['id'] ?? '?'));
    }
}

if (! function_exists('page_head')) {
    /**
     * SmartHR-style page header: title + breadcrumb trail, with optional
     * right-aligned action HTML. The breadcrumb defaults to Home › {title};
     * pass $crumbs as [['Label','url'], ['Label']] to set a custom trail
     * (the last entry is the current page and is not linked).
     */
    function page_head(string $title, string $sub = '', string $actions = '', array $crumbs = []): string
    {
        if ($crumbs === []) {
            $crumbs = [['TimeTrack', route_to('dashboard')], [$title]];
        }
        // legacy calls pass 'Home' as the root — show the product name instead
        if (isset($crumbs[0][0]) && $crumbs[0][0] === 'Home') {
            $crumbs[0][0] = 'TimeTrack';
        }

        $trail = '';
        $last  = count($crumbs) - 1;
        foreach ($crumbs as $i => $c) {
            if ($i > 0) {
                $trail .= '<i class="bi bi-chevron-right sep"></i>';
            }
            if ($i === $last) {
                $trail .= '<span class="here">' . esc($c[0]) . '</span>';
            } elseif (isset($c[1])) {
                $trail .= '<a href="' . esc($c[1], 'attr') . '">' . esc($c[0]) . '</a>';
            } else {
                $trail .= '<span>' . esc($c[0]) . '</span>';
            }
        }

        $h  = '<div class="page-head">';
        $h .= '<h1>' . esc($title) . '</h1>';
        $h .= '<nav class="crumb" aria-label="Breadcrumb">' . $trail . '</nav>';
        if ($actions !== '') {
            $h .= '<div class="actions">' . $actions . '</div>';
        }
        return $h . '</div>';
    }
}

if (! function_exists('modal_btn')) {
    function modal_btn(string $target, string $label, string $icon = 'plus-lg', string $cls = 'btn-primary'): string
    {
        return '<button class="btn btn-sm ' . $cls . '" data-bs-toggle="modal" data-bs-target="#' . esc($target, 'attr') . '">'
            . '<i class="bi bi-' . esc($icon, 'attr') . ' me-1"></i>' . esc($label) . '</button>';
    }
}

if (! function_exists('export_dropdown')) {
    /**
     * Excel/CSV/PDF/TXT export menu for a page_head() actions slot — $base
     * is the export route URL with its query string already built, no
     * trailing format. Each link carries .js-export-link, which
     * tt-grid.js's Columns button picks up: on click it appends the
     * currently-visible data-col keys as ?cols=, so a hidden column never
     * shows up in the download either. Only takes effect on a page that
     * also has a [data-grid-columns] button (see modal_btn-style usage
     * alongside this).
     */
    function export_dropdown(string $base): string
    {
        $base = rtrim($base, '&?');
        $sep  = str_contains($base, '?') ? '&' : '?';
        $url  = static fn (string $fmt) => esc($base . $sep . 'format=' . $fmt, 'attr');

        return '<div class="btn-group">'
            . '<button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-box-arrow-up me-1"></i>Export</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">'
            .   '<li><a class="dropdown-item js-export-link" href="' . $url('xlsx') . '"><i class="bi bi-file-earmark-excel me-2"></i>Excel Export</a></li>'
            .   '<li><a class="dropdown-item js-export-link" href="' . $url('csv') . '"><i class="bi bi-filetype-csv me-2"></i>CSV Export</a></li>'
            .   '<li><a class="dropdown-item js-export-link" href="' . $url('pdf') . '" target="_blank"><i class="bi bi-filetype-pdf me-2"></i>PDF Export</a></li>'
            .   '<li><a class="dropdown-item js-export-link" href="' . $url('txt') . '"><i class="bi bi-filetype-txt me-2"></i>TXT Export</a></li>'
            . '</ul>'
            . '</div>';
    }
}

if (! function_exists('code_pill')) {
    /**
     * Monthly Status's day-cell short code (P/A/L/E/H/W/V — combined
     * space-separated, e.g. "P L") as a colored pill. One place for the
     * code→color mapping so the legend and the table cells can never drift
     * apart. Priority when combined: Absent > Leave > Holiday/Weekend >
     * Late/Early > Present.
     */
    function code_pill(string $code): string
    {
        if ($code === '') {
            return '<span class="text-muted">—</span>';
        }
        $cls = match (true) {
            str_contains($code, 'A')                              => 'danger',
            $code === 'V'                                         => 'info',
            $code === 'H' || $code === 'W'                        => 'muted',
            str_contains($code, 'L') || str_contains($code, 'E')   => 'warning',
            default                                                => 'success',
        };

        return '<span class="pill pill-' . $cls . '">' . esc($code) . '</span>';
    }
}

/* ==========================================================================
   Sneat design-system component builders — reusable across every view.
   ========================================================================== */

if (! function_exists('label')) {
    /**
     * Sneat soft label badge: tinted background, solid text.
     *   label('Active', 'success')  →  <span class="badge bg-label-success">Active</span>
     *
     * @param string $variant primary|success|danger|warning|info|secondary|dark
     */
    function label(string $text, string $variant = 'primary', ?string $icon = null): string
    {
        $i = $icon ? '<i class="bi bi-' . esc($icon, 'attr') . ' me-1"></i>' : '';

        return '<span class="badge bg-label-' . esc($variant, 'attr') . '">' . $i . esc($text) . '</span>';
    }
}

if (! function_exists('ui_avatar')) {
    /**
     * Sneat .avatar element — initials block or photo, with optional status
     * dot and size. Sizes: xs · sm-box · '' (2.5rem) · md · lg · xl.
     *
     * @param string $seed  a name/code — first letter becomes the initial
     * @param array{size?:string,src?:?string,status?:?string,square?:bool} $o
     */
    function ui_avatar(string $seed, array $o = []): string
    {
        $size   = $o['size']   ?? '';
        $src    = $o['src']    ?? null;
        $status = $o['status'] ?? null;                 // 'online' | 'offline'
        $sq     = ! empty($o['square']);

        $cls = 'avatar';
        if ($size)   { $cls .= ' avatar-' . $size; }
        if ($sq)     { $cls .= ' rounded'; }
        if ($status) { $cls .= ' avatar-' . ($status === 'online' ? 'online' : 'offline'); }

        $inner = $src
            ? '<img src="' . esc($src, 'attr') . '" alt="">'
            : '<span class="avatar-initial">' . esc(mb_substr(trim($seed) !== '' ? $seed : '?', 0, 1)) . '</span>';

        return '<span class="' . $cls . '">' . $inner . '</span>';
    }
}

if (! function_exists('card_open')) {
    /** Open a Sneat card. Pair with card_close(). $actions is raw HTML shown on the right of the header. */
    function card_open(string $title = '', string $actions = '', string $bodyClass = 'card-body'): string
    {
        $h = '<div class="card">';
        if ($title !== '' || $actions !== '') {
            $h .= '<div class="card-header">' . esc($title);
            if ($actions !== '') {
                $h .= '<span class="ms-auto d-flex gap-2">' . $actions . '</span>';
            }
            $h .= '</div>';
        }

        return $h . ($bodyClass !== '' ? '<div class="' . esc($bodyClass, 'attr') . '">' : '');
    }
}
if (! function_exists('card_close')) {
    function card_close(bool $hadBody = true): string
    {
        return ($hadBody ? '</div>' : '') . '</div>';
    }
}

if (! function_exists('stat_tile')) {
    /**
     * Dashboard-style KPI tile. $tone is a stat-icon class: si-indigo (brand),
     * si-green, si-red, si-amber, si-blue, si-slate. Optional $href makes it a link.
     */
    function stat_tile(string $labelText, $value, string $icon, string $tone = 'si-slate', string $sub = '', ?string $href = null): string
    {
        $tag  = $href ? 'a' : 'div';
        $attr = $href ? ' href="' . esc($href, 'attr') . '"' : '';

        return '<' . $tag . ' class="card stat-card h-100 text-decoration-none text-reset"' . $attr . '><div class="card-body">'
            . '<div class="d-flex justify-content-between align-items-start">'
            . '<div><div class="stat-label">' . esc($labelText) . '</div>'
            . '<div class="stat-value">' . esc($value) . '</div></div>'
            . '<div class="stat-icon ' . esc($tone, 'attr') . '"><i class="bi bi-' . esc($icon, 'attr') . '"></i></div>'
            . '</div>'
            . ($sub !== '' ? '<div class="stat-sub mt-1">' . esc($sub) . '</div>' : '')
            . '</div></' . $tag . '>';
    }
}

if (! function_exists('punch_chip')) {
    function punch_chip(array $p): string
    {
        $mod = match ($p['state']) {
            0, 4 => 'in',
            1, 5 => 'out',
            2, 3 => 'brk',
            default => '',
        };

        return '<span class="punch-chip ' . $mod . '" title="' . esc($p['label'] . ' · ' . $p['device'], 'attr') . '">'
            . '<span class="dot"></span>' . esc($p['time']) . '</span>';
    }
}

/* -------------------------------------------------------------------------
 *  Form validation helpers — pair with the global "needs-validation" JS in
 *  layouts/admin.php. `req()` marks a label; `err_class()` / `err_text()`
 *  surface the server-side errors a controller flashes with
 *  ->with('errors', $validator->getErrors()).
 * ---------------------------------------------------------------------- */
if (! function_exists('req')) {
    /** Red asterisk to append after a required field's label text. */
    function req(): string
    {
        return ' <span class="req-star" aria-hidden="true">*</span>';
    }
}

if (! function_exists('field_errors')) {
    function field_errors(): array
    {
        $e = session()->getFlashdata('errors');

        return is_array($e) ? $e : [];
    }
}

if (! function_exists('err_class')) {
    /** ' is-invalid' when the named field failed server-side validation. */
    function err_class(string $field): string
    {
        return isset(field_errors()[$field]) ? ' is-invalid' : '';
    }
}

if (! function_exists('err_text')) {
    /** The <div class="invalid-feedback"> for a field, or '' when it validated. */
    function err_text(string $field): string
    {
        $errs = field_errors();

        return isset($errs[$field])
            ? '<div class="invalid-feedback d-block">' . esc($errs[$field]) . '</div>'
            : '';
    }
}

if (! function_exists('pager_bar')) {
    /**
     * SmartHR-style list footer: "Showing 1–15 of 58" + a rows-per-page
     * <select> (writes ?per and drops ?page, keeping every other query var)
     * on the left, the page links on the right.
     *
     * @param \CodeIgniter\Pager\Pager|null $pager
     */
    function pager_bar($pager, array $options = [10, 15, 25, 50, 100], string $group = 'default'): string
    {
        if (! $pager) {
            return '';
        }
        $per   = (int) $pager->getPerPage($group);
        $page  = max(1, (int) $pager->getCurrentPage($group));
        $total = (int) $pager->getTotal($group);
        $from  = $total === 0 ? 0 : (($page - 1) * $per) + 1;
        $to    = min($page * $per, $total);

        if (! in_array($per, $options, true)) {
            $options[] = $per;
            sort($options);
        }
        $opts = '';
        foreach ($options as $o) {
            $opts .= '<option value="' . (int) $o . '"' . ((int) $o === $per ? ' selected' : '') . '>' . (int) $o . '</option>';
        }

        $perParam  = $group === 'default' ? 'per' : 'per_' . $group;
        $pageParam = $group === 'default' ? 'page' : 'page_' . $group;

        return '<div class="pager-wrap">'
            . '<div class="range d-flex align-items-center gap-2">'
            .   '<span>Showing <b>' . $from . '</b>&ndash;<b>' . $to . '</b> of <b>' . number_format($total) . '</b></span>'
            .   '<select class="form-select w-auto" aria-label="Rows per page" data-native '
            .     'data-per-select data-per-param="' . esc($perParam, 'attr') . '" data-page-param="' . esc($pageParam, 'attr') . '">'
            .     $opts
            .   '</select>'
            .   '<span class="text-muted">per page</span>'
            . '</div>'
            . '<div>' . $pager->links($group) . '</div>'
            . '</div>';
    }
}
