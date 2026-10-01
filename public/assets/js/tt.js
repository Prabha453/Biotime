/* ============================================================================
 * tt.js — TimeTrack front-end runtime
 * ----------------------------------------------------------------------------
 *  Loaded in <head> BEFORE Turbo. Provides:
 *   • window.TT           fetch wrapper + toast + inline-error helpers
 *   • one-time delegated UI (sidebar, theme, sub-menus, fullscreen, per-page)
 *   • per-navigation setup on `turbo:load` (validation, toasts, active menu…)
 *   • [data-crud]          no-reload CRUD regions (see conventions below)
 *
 *  Turbo Drive gives whole-app no-reload navigation; the sidebar is a
 *  `data-turbo-permanent` island so it keeps scroll + open sub-menu across
 *  page changes. Anything under `[data-crud]` is marked `data-turbo="false"`
 *  so Turbo leaves its links/forms to us.
 * ========================================================================== */
(function () {
    'use strict';

    const meta = (n) => document.querySelector('meta[name="' + n + '"]');

    // ---------------------------------------------------------------- TT core
    const TT = window.TT = {
        csrfName: (meta('csrf-name') || {}).content || 'csrf_test_name',
        csrfHash: (meta('csrf-token') || {}).content || '',

        _bump(res, jsonBody) {
            const h = res.headers.get('X-CSRF-TOKEN') || (jsonBody && jsonBody.csrf);
            if (h && h !== this.csrfHash) {
                this.csrfHash = h;
                const m = meta('csrf-token'); if (m) m.setAttribute('content', h);
                document.querySelectorAll('input[name="' + this.csrfName + '"]').forEach((i) => { i.value = h; });
            }
        },

        async fetch(url, opts) {
            opts = opts || {};
            const method = (opts.method || 'GET').toUpperCase();
            const init = { method, headers: { 'X-Requested-With': 'XMLHttpRequest' } };
            if (opts.form) {
                const fd = opts.form instanceof FormData ? opts.form : new FormData(opts.form);
                if (method !== 'GET') {
                    if (method !== 'POST') { fd.append('_method', method); init.method = 'POST'; }
                    fd.set(this.csrfName, this.csrfHash);
                }
                init.body = fd;
            } else if (opts.json) {
                init.headers['Content-Type'] = 'application/json';
                init.headers['X-CSRF-TOKEN'] = this.csrfHash;
                init.body = JSON.stringify(opts.json);
            }
            let res, data;
            try { res = await fetch(url, init); }
            catch (e) { this.toast('Network error — check your connection.', 'err'); return { ok: false, res: null, data: null }; }
            const isJson = (res.headers.get('content-type') || '').indexOf('json') !== -1;
            data = isJson ? await res.json().catch(() => null) : await res.text();
            this._bump(res, isJson ? data : null);
            return { ok: res.ok, res, data };
        },

        toast(msg, kind) {
            kind = kind || 'ok';
            let stack = document.querySelector('.toast-stack');
            if (!stack) { stack = document.createElement('div'); stack.className = 'toast-stack'; document.body.appendChild(stack); }
            const icon = kind === 'ok' ? 'check-circle-fill' : (kind === 'err' ? 'x-circle-fill' : 'exclamation-triangle-fill');
            const title = kind === 'ok' ? 'Success' : (kind === 'err' ? 'Error' : 'Warning');
            const el = document.createElement('div');
            el.className = 'toast-msg ' + kind;
            el.style.setProperty('--dur', '4s');
            el.innerHTML = '<i class="bi bi-' + icon + '"></i>'
                + '<div class="toast-body"><p class="toast-title"></p><p class="toast-text"></p></div>'
                + '<button class="toast-close" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>'
                + '<div class="toast-progress" style="animation-duration: var(--dur)"></div>';
            el.querySelector('.toast-title').textContent = title;
            el.querySelector('.toast-text').textContent = msg;
            stack.appendChild(el);
            setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = 0; setTimeout(() => el.remove(), 300); }, 4200);
        },

        /**
         * Styled dialog → Promise resolving to the clicked button's value.
         * opts: { title, body, bodyHtml, okText, okClass, danger, buttons:[{text,value,class}] }
         */
        dialog(opts) {
            opts = opts || {};
            return new Promise((resolve) => {
                if (!window.bootstrap) { resolve(window.confirm(opts.body || 'Are you sure?')); return; }
                const el = document.createElement('div');
                el.className = 'modal fade'; el.tabIndex = -1;
                const dlg = document.createElement('div');
                dlg.className = 'modal-dialog modal-dialog-centered';
                dlg.innerHTML =
                    '<div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5>'
                    + '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
                    + '<div class="modal-body"></div><div class="modal-footer"></div></div>';
                el.appendChild(dlg);
                el.querySelector('.modal-title').textContent = opts.title || 'Please confirm';
                const bodyEl = el.querySelector('.modal-body');
                if (opts.bodyHtml) bodyEl.innerHTML = opts.bodyHtml; else bodyEl.textContent = opts.body || '';
                const foot = el.querySelector('.modal-footer');
                const btns = opts.buttons || [
                    { text: 'Cancel', value: false, class: 'btn-outline-secondary' },
                    { text: opts.okText || 'OK', value: true, class: opts.okClass || (opts.danger ? 'btn-danger' : 'btn-primary') },
                ];
                let result = false;
                const inst = new bootstrap.Modal(el);
                btns.forEach((b) => {
                    const btn = document.createElement('button');
                    btn.type = 'button'; btn.className = 'btn ' + (b.class || 'btn-secondary'); btn.textContent = b.text;
                    btn.addEventListener('click', () => { result = b.value; inst.hide(); });
                    foot.appendChild(btn);
                });
                el.addEventListener('shown.bs.modal', () => foot.querySelector('.btn-primary, .btn-danger, .btn')?.focus());
                el.addEventListener('hidden.bs.modal', () => { el.remove(); resolve(result); }, { once: true });
                document.body.appendChild(el);
                inst.show();
            });
        },
        confirm(message, opts) {
            opts = opts || {};
            return this.dialog({
                title: opts.title || 'Please confirm', body: message,
                okText: opts.okText || 'Confirm', okClass: opts.danger ? 'btn-danger' : 'btn-primary',
            });
        },

        /**
         * Load a form partial into a shared modal, wire its submit through
         * TT.fetch, and handle {ok,message,errors,html}. opts: { onSaved(data) }
         */
        async modal(url, opts) {
            opts = opts || {};
            let el = document.getElementById('tt-remote-modal');
            if (!el) {
                el = document.createElement('div');
                el.id = 'tt-remote-modal'; el.className = 'modal fade'; el.tabIndex = -1;
                el.innerHTML = '<div class="modal-dialog"><div class="modal-content"></div></div>';
                document.body.appendChild(el);
            }
            const content = el.querySelector('.modal-content');
            content.innerHTML = '<div class="modal-body text-center py-5"><span class="tt-ring d-inline-block"></span></div>';
            const inst = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
            inst && inst.show();

            const r = await this.fetch(url, {});
            if (!r.ok || typeof r.data !== 'string') {
                content.innerHTML = '<div class="modal-body"><div class="alert alert-danger m-0">Could not load this form.</div></div>';
                return;
            }
            content.innerHTML = r.data;
            this.enhance(content);
            const form = content.querySelector('form');
            if (!form) return;
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (form.checkValidity && !form.checkValidity()) { form.classList.add('was-validated'); return; }
                this.clearErrors(form);
                const btn = form.querySelector('button[type="submit"], .modal-footer .btn-primary');
                if (btn) btn.disabled = true;
                const rr = await this.fetch(form.getAttribute('action'), { method: form.getAttribute('method') || 'POST', form });
                if (btn) btn.disabled = false;
                if (rr.ok && rr.data && rr.data.ok) {
                    inst && inst.hide();
                    this.toast(rr.data.message || 'Saved.', 'ok');
                    opts.onSaved && opts.onSaved(rr.data);
                } else if (rr.data && rr.data.errors) {
                    this.showErrors(form, rr.data.errors);
                } else {
                    this.toast((rr.data && rr.data.message) || 'Something went wrong.', 'err');
                }
            });
        },

        clearErrors(form) {
            form.querySelectorAll('.is-invalid').forEach((e) => e.classList.remove('is-invalid'));
            form.querySelectorAll('.ts-wrapper.is-invalid').forEach((e) => e.classList.remove('is-invalid'));
            form.querySelectorAll('.invalid-feedback.js-err').forEach((e) => e.remove());
        },
        showErrors(form, errors) {
            Object.keys(errors || {}).forEach((field) => {
                const input = form.querySelector('[name="' + field + '"]');
                if (!input) return;
                input.classList.add('is-invalid');
                if (input.tomselect) input.tomselect.wrapper.classList.add('is-invalid');
                const anchor = input.tomselect ? input.tomselect.wrapper : input;
                const fb = document.createElement('div');
                fb.className = 'invalid-feedback js-err d-block';
                fb.textContent = errors[field];
                anchor.parentNode.appendChild(fb);
            });
        },

        /** upgrade every plain <select>/date input inside root into Tom Select / flatpickr */
        enhance(root) {
            root = root || document;
            if (window.flatpickr) {
                root.querySelectorAll('input[type="date"]:not([data-fp])').forEach((el) => {
                    if (el.closest('[data-no-enhance]')) return;
                    el.dataset.fp = '1';
                    flatpickr(el, {
                        dateFormat: 'Y-m-d',        // the value that is submitted / stored
                        altInput: true,             // visible field shown to the user ↓
                        altFormat: 'd/m/Y',         // as dd/mm/yyyy
                        altInputClass: el.className || 'form-control',
                        allowInput: true,
                        disableMobile: true,
                        defaultDate: el.value || null,
                    });
                });
            }
            if (window.TomSelect) {
                root.querySelectorAll('select.form-select:not(.tomselected), select[multiple]:not(.tomselected)').forEach((el) => {
                    if (el.dataset.native !== undefined || el.closest('[data-no-enhance]')) return;
                    const many = el.multiple;
                    const selectAll = many && el.dataset.selectAll !== undefined;
                    const ts = new TomSelect(el, {
                        maxOptions: null,
                        allowEmptyOption: true,
                        hideSelected: many,
                        dropdownParent: el.closest('.modal, .offcanvas') ? 'body' : null,
                        plugins: many ? { remove_button: { title: 'Remove' } } : [],
                        controlInput: (many || el.options.length >= 6) ? '<input>' : null,
                        render: { no_results: (d, esc) => '<div class="no-results">No match for "' + esc(d.input) + '"</div>' },
                    });
                    // Select all / Clear all toolbar for a big multi-select
                    // (e.g. "assign shift to every employee, then uncheck a
                    // few") — opt in per <select> via data-select-all.
                    if (selectAll) {
                        const bar = document.createElement('div');
                        bar.className = 'tt-select-all-bar mb-1';
                        bar.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2">Select all</button> '
                            + '<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2">Clear all</button>';
                        ts.wrapper.parentNode.insertBefore(bar, ts.wrapper);
                        const [allBtn, noneBtn] = bar.querySelectorAll('button');
                        allBtn.addEventListener('click', () => ts.setValue(Object.keys(ts.options)));
                        noneBtn.addEventListener('click', () => ts.clear());
                    }
                });
            }
            // file / image drop-zone widget (field_image → .tt-upload)
            root.querySelectorAll('[data-tt-upload]:not([data-ttu])').forEach((box) => {
                box.dataset.ttu = '1';
                const input = box.querySelector('input[type="file"]');
                const img = box.querySelector('.tt-upload-img');
                const empty = box.querySelector('.tt-upload-empty');
                if (!input) return;
                const show = (file) => {
                    if (!file) return;
                    if (img) { img.src = URL.createObjectURL(file); img.hidden = false; }
                    if (empty) empty.hidden = true;
                    const rm = document.getElementById(input.id + '_rm');
                    if (rm) rm.checked = false;
                };
                input.addEventListener('change', () => show(input.files && input.files[0]));
                box.addEventListener('dragover', (e) => { e.preventDefault(); box.classList.add('is-drag'); });
                box.addEventListener('dragleave', () => box.classList.remove('is-drag'));
                box.addEventListener('drop', (e) => {
                    e.preventDefault(); box.classList.remove('is-drag');
                    const f = e.dataTransfer.files && e.dataTransfer.files[0];
                    if (f) { input.files = e.dataTransfer.files; show(f); }
                });
            });
        },
        unenhance(root) {
            root = root || document;
            root.querySelectorAll('.tomselected').forEach((el) => el.tomselect && el.tomselect.destroy());
            root.querySelectorAll('input[data-fp]').forEach((el) => {
                if (el._flatpickr) el._flatpickr.destroy();
                delete el.dataset.fp;
            });
        },

        /** call after form.reset() or programmatic <select>.value/<input type=date> changes */
        syncForm(form) {
            if (!form) return;
            form.querySelectorAll('select.tomselected').forEach((s) => s.tomselect && s.tomselect.sync());
            form.querySelectorAll('input[data-fp]').forEach((i) => i._flatpickr && i._flatpickr.setDate(i.value || null, false));
        },
    };

    // ---------------------------------------------- one-time delegated UI ---
    // capture-phase validation — runs before Turbo's form observer, so an
    // invalid form never submits (via Turbo or otherwise).
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        form.setAttribute('novalidate', '');
        if (form.checkValidity()) { form.classList.add('was-validated'); return; }
        e.preventDefault(); e.stopImmediatePropagation();
        form.classList.add('was-validated');
        // reflect invalid state onto Tom Select wrappers (the real <select> is hidden)
        form.querySelectorAll('select:invalid').forEach((s) => s.tomselect && s.tomselect.wrapper.classList.add('is-invalid'));
        form.querySelectorAll('select:valid').forEach((s) => s.tomselect && s.tomselect.wrapper.classList.remove('is-invalid'));
        const bad = form.querySelector(':invalid');
        if (!bad) return;
        const modal = bad.closest('.modal');
        const pane = bad.closest('.tab-pane');
        if (modal && !modal.classList.contains('show') && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
            modal.addEventListener('shown.bs.modal', () => bad.focus(), { once: true });
        } else if (pane && !pane.classList.contains('active') && window.bootstrap) {
            const tabBtn = document.querySelector('[data-bs-target="#' + pane.id + '"]');
            if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            setTimeout(() => bad.focus(), 150);
        } else {
            bad.focus();
        }
    }, true);

    // capture-phase: form[data-confirm] → styled dialog before submit (replaces
    // inline onsubmit="return confirm(...)"). Re-submits with a pass flag when OK.
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
        e.preventDefault(); e.stopImmediatePropagation();
        TT.confirm(form.dataset.confirm, { danger: form.dataset.confirmDanger !== undefined, okText: form.dataset.confirmOk || 'Confirm' })
            .then((ok) => { if (ok) { form.dataset.confirmed = '1'; form.requestSubmit ? form.requestSubmit() : form.submit(); } });
    }, true);

    // Submit feedback: disable the button and show a spinner for the
    // round-trip so a slow save can't be double-clicked into duplicate
    // rows, and the user isn't left wondering if the click registered.
    // Every POST here ends in a redirect (success or back-with-errors),
    // so Turbo replaces the DOM shortly after — this only needs to cover
    // the in-flight gap, with submit-end as a safety net if it doesn't.
    document.addEventListener('turbo:submit-start', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const submitter = e.detail && e.detail.formSubmission && e.detail.formSubmission.submitter;
        const btn = (submitter instanceof HTMLButtonElement && submitter.type !== 'button')
            ? submitter
            : form.querySelector('button.btn-primary:not([data-bs-dismiss]), button[type="submit"]:not([data-bs-dismiss])');
        if (!btn || btn.disabled) return;
        btn.dataset.ttLabel = btn.innerHTML;
        btn.disabled = true;
        btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>');
    });
    document.addEventListener('turbo:submit-end', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        form.querySelectorAll('button[data-tt-label]').forEach((btn) => {
            btn.innerHTML = btn.dataset.ttLabel;
            delete btn.dataset.ttLabel;
            btn.disabled = false;
        });
    });

    document.addEventListener('click', (e) => {
        // Cancel / X buttons inside a <form> must never submit it. A plain
        // <button> with no explicit type="button" defaults to type="submit",
        // so a modal's Cancel/close button that forgot the attribute was
        // silently POSTing the form (Turbo intercepts the submit) and
        // showing the real success flash even though nothing was filled in.
        const dismiss = e.target.closest('[data-bs-dismiss="modal"], [data-bs-dismiss="offcanvas"]');
        if (dismiss && dismiss.tagName === 'BUTTON' && dismiss.type !== 'button' && dismiss.form) {
            e.preventDefault();
            return;
        }
        // dismiss a flash/JS toast
        const tc = e.target.closest('.toast-close');
        if (tc) {
            const t = tc.closest('.toast-msg');
            if (t) { t.style.transition = 'opacity .2s'; t.style.opacity = 0; setTimeout(() => t.remove(), 200); }
            return;
        }
        // remote form modal: <a data-tt-modal href="/…/form"> or [data-tt-modal="url"]
        const rm = e.target.closest('[data-tt-modal]');
        if (rm) {
            e.preventDefault();
            const url = rm.dataset.ttModal || rm.getAttribute('href');
            TT.modal(url, { onSaved: () => window.Turbo ? Turbo.visit(location.href, { action: 'replace' }) : location.reload() });
            return;
        }
        // mobile sidebar
        if (e.target.closest('#sidebarToggle')) {
            document.getElementById('sidebar')?.classList.toggle('open');
            document.getElementById('sidebarBackdrop')?.classList.toggle('show');
            return;
        }
        if (e.target.closest('#sidebarBackdrop')) {
            document.getElementById('sidebar')?.classList.remove('open');
            document.getElementById('sidebarBackdrop')?.classList.remove('show');
            return;
        }
        // collapsible sub-menu — one open at a time
        const tree = e.target.closest('.nav-tree');
        if (tree) {
            e.preventDefault();
            const wasOpen = tree.classList.contains('is-open');
            document.querySelectorAll('.nav-tree.is-open').forEach((o) => {
                if (o !== tree) { o.classList.remove('is-open'); o.setAttribute('aria-expanded', 'false'); }
            });
            tree.classList.toggle('is-open', !wasOpen);
            tree.setAttribute('aria-expanded', String(!wasOpen));
            return;
        }
        // fullscreen
        if (e.target.closest('#fsToggle')) {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
            else document.exitFullscreen?.();
            return;
        }
        // theme
        if (e.target.closest('#themeToggle')) {
            const root = document.documentElement;
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('tt-theme', next); } catch (err) {}
            syncThemeIcon();
        }
    });

    // any page's modal fill-logic runs on show.bs.modal by setting <select>.value;
    // re-sync Tom Select / flatpickr once the modal is fully shown.
    document.addEventListener('shown.bs.modal', (e) => {
        const form = e.target.querySelector && e.target.querySelector('form');
        if (form) TT.syncForm(form);
    });

    // rows-per-page selector — plain nav (a [data-crud] scope intercepts its own)
    document.addEventListener('change', (e) => {
        const s = e.target.closest('[data-per-select]');
        if (!s || e.defaultPrevented || s.closest('[data-crud]')) return;
        const u = new URL(location.href);
        u.searchParams.set(s.dataset.perParam || 'per', s.value);
        u.searchParams.delete(s.dataset.pageParam || 'page');
        (window.Turbo ? Turbo.visit(u.toString()) : (location.href = u.toString()));
    });

    function syncThemeIcon() {
        const root = document.documentElement, btn = document.getElementById('themeToggle');
        if (!btn) return;
        const dark = root.getAttribute('data-theme') === 'dark'
            || (!root.hasAttribute('data-theme') && matchMedia('(prefers-color-scheme: dark)').matches);
        btn.querySelector('[data-icon-dark]')?.classList.toggle('d-none', dark);
        btn.querySelector('[data-icon-light]')?.classList.toggle('d-none', !dark);
    }

    // keep the permanent sidebar's active row in step with the current page
    function syncActiveNav() {
        const active = (meta('nav-active') || {}).content || '';
        const tab = (meta('nav-tab') || {}).content || '';
        const nav = document.querySelector('.sidebar-nav');
        if (!nav || !active) return;
        nav.querySelectorAll('.nav-item.is-active').forEach((a) => a.classList.remove('is-active'));
        nav.querySelectorAll('.nav-tree').forEach((t) => t.classList.remove('is-parent-active'));
        nav.querySelectorAll('a.nav-item').forEach((a) => {
            const key = a.getAttribute('data-key');
            if (key && (key === active || (tab && key === tab))) {
                a.classList.add('is-active');
                const sub = a.closest('.nav-sub');
                if (sub) {
                    const parent = sub.previousElementSibling;
                    if (parent && parent.classList.contains('nav-tree')) {
                        document.querySelectorAll('.nav-tree.is-open').forEach((o) => o !== parent && o.classList.remove('is-open'));
                        parent.classList.add('is-open', 'is-parent-active');
                    }
                }
            }
        });
    }

    // ---------------------------------------------------- per-navigation ---
    function initPage() {
        syncThemeIcon();
        syncActiveNav();
        TT.enhance();

        // close the mobile drawer after navigating
        document.getElementById('sidebar')?.classList.remove('open');
        document.getElementById('sidebarBackdrop')?.classList.remove('show');

        // required-field asterisks
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach((el) => {
            let lbl = el.id ? document.querySelector('label[for="' + CSS.escape(el.id) + '"]') : null;
            if (!lbl) {
                let p = el.previousElementSibling;
                while (p && !lbl) { if (p.matches && p.matches('label, .form-label')) lbl = p; p = p.previousElementSibling; }
            }
            if (!lbl) { const g = el.closest('.col, [class*="col-"], .mb-2, .mb-3, .form-group'); if (g) lbl = g.querySelector('.form-label, label'); }
            if (lbl && !lbl.querySelector('.req-star') && !/[*]\s*$/.test(lbl.textContent)) {
                lbl.insertAdjacentHTML('beforeend', ' <span class="req-star" aria-hidden="true">*</span>');
            }
        });

        // auto-dismiss flash toasts
        setTimeout(() => document.querySelectorAll('.toast-stack .toast-msg').forEach((t) => {
            t.style.transition = 'opacity .3s'; t.style.opacity = 0; setTimeout(() => t.remove(), 300);
        }), 4200);

        // re-open a modal a server validation error came back on
        const reopen = document.body.dataset.reopen;
        if (reopen && window.bootstrap) {
            const el = document.getElementById(reopen);
            if (el) bootstrap.Modal.getOrCreateInstance(el).show();
        }

        // wire any not-yet-wired no-reload CRUD regions
        document.querySelectorAll('[data-crud]').forEach((scope) => {
            if (scope.dataset.crudWired) return;
            scope.dataset.crudWired = '1';
            // opt the results region + our own forms out of Turbo (so pager links
            // and CRUD/filter submits stay AJAX) — but leave other links (row
            // "View"/"Edit", KPI tiles) free to Turbo-navigate.
            (scope.querySelector('[data-crud-results]') || scope).setAttribute('data-turbo', 'false');
            scope.querySelectorAll('form[data-crud-save], form[data-crud-delete], form[data-crud-filter]')
                .forEach((f) => f.setAttribute('data-turbo', 'false'));
            wireScope(scope);
        });
    }

    // ---------------------------------------------------------- CRUD scope ---
    function wireScope(scope) {
        const url     = scope.dataset.crud || location.pathname;
        const results = scope.dataset.crudResults
            ? document.querySelector(scope.dataset.crudResults)
            : scope.querySelector('[data-crud-results]');
        let seq = 0;

        function swap(html) {
            if (!results) return;
            TT.unenhance(results);
            results.innerHTML = html;
            TT.enhance(results);
            results.dispatchEvent(new CustomEvent('tt:swapped', { bubbles: true }));
        }
        async function refresh(search) {
            if (!results) return;
            const mine = ++seq;
            results.classList.add('is-loading');
            const r = await TT.fetch(url + (search || ''), {});
            if (mine !== seq) return;
            if (r.ok && typeof r.data === 'string') {
                swap(r.data);
                history.replaceState(history.state, '', url + (search || ''));
            }
            results.classList.remove('is-loading');
        }

        const filter = scope.querySelector('form[data-crud-filter]');
        const query = () => {
            if (!filter) return location.search;
            const p = new URLSearchParams(new FormData(filter));
            for (const pair of [...p]) { if (!pair[1]) p.delete(pair[0]); }
            const s = p.toString();
            return s ? '?' + s : '';
        };
        if (filter) {
            filter.addEventListener('submit', (e) => { e.preventDefault(); refresh(query()); });
            filter.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => refresh(query())));
            const q = filter.querySelector('[name="q"], input[type="search"]');
            let t;
            if (q) q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => refresh(query()), 350); });
        }

        scope.addEventListener('click', (e) => {
            const a = e.target.closest('[data-crud-results] a.page-link');
            if (!a) return;
            const href = a.getAttribute('href');
            if (!href || href === '#') return;
            e.preventDefault(); e.stopPropagation();
            refresh(new URL(a.href, location.origin).search);
        }, true);
        scope.addEventListener('change', (e) => {
            const s = e.target.closest('[data-crud-results] [data-per-select]');
            if (!s) return;
            e.preventDefault(); e.stopPropagation();
            const p = new URLSearchParams(query().replace(/^\?/, ''));
            p.set(s.dataset.perParam || 'per', s.value);
            p.delete(s.dataset.pageParam || 'page');
            refresh('?' + p.toString());
        }, true);

        scope.addEventListener('submit', async (e) => {
            const form = e.target.closest('form[data-crud-save]');
            if (!form) return;
            e.preventDefault();
            if (form.checkValidity && !form.checkValidity()) { form.classList.add('was-validated'); return; }
            TT.clearErrors(form);
            const btn = form.querySelector('button[type="submit"], .modal-footer button.btn-primary');
            if (btn) btn.disabled = true;
            const r = await TT.fetch(form.getAttribute('action'), { method: form.dataset.crudSave || 'POST', form });
            if (btn) btn.disabled = false;
            if (r.ok && r.data && r.data.ok) {
                const modal = form.closest('.modal');
                if (modal && window.bootstrap) bootstrap.Modal.getInstance(modal)?.hide();
                if (r.data.html) swap(r.data.html);
                TT.toast(r.data.message || 'Saved.', 'ok');
                if (!modal) { form.reset(); form.classList.remove('was-validated'); }
            } else if (r.data && r.data.errors) {
                TT.showErrors(form, r.data.errors);
                TT.toast(r.data.message || 'Please fix the highlighted fields.', 'err');
            } else {
                TT.toast((r.data && r.data.message) || 'Something went wrong.', 'err');
            }
        });

        scope.addEventListener('submit', async (e) => {
            const form = e.target.closest('form[data-crud-delete]');
            if (!form) return;
            e.preventDefault();
            const ok = await TT.confirm(form.dataset.crudDelete || 'Delete this item?', { danger: true, okText: 'Delete' });
            if (!ok) return;
            const r = await TT.fetch(form.getAttribute('action'), { method: 'POST', form });
            if (r.ok && r.data && r.data.ok) {
                if (r.data.html) swap(r.data.html);
                else refresh(query());
                TT.toast(r.data.message || 'Deleted.', 'ok');
            } else {
                TT.toast((r.data && r.data.message) || 'Could not delete.', 'err');
            }
        });
    }

    // ----------------------------------------------------------- bootstrap ---
    let ran = false;
    document.addEventListener('turbo:load', () => { ran = true; initPage(); });
    // fallback for when Turbo fails to load from the CDN
    window.addEventListener('load', () => setTimeout(() => { if (!ran) { ran = true; initPage(); } }, 150));

    // tear down Chart.js instances + open Bootstrap overlays before Turbo caches
    // the page, so a cached snapshot never restores a "live" canvas or a stuck modal.
    document.addEventListener('turbo:before-cache', () => {
        TT.unenhance();
        if (window.Chart) {
            document.querySelectorAll('canvas').forEach((c) => {
                const inst = window.Chart.getChart ? window.Chart.getChart(c) : null;
                if (inst) inst.destroy();
            });
        }
        if (window.bootstrap) {
            document.querySelectorAll('.modal.show, .offcanvas.show').forEach((el) => {
                (bootstrap.Modal.getInstance(el) || bootstrap.Offcanvas.getInstance(el))?.hide();
            });
            document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach((b) => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }
    });
})();
