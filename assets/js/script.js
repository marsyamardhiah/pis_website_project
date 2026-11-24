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
