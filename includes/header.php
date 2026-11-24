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

    <!-- Type system: Oswald (signage headings), Work Sans (body), Space Mono (departure-board data) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Work+Sans:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap">

    <!-- Our own external style sheet only — no CSS framework. -->
    <link rel="stylesheet" href="<?= $pis_base ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <?= $extra_head ?? '' ?>
</head>
<body>

<?php $pis_current_page = basename($_SERVER['SCRIPT_NAME']); ?>

<nav class="pis-navbar">
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
            <li><a class="pis-nav-link<?= $pis_current_page === 'index.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/index.php">Home</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'departures.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/departures.php">Departures</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'search.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/search.php">Search Schedule</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'stations.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/stations.php">Stations</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'alerts.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/alerts.php">Alerts</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'faq.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/faq.php">FAQ</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'about.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/about.php">About</a></li>
            <li><a class="pis-nav-link<?= $pis_current_page === 'contact.php' ? ' is-active' : '' ?>" href="<?= $pis_base ?>/public/contact.php">Contact</a></li>
            <li><a class="pis-nav-link pis-staff-link" href="<?= $pis_base ?>/admin/login.php">Staff Login</a></li>
        </ul>
    </div>
</nav>

<main class="pis-container pis-main">

