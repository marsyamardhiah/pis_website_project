<?php
// Site root URL, worked out from where this page is being served
// (e.g. /pis-website or /WebDev/pis-website), so every link and asset
// works no matter which folder under htdocs/ the project sits in.
// All pages live exactly one folder down (public/ or admin/).
$pis_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>PIS | Passenger Information System</title>

    <!-- Type system: Plus Jakarta Sans (headings), Inter (body, very readable), JetBrains Mono (departure-board figures) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap">

    <!-- Our own external style sheet only — no CSS framework. -->
    <link rel="stylesheet" href="<?= $pis_base ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <meta name="theme-color" content="#10273F">
    <link rel="icon" type="image/svg+xml" href="<?= $pis_base ?>/assets/img/favicon.svg">
    <link rel="manifest" href="<?= $pis_base ?>/assets/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <?= $extra_head ?? '' ?>

    <!-- Apply the visitor's saved text size / contrast BEFORE the page paints,
         so there is no flash of small text for someone who chose larger text. -->
    <script>
    document.documentElement.classList.add('pis-js');
    (function () {
        try {
            var scale = localStorage.getItem('pisTextScale');
            if (scale) document.documentElement.style.fontSize = scale + '%';
            var high = localStorage.getItem('pisContrast') === 'high';
            if (high) document.documentElement.classList.add('pis-high-contrast');
            var theme = localStorage.getItem('pisTheme');
            var dark = theme ? theme === 'dark' : (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
            if (dark && !high) document.documentElement.classList.add('pis-dark');
        } catch (e) { /* storage blocked: defaults are fine */ }
    })();
    </script>
</head>
<body>

<a class="pis-skip" href="#main">Skip to main content</a>

<?php $pis_current_page = basename($_SERVER['SCRIPT_NAME']); ?>

<!-- Display settings: larger text and high contrast for readers who need them -->
<div class="pis-a11y" role="group" aria-label="Display settings">
    <div class="pis-container pis-a11y-inner">
        <span class="pis-a11y-label">Display:</span>
        <button type="button" class="pis-a11y-btn" data-pis-text="down" aria-label="Make text smaller">A&minus;</button>
        <button type="button" class="pis-a11y-btn" data-pis-text="reset" aria-label="Reset text size">A</button>
        <button type="button" class="pis-a11y-btn" data-pis-text="up" aria-label="Make text larger">A+</button>
        <button type="button" class="pis-a11y-btn" id="pisContrastBtn" aria-pressed="false">High contrast</button>
        <button type="button" class="pis-a11y-btn" id="pisThemeBtn" aria-pressed="false">Dark mode</button>
    </div>
</div>

<nav class="pis-navbar" aria-label="Main navigation">
    <div class="pis-container pis-navbar-inner">
        <a class="pis-brand" href="<?= $pis_base ?>/public/index.php">
            <svg class="pis-brand-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="3" width="14" height="13" rx="4" />
                <line x1="5" y1="10" x2="19" y2="10" />
                <circle cx="9" cy="19" r="1.6" fill="currentColor" stroke="none" />
                <circle cx="15" cy="19" r="1.6" fill="currentColor" stroke="none" />
                <line x1="7" y1="16" x2="5.5" y2="19" />
                <line x1="17" y1="16" x2="18.5" y2="19" />
            </svg>
            <span class="pis-brand-mark">PIS</span><span class="pis-brand-sub">Railway</span>
        </a>

        <button type="button" class="pis-nav-toggle" id="pisNavToggle" aria-expanded="false" aria-controls="pisNavMenu" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>

        <ul class="pis-nav-menu" id="pisNavMenu">
            <li><a class="pis-nav-link<?= $pis_current_page === 'index.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'index.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/index.php">Home</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'departures.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'departures.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/departures.php">Departures</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'search.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'search.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/search.php">Search Schedule</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'stations.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'stations.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/stations.php">Stations</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'alerts.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'alerts.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/alerts.php">Alerts</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'faq.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'faq.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/faq.php">FAQ</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'about.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'about.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/about.php">About</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'contact.php' ? ' is-active' : '' ?>"<?= $pis_current_page === 'contact.php' ? ' aria-current="page"' : '' ?> href="<?= $pis_base ?>/public/contact.php">Contact</a></li>
            <li><a class="pis-nav-link pis-staff-link" href="<?= $pis_base ?>/admin/login.php">Staff Login</a></li>
        </ul>
    </div>
</nav>


<!-- Quick navigation for phones: an app-style tab bar -->
<nav class="pis-tabbar" aria-label="Quick navigation">
    <a href="<?= $pis_base ?>/public/index.php"<?= $pis_current_page === 'index.php' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg><span>Home</span></a>
    <a href="<?= $pis_base ?>/public/departures.php"<?= $pis_current_page === 'departures.php' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>Board</span></a>
    <a href="<?= $pis_base ?>/public/search.php"<?= $pis_current_page === 'search.php' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><span>Search</span></a>
    <a href="<?= $pis_base ?>/public/alerts.php"<?= $pis_current_page === 'alerts.php' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/></svg><span>Alerts</span></a>
    <a href="<?= $pis_base ?>/public/stations.php"<?= $pis_current_page === 'stations.php' ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 0 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Stations</span></a>
</nav>

<main id="main" tabindex="-1" class="pis-container pis-main">

