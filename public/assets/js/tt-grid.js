/**
 * tt-grid.js — ZKBioTime-style list-page chrome:
 *   • bulk-select   (.grid-check rows + #gridCheckAll + [data-grid-delete])
 *   • column chooser ([data-grid-columns], th/td carry data-col)
 *   • import modal   (#importModal, populated from #importModalSpecs)
 *   • history modal  (#historyModal, lazy-loads /admin/history/feed)
 *
 * Everything re-binds after a [data-crud] swap (tt:swapped) and on Turbo load.
 */
(function () {
    'use strict';

    // ---- bulk select + delete ------------------------------------------------
    function bindBulk() {
        const bar = document.querySelector('[data-grid]');
        const del = document.querySelector('[data-grid-delete]');
        if (!bar || !del) return;

        const scope = document.querySelector('[data-crud-results]') || document;
        const boxes = () => [...scope.querySelectorAll('.grid-check')];
        const checked = () => boxes().filter((b) => b.checked);

        function refresh() {
            const n = checked().length;
            const cnt = del.querySelector('[data-grid-count]');
            if (cnt) cnt.textContent = n;
            del.disabled = n === 0;
            const all = scope.querySelector('#gridCheckAll');
            if (all) {
                all.checked = n > 0 && n === boxes().length;
                all.indeterminate = n > 0 && n < boxes().length;
            }
        }

        // Guard on `bar` (always a real Element here — proven by the check
        // above), not `scope`: scope can resolve to the bare `document`
        // object on pages with no [data-crud-results], and `document.dataset`
        // does not exist, which would throw if we guarded on it instead.
        if (!bar.dataset.bulkBound) {
            bar.dataset.bulkBound = '1';
            scope.addEventListener('change', (e) => {
                if (e.target.id === 'gridCheckAll') {
                    boxes().forEach((b) => (b.checked = e.target.checked));
                }
                if (e.target.classList.contains('grid-check') || e.target.id === 'gridCheckAll') refresh();
            });
        }

        if (!del.dataset.bound) {
            del.dataset.bound = '1';
            del.addEventListener('click', () => {
                const ids = checked().map((b) => b.value);
                if (!ids.length) return;
                if (!confirm('Delete ' + ids.length + ' selected record(s)?')) return;
                const f = document.createElement('form');
                f.method = 'post';
                f.action = del.dataset.url;
                f.innerHTML =
                    csrfInput() +
                    ids.map((id) => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
                document.body.appendChild(f);
                f.submit();
            });
        }
        refresh();
    }

    function csrfInput() {
        const m = document.querySelector('meta[name="csrf-token"]');
        const n = document.querySelector('meta[name="csrf-header"]');
        const name = (n && n.content) || 'csrf_test_name';
        return m ? '<input type="hidden" name="' + name + '" value="' + m.content + '">' : '';
    }

    // ---- column chooser ----------------------------------------------------
    function bindColumns() {
        const btn = document.querySelector('[data-grid-columns]');
        if (!btn || btn.dataset.bound) return;
        btn.dataset.bound = '1';

        function table() {
            const scope = document.querySelector('[data-crud-results]') || document;
            return (
                [...scope.querySelectorAll('table')].find((t) => t.querySelector('thead th[data-col]')) ||
                [...document.querySelectorAll('table')].find((t) => t.querySelector('thead th[data-col]')) ||
                null
            );
        }
        function defaultHidden() {
            const t = table();
            return t
                ? [...t.querySelectorAll('thead th[data-col][data-col-default-hidden]')].map((th) => th.dataset.col)
                : [];
        }
        // Version the storage key by the page's default-hidden set. When the
        // server changes which columns start hidden, the key changes and any
        // stale per-viewer choice is dropped (so the new default takes effect).
        function storageKey() {
            const s = defaultHidden().slice().sort().join(',');
            let h = 0;
            for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
            const v = (h >>> 0).toString(36);
            return 'tt-cols:' + location.pathname + (s ? ':' + v : '');
        }
        const key = storageKey();

        function cols() {
            const t = table();
            if (!t) return [];
            return [...t.querySelectorAll('thead th[data-col]')].map((th) => ({
                key: th.dataset.col,
                label: th.textContent.trim() || th.dataset.col,
            }));
        }
        function hidden() {
            try {
                const raw = localStorage.getItem(key);
                if (raw !== null) return JSON.parse(raw) || [];
            } catch (e) {}
            // first visit for this column set — seed from the page's defaults
            return defaultHidden();
        }
        function apply() {
            const t = table();
            if (!t) return;
            const h = hidden();
            // Single pass: index th[data-col] cells directly (not against
            // every child of the header row) and reuse that index across all
            // body rows in one querySelectorAll, instead of re-querying
            // `tbody tr` inside the per-column loop.
            const ths = [...t.querySelectorAll('thead th[data-col]')];
            const hiddenIdx = new Set();
            ths.forEach((th, i) => {
                const on = !h.includes(th.dataset.col);
                th.hidden = !on;
                if (!on) hiddenIdx.add(i);
            });
            t.querySelectorAll('tbody tr, tfoot tr').forEach((tr) => {
                [...tr.children].forEach((cell, i) => {
                    cell.hidden = hiddenIdx.has(i);
                });
            });
        }
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelector('.tt-col-pop')?.remove();
            const h = hidden();
            const pop = document.createElement('div');
            pop.className = 'tt-col-pop card shadow-sm p-2';
            pop.style.cssText = 'position:absolute;z-index:1080;min-width:180px;max-height:380px;overflow-y:auto;';
            pop.innerHTML = cols()
                .map(
                    (c) =>
                        '<label class="form-check d-block small mb-1"><input class="form-check-input" type="checkbox" value="' +
                        c.key +
                        '" ' +
                        (h.includes(c.key) ? '' : 'checked') +
                        '> ' +
                        c.label +
                        '</label>'
                )
                .join('');
            document.body.appendChild(pop);
            const r = btn.getBoundingClientRect();
            pop.style.top = window.scrollY + r.bottom + 4 + 'px';
            pop.style.left = window.scrollX + r.left + 'px';
            pop.addEventListener('change', (ev) => {
                const set = new Set(hidden());
                ev.target.checked ? set.delete(ev.target.value) : set.add(ev.target.value);
                localStorage.setItem(key, JSON.stringify([...set]));
                apply();
            });
            setTimeout(() => document.addEventListener('click', function close(ev) {
                if (!pop.contains(ev.target)) { pop.remove(); document.removeEventListener('click', close); }
            }));
        });
        apply();
        document.addEventListener('tt:swapped', apply);

        // Export links (built by the export_dropdown() helper) only send
        // the columns currently ticked on screen — same table/hidden-set
        // this Columns button already tracks, so it stays in sync for
        // free with whatever the viewer has toggled.
        document.addEventListener('click', (ev) => {
            const a = ev.target.closest('.js-export-link');
            if (!a) return;
            const t = table();
            if (!t) return;
            const visible = [...t.querySelectorAll('thead th[data-col]')]
                .filter((th) => !th.hidden)
                .map((th) => th.dataset.col);
            const url = new URL(a.getAttribute('href'), location.origin);
            if (visible.length) url.searchParams.set('cols', visible.join(','));
            else url.searchParams.delete('cols');
            a.setAttribute('href', url.pathname + url.search);
        }, true);
    }

    // ---- import modal ----------------------------------------------------
    function bindImport() {
        const modal = document.getElementById('importModal');
        if (!modal || modal.dataset.bound) return;
        modal.dataset.bound = '1';
        let specs = {};
        try { specs = JSON.parse(document.getElementById('importModalSpecs').textContent || '{}'); } catch (e) {}

        modal.addEventListener('show.bs.modal', (ev) => {
            const trg = ev.relatedTarget;
            if (!trg) return;
            const type = trg.getAttribute('data-import-type');
            const ret = trg.getAttribute('data-import-ret') || '';
            const spec = specs[type];
            const form = document.getElementById('importModalForm');
            form.action = (window.__importAction || '').replace('__TYPE__', type);
            form.querySelector('[name="return"]').value = ret;
            form.reset();
            modal.querySelector('.modal-title').textContent = 'Import' + (spec ? ' — ' + spec.label : '');

            const tpl = document.getElementById('importModalTemplate');
            if (spec && spec.tpl) { tpl.href = spec.tpl; tpl.style.display = ''; }
            else { tpl.style.display = 'none'; }

            const demo = document.querySelector('#importModalDemo tbody');
            const notes = document.getElementById('importModalNotes');
            demo.innerHTML = '';
            notes.innerHTML = '';
            if (spec) {
                const letters = spec.headers.map((_, i) => String.fromCharCode(65 + i));
                demo.insertAdjacentHTML('beforeend',
                    '<tr class="table-light"><th></th>' + letters.map((l) => '<th>' + l + '</th>').join('') + '</tr>');
                demo.insertAdjacentHTML('beforeend',
                    '<tr><td class="text-muted">1</td>' + spec.headers.map((h) => '<td><strong>' + esc(h) + '</strong></td>').join('') + '</tr>');
                spec.sample.forEach((row, i) => {
                    demo.insertAdjacentHTML('beforeend',
                        '<tr><td class="text-muted">' + (i + 2) + '</td>' +
                        spec.headers.map((_, c) => '<td>' + esc(row[c] == null ? '' : row[c]) + '</td>').join('') + '</tr>');
                });
                (spec.notes || []).forEach((n) => notes.insertAdjacentHTML('beforeend', '<li>' + esc(n) + '</li>'));
            }
        });
    }

    // ---- history modal --------------------------------------------------
    function bindHistory() {
        const modal = document.getElementById('historyModal');
        if (!modal || modal.dataset.bound) return;
        modal.dataset.bound = '1';
        modal.addEventListener('show.bs.modal', async (ev) => {
            const trg = ev.relatedTarget;
            const prefix = trg ? trg.getAttribute('data-history-prefix') : '';
            const label = trg ? trg.getAttribute('data-history-label') : 'records';
            modal.querySelector('.modal-title').textContent = 'History — ' + label;
            const body = document.getElementById('historyModalBody');
            body.innerHTML = '<tr><td colspan="4" class="text-muted small py-3">Loading…</td></tr>';
            try {
                const r = await fetch('/admin/history/feed?prefix=' + encodeURIComponent(prefix), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const rows = await r.json();
                body.innerHTML = rows.length
                    ? rows.map((x) =>
                          '<tr><td class="small text-nowrap">' + esc(x.when) + '</td><td class="small">' + esc(x.who) +
                          '</td><td class="small mono">' + esc(x.action) + '</td><td class="small">' + esc(x.target || '') + '</td></tr>'
                      ).join('')
                    : '<tr><td colspan="4" class="text-muted small py-3">No history yet.</td></tr>';
            } catch (e) {
                body.innerHTML = '<tr><td colspan="4" class="text-danger small py-3">Could not load history.</td></tr>';
            }
        });
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // ---- advanced filter builder + bookmarks ----------------------------
    const OPS = {
        text:   [['contains', 'contains'], ['not_contains', "doesn't contain"], ['eq', 'is'], ['starts', 'starts with'], ['ends', 'ends with'], ['empty', 'is empty'], ['not_empty', 'is not empty']],
        number: [['eq', '='], ['ne', '≠'], ['gt', '>'], ['gte', '≥'], ['lt', '<'], ['lte', '≤']],
        date:   [['eq', 'on'], ['before', 'before'], ['after', 'after'], ['between', 'between']],
        bool:   [['eq', 'is']],
        select: [['eq', 'is'], ['ne', 'is not']],
    };

    function bindFilterBuilder() {
        document.querySelectorAll('[data-filter-builder]').forEach((panel) => {
            if (panel.dataset.fbBound) return;
            panel.dataset.fbBound = '1';

            let fields = {};
            try { fields = JSON.parse(panel.dataset.fields || '{}'); } catch (e) {}
            const keys = Object.keys(fields);
            if (!keys.length) return;

            const rowsWrap = panel.querySelector('[data-filter-rows]');
            const form = panel.querySelector('form[data-crud-filter]');
            const bmKey = 'tt-bookmarks:' + location.pathname;

            function valueInput(type, idx, val, val2) {
                if (type === 'bool') {
                    return '<select class="form-select form-select-sm" name="fb[' + idx + '][val]">' +
                        '<option value="1"' + (val === '1' ? ' selected' : '') + '>Yes</option>' +
                        '<option value="0"' + (val === '0' ? ' selected' : '') + '>No</option></select>';
                }
                if (type === 'select') {
                    const f = fields[rowField(idx)] || {};
                    const opts = f.options || {};
                    return '<select class="form-select form-select-sm" name="fb[' + idx + '][val]"><option value="">—</option>' +
                        Object.keys(opts).map((k) => '<option value="' + esc(k) + '"' + (String(val) === String(k) ? ' selected' : '') + '>' + esc(opts[k]) + '</option>').join('') + '</select>';
                }
                const t = type === 'date' ? 'date' : (type === 'number' ? 'number' : 'text');
                return '<input type="' + t + '" class="form-control form-control-sm" name="fb[' + idx + '][val]" value="' + esc(val || '') + '">';
            }
            function rowField(idx) {
                const s = rowsWrap.querySelector('select[name="fb[' + idx + '][field]"]');
                return s ? s.value : keys[0];
            }

            function makeRow(data) {
                data = data || {};
                const idx = rowsWrap.children.length;
                const fkey = data.field && fields[data.field] ? data.field : keys[0];
                const type = fields[fkey].type || 'text';
                const op = data.op || (OPS[type] || OPS.text)[0][0];
                const div = document.createElement('div');
                div.className = 'fb-row d-flex gap-1 align-items-start';
                div.innerHTML =
                    '<select class="form-select form-select-sm" name="fb[' + idx + '][field]" style="flex:0 0 34%">' +
                    keys.map((k) => '<option value="' + k + '"' + (k === fkey ? ' selected' : '') + '>' + esc(fields[k].label) + '</option>').join('') + '</select>' +
                    '<select class="form-select form-select-sm" name="fb[' + idx + '][op]" style="flex:0 0 26%">' +
                    (OPS[type] || OPS.text).map((o) => '<option value="' + o[0] + '"' + (o[0] === op ? ' selected' : '') + '>' + esc(o[1]) + '</option>').join('') + '</select>' +
                    '<div class="fb-val flex-grow-1">' + valueInput(type, idx, data.val, data.val2) +
                    (op === 'between' ? '<input type="date" class="form-control form-control-sm mt-1" name="fb[' + idx + '][val2]" value="' + esc(data.val2 || '') + '">' : '') + '</div>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger px-1" data-fb-del><i class="bi bi-x-lg"></i></button>';
                rowsWrap.appendChild(div);
            }

            function rebuildValue(row) {
                const idx = [...rowsWrap.children].indexOf(row);
                const fkey = row.querySelector('select[name$="[field]"]').value;
                const type = (fields[fkey] || {}).type || 'text';
                const opSel = row.querySelector('select[name$="[op]"]');
                opSel.innerHTML = (OPS[type] || OPS.text).map((o) => '<option value="' + o[0] + '">' + esc(o[1]) + '</option>').join('');
                const valWrap = row.querySelector('.fb-val');
                const hideVal = opSel.value === 'empty' || opSel.value === 'not_empty';
                valWrap.innerHTML = hideVal ? '' : valueInput(type, idx, '', '');
                if (opSel.value === 'between') {
                    valWrap.insertAdjacentHTML('beforeend', '<input type="date" class="form-control form-control-sm mt-1" name="fb[' + idx + '][val2]">');
                }
            }

            rowsWrap.addEventListener('change', (e) => {
                const row = e.target.closest('.fb-row');
                if (!row) return;
                if (e.target.name.endsWith('[field]')) rebuildValue(row);
                if (e.target.name.endsWith('[op]')) {
                    const type = (fields[row.querySelector('select[name$="[field]"]').value] || {}).type || 'text';
                    const idx = [...rowsWrap.children].indexOf(row);
                    const valWrap = row.querySelector('.fb-val');
                    const v = e.target.value;
                    if (v === 'empty' || v === 'not_empty') valWrap.innerHTML = '';
                    else if (v === 'between') { valWrap.innerHTML = valueInput(type, idx, '', ''); valWrap.insertAdjacentHTML('beforeend', '<input type="date" class="form-control form-control-sm mt-1" name="fb[' + idx + '][val2]">'); }
                    else if (!valWrap.querySelector('input,select')) valWrap.innerHTML = valueInput(type, idx, '', '');
                    else if (valWrap.children.length > 1) valWrap.lastElementChild.remove();
                }
            });
            rowsWrap.addEventListener('click', (e) => {
                if (e.target.closest('[data-fb-del]')) {
                    e.target.closest('.fb-row').remove();
                    if (!rowsWrap.children.length) makeRow();
                }
            });
            panel.querySelector('[data-filter-add]').addEventListener('click', () => makeRow());
            panel.querySelector('[data-filter-reset]').addEventListener('click', () => {
                rowsWrap.innerHTML = '';
                makeRow();
                location.search = '';
            });

            // hydrate from URL
            const params = new URLSearchParams(location.search);
            const fromUrl = [];
            params.forEach((v, k) => {
                const m = k.match(/^fb\[(\d+)\]\[(field|op|val|val2)\]$/);
                if (m) { (fromUrl[+m[1]] = fromUrl[+m[1]] || {})[m[2]] = v; }
            });
            const seed = fromUrl.filter(Boolean);
            (seed.length ? seed : [{}]).forEach(makeRow);

            // ---- bookmarks ----
            const listEl = panel.querySelector('[data-bookmark-list]');
            function loadBM() { try { return JSON.parse(localStorage.getItem(bmKey) || '[]'); } catch (e) { return []; } }
            function saveBM(list) { try { localStorage.setItem(bmKey, JSON.stringify(list)); } catch (e) {} }
            function renderBM() {
                const list = loadBM();
                listEl.innerHTML = list.length
                    ? list.map((b, i) =>
                        '<li class="d-flex align-items-center"><a class="dropdown-item flex-grow-1" href="?' + esc(b.qs) + '">' + esc(b.name) + '</a>' +
                        '<button type="button" class="btn btn-sm btn-link text-danger px-2" data-bm-del="' + i + '"><i class="bi bi-trash"></i></button></li>').join('')
                    : '<li><span class="dropdown-item-text text-muted small">No saved filters</span></li>';
            }
            listEl.addEventListener('click', (e) => {
                const del = e.target.closest('[data-bm-del]');
                if (del) { e.preventDefault(); const l = loadBM(); l.splice(+del.dataset.bmDel, 1); saveBM(l); renderBM(); }
            });
            panel.querySelector('[data-bookmark-save]').addEventListener('click', () => {
                const name = prompt('Name this filter:');
                if (!name) return;
                const qs = new URLSearchParams(new FormData(form)).toString().replace(/(^|&)(csrf[^=]*=[^&]*)/g, '').replace(/^&/, '');
                const l = loadBM();
                l.push({ name: name.slice(0, 40), qs });
                saveBM(l);
                renderBM();
            });
            renderBM();
        });
    }

    function init() {
        bindBulk();
        bindColumns();
        bindImport();
        bindHistory();
        bindFilterBuilder();
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('turbo:load', init);
    document.addEventListener('tt:swapped', bindBulk);
})();