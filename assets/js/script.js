// Shared site-wide JS — plain DOM manipulation and event handling only
// (no Bootstrap, no external JS library). Add small page-independent
// enhancements here so every member's pages behave consistently.

document.addEventListener('DOMContentLoaded', function () {
    // Mobile nav toggle: show/hide the nav menu on small screens.
    // This replaces Bootstrap's data-bs-toggle="collapse" behaviour.
    const toggle = document.getElementById('pisNavToggle');
    const menu = document.getElementById('pisNavMenu');

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            const isOpen = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close the menu again once a link inside it is clicked
        // (handy on mobile after navigating to a new page).
        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // Live text filter (Stations, FAQ — reusable on any page).
    //   <input data-pis-filter="#list">   hides every [data-filter-text]
    //                                      item inside #list that doesn't
    //                                      contain the typed text
    //   data-pis-filter-empty="#el"       shown when nothing matches
    //   data-pis-filter-count="#el"       shows "N of M shown" while filtering
    // Wrappers marked [data-filter-group] hide when all their items do.
    document.querySelectorAll('[data-pis-filter]').forEach(function (input) {
        const list = document.querySelector(input.dataset.pisFilter);
        if (!list) return;

        const items  = list.querySelectorAll('[data-filter-text]');
        const groups = list.querySelectorAll('[data-filter-group]');
        const empty  = input.dataset.pisFilterEmpty ? document.querySelector(input.dataset.pisFilterEmpty) : null;
        const count  = input.dataset.pisFilterCount ? document.querySelector(input.dataset.pisFilterCount) : null;
        const countDefault = count ? count.textContent : '';
        if (!items.length) return;

        function applyFilter() {
            const query = input.value.trim().toLowerCase();
            let shown = 0;

            items.forEach(function (item) {
                const match = query === '' || item.dataset.filterText.toLowerCase().indexOf(query) !== -1;
                item.hidden = !match;
                if (match) shown++;
            });

            groups.forEach(function (group) {
                group.hidden = !group.querySelector('[data-filter-text]:not([hidden])');
            });

            if (empty) empty.hidden = shown !== 0;
            if (count) count.textContent = query === '' ? countDefault : shown + ' of ' + items.length + ' shown';
        }

        input.addEventListener('input', applyFilter);
        applyFilter(); // the browser may restore typed text after Back
    });

    // A link to one FAQ answer (faq.php#faq-status) should open it, not
    // just scroll to its closed question.
    function openLinkedDetails() {
        if (!location.hash) return;
        let target = null;
        try {
            target = document.querySelector(location.hash);
        } catch (e) {
            return; // hash isn't a valid selector — nothing to open
        }
        if (target && target.tagName === 'DETAILS') {
            target.open = true;
        }
    }

    openLinkedDetails();
    window.addEventListener('hashchange', openLinkedDetails);
});

// ---------------------------------------------------------------------
// Display settings (accessibility): text size + high contrast.
// The choice is saved in the browser (localStorage), so it is kept on
// every page and on the next visit. The tiny script in header.php applies
// it before the page paints.
// ---------------------------------------------------------------------
(function () {
    const SCALES = [87.5, 100, 112.5, 125, 150];   // percent of the browser's base size
    const root = document.documentElement;

    function readScale() {
        let saved = null;
        try { saved = parseFloat(localStorage.getItem('pisTextScale')); } catch (e) {}
        return SCALES.indexOf(saved) !== -1 ? saved : 100;
    }

    function setScale(value) {
        root.style.fontSize = value + '%';
        try { localStorage.setItem('pisTextScale', String(value)); } catch (e) {}
    }

    document.querySelectorAll('[data-pis-text]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const action = btn.dataset.pisText;
            let i = SCALES.indexOf(readScale());
            if (action === 'up')   i = Math.min(i + 1, SCALES.length - 1);
            if (action === 'down') i = Math.max(i - 1, 0);
            if (action === 'reset') i = SCALES.indexOf(100);
            setScale(SCALES[i]);
        });
    });

    const contrastBtn = document.getElementById('pisContrastBtn');
    if (contrastBtn) {
        const sync = function () {
            contrastBtn.setAttribute('aria-pressed', root.classList.contains('pis-high-contrast') ? 'true' : 'false');
        };
        sync();
        contrastBtn.addEventListener('click', function () {
            const on = root.classList.toggle('pis-high-contrast');
            try { localStorage.setItem('pisContrast', on ? 'high' : 'normal'); } catch (e) {}
            sync();
        });
    }
})();

// ---------------------------------------------------------------------
// Live board: refreshes the table rows in place, without reloading the page.
//   <div class="departure-board" data-pis-live
//        data-endpoint="api_board.php?mode=station&station_id=1"
//        data-columns="train,line,working,platform,arrives,departs,status"
//        data-empty="No trains today.">
// The server answers with JSON (public/api_board.php); fetch() asks for it,
// and the rows are rebuilt with createElement + textContent, so nothing
// from the database is ever treated as HTML.
//
// The visitor can pause it (WCAG 2.2.2): <button data-pis-refresh="30">.
// If JavaScript is off, the server-rendered rows are still there.
// ---------------------------------------------------------------------
(function () {
    const board = document.querySelector('[data-pis-live]');
    const btn = document.querySelector('[data-pis-refresh]');
    if (!btn && !board) return;

    const seconds = parseInt(btn ? btn.dataset.pisRefresh : '30', 10) || 30;
    const message = document.getElementById('pisLiveMsg');
    const FIELD = { train: 'train_no', line: 'line_name', working: 'working', station: 'station_name',
                    platform: 'platform', arrives: 'arrives', departs: 'departs' };
    let paused = false;
    let timer = null;

    function say(text) { if (message) message.textContent = text; }

    function cell(text, className) {
        const td = document.createElement('td');
        td.textContent = text;
        if (className) td.className = className;
        return td;
    }

    function render(data) {
        const tbody = board.querySelector('tbody');
        const columns = (board.dataset.columns || '').split(',');
        tbody.textContent = '';

        if (!data.rows.length) {
            const tr = document.createElement('tr');
            const td = cell(board.dataset.empty || 'Nothing to show right now.');
            td.colSpan = columns.length;
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        data.rows.forEach(function (row) {
            const tr = document.createElement('tr');
            columns.forEach(function (col) {
                if (col === 'status') {
                    const delay = row.delay > 0 ? ' (+' + row.delay + 'm)' : '';
                    tr.appendChild(cell(row.status_label + delay, 'status-' + row.status));
                } else {
                    tr.appendChild(cell(row[FIELD[col]]));
                }
            });
            tbody.appendChild(tr);
        });
    }

    function renderStats(stats) {
        if (!stats) return;
        Object.keys(stats).forEach(function (key) {
            const el = document.querySelector('[data-stat="' + key + '"]');
            if (el) el.textContent = stats[key];
        });
    }

    function stamp(time) {
        document.querySelectorAll('[data-pis-updated]').forEach(function (el) {
            el.textContent = time;
            el.classList.remove('is-fresh');
            void el.offsetWidth;            // restart the highlight animation
            el.classList.add('is-fresh');
        });
    }

    async function refresh() {
        if (!board || document.hidden) return;
        try {
            const response = await fetch(board.dataset.endpoint, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            render(data);
            renderStats(data.stats);
            if (window.pisApplyBoardFilter) window.pisApplyBoardFilter();
            if (window.pisUpdateNext && data.stats) window.pisUpdateNext(data.rows);
            stamp(data.generated_at);
            say('');
        } catch (error) {
            say('Could not update the board just now. Showing the last information.');
        }
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(async function () {
            if (!paused) {
                if (board) { await refresh(); } else { location.reload(); }
            }
            if (!paused) schedule();
        }, seconds * 1000);
    }

    if (btn) {
        btn.addEventListener('click', function () {
            paused = !paused;
            btn.setAttribute('aria-pressed', paused ? 'true' : 'false');
            btn.textContent = paused ? 'Resume auto-refresh' : 'Pause auto-refresh';
            say(paused ? 'Auto-refresh is paused.' : 'Auto-refresh is on.');
            clearTimeout(timer);
            if (!paused) { refresh(); schedule(); }
        });
    }

    // Catch up straight away when the visitor comes back to this tab
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !paused) refresh();
    });

    schedule();
})();

// =====================================================================
// Modern features: toasts, dark mode, favourite stations, board filter,
// next-departure card, scroll reveal. Plain DOM + events + localStorage.
// =====================================================================

// ---------- Toast: a small, polite pop-up message ----------
window.pisToast = function (text) {
    const holder = document.getElementById('pisToasts');
    if (!holder) return;
    const toast = document.createElement('div');
    toast.className = 'pis-toast';
    toast.textContent = text;
    holder.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 3200);
};

// ---------- Dark mode ----------
(function () {
    const root = document.documentElement;
    const btn = document.getElementById('pisThemeBtn');
    const contrastBtn = document.getElementById('pisContrastBtn');

    function saved() { try { return localStorage.getItem('pisTheme'); } catch (e) { return null; } }
    function wantsDark() {
        const s = saved();
        if (s) return s === 'dark';
        return !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
    }
    function apply() {
        // High-contrast mode has its own black/white/yellow palette, so dark steps aside
        const dark = wantsDark() && !root.classList.contains('pis-high-contrast');
        root.classList.toggle('pis-dark', dark);
        if (btn) btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
    }

    apply();

    if (btn) {
        btn.addEventListener('click', function () {
            if (root.classList.contains('pis-high-contrast')) {
                window.pisToast('Turn off High contrast to use dark mode.');
                return;
            }
            const next = root.classList.contains('pis-dark') ? 'light' : 'dark';
            try { localStorage.setItem('pisTheme', next); } catch (e) {}
            apply();
            window.pisToast(next === 'dark' ? 'Dark mode on' : 'Light mode on');
        });
    }
    if (contrastBtn) {
        contrastBtn.addEventListener('click', function () { setTimeout(apply, 0); });
    }
})();

// ---------- Favourite stations ----------
(function () {
    function load() {
        try { return JSON.parse(localStorage.getItem('pisFavs') || '[]'); } catch (e) { return []; }
    }
    function store(list) {
        try { localStorage.setItem('pisFavs', JSON.stringify(list)); } catch (e) {}
    }

    const star = document.getElementById('pisStarBtn');
    if (star) {
        const id = parseInt(star.dataset.stationId, 10);
        const name = star.dataset.stationName;
        const sync = function () {
            const on = load().some(function (f) { return f.id === id; });
            star.setAttribute('aria-pressed', on ? 'true' : 'false');
            star.textContent = on ? '★ Saved' : '☆ Save station';
        };
        sync();
        star.addEventListener('click', function () {
            let list = load();
            if (list.some(function (f) { return f.id === id; })) {
                list = list.filter(function (f) { return f.id !== id; });
                window.pisToast(name + ' removed from your stations');
            } else {
                list.push({ id: id, name: name });
                window.pisToast(name + ' saved to your stations');
            }
            store(list);
            sync();
        });
    }

    // Home page: list the saved stations as quick links
    const section = document.getElementById('pisFavs');
    const holder = document.getElementById('pisFavList');
    if (section && holder) {
        const list = load();
        if (list.length) {
            section.hidden = false;
            list.forEach(function (f) {
                const a = document.createElement('a');
                a.className = 'pis-station-chip';
                a.href = 'departures.php?station_id=' + encodeURIComponent(f.id);
                a.textContent = f.name;
                holder.appendChild(a);
            });
        }
    }
})();

// ---------- Board filter: search box + status chips ----------
(function () {
    const board = document.querySelector('[data-pis-live]');
    const input = document.getElementById('boardSearch');
    const chips = document.querySelectorAll('[data-status-filter]');
    const count = document.getElementById('pisCount');
    if (!board || (!input && !chips.length)) return;

    let status = 'all';

    window.pisApplyBoardFilter = function () {
        const term = input ? input.value.trim().toLowerCase() : '';
        const rows = board.querySelectorAll('tbody tr');
        let shown = 0;
        let total = 0;

        rows.forEach(function (row) {
            const last = row.cells[row.cells.length - 1];
            if (!last || !/status-/.test(last.className)) { return; }   // the "nothing to show" row
            total++;
            const okStatus = status === 'all' || last.className.indexOf('status-' + status) !== -1;
            const okText = term === '' || row.textContent.toLowerCase().indexOf(term) !== -1;
            row.hidden = !(okStatus && okText);
            if (!row.hidden) shown++;
        });

        if (count) count.textContent = total ? 'Showing ' + shown + ' of ' + total + ' trains' : '';
    };

    if (input) input.addEventListener('input', window.pisApplyBoardFilter);
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            status = chip.dataset.statusFilter;
            chips.forEach(function (c) { c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'); });
            window.pisApplyBoardFilter();
        });
    });

    window.pisApplyBoardFilter();
})();

// ---------- Home: "Next departure" card ----------
window.pisUpdateNext = function (rows) {
    const title = document.querySelector('[data-next-title]');
    const detail = document.querySelector('[data-next-detail]');
    if (!title || !detail) return;

    const next = rows.find(function (r) { return r.status !== 'departed' && r.minutes >= -3; });
    if (!next) {
        title.textContent = 'No more departures today';
        detail.textContent = 'Check the board again tomorrow.';
        return;
    }
    let when;
    if (next.minutes <= 0) when = 'boarding now';
    else if (next.minutes < 60) when = 'in ' + next.minutes + ' min';
    else when = 'in ' + Math.floor(next.minutes / 60) + ' h ' + (next.minutes % 60) + ' min';

    title.textContent = next.train_no + ' from ' + next.station_name;
    detail.textContent = 'Platform ' + next.platform + ' · ' + next.departs + ' · ' + when;
};

// ---------- Scroll reveal ----------
(function () {
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const targets = document.querySelectorAll('.pis-section, .pis-board-heading, .departure-board, .pis-feature, .pis-station-card, .pis-toolbar, .pis-faq-list, .pis-alert-list');
    const io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
        });
    }, { threshold: 0.08 });

    targets.forEach(function (el) { el.classList.add('pis-reveal'); io.observe(el); });
})();
