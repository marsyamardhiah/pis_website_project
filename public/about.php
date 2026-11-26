<?php
require_once __DIR__ . '/../config/db.php';
$page_title = 'About';

// Pull a couple of live figures so this page isn't just static text
$stationCount = (int) $pdo->query("SELECT COUNT(*) FROM stations")->fetchColumn();
$trainCount   = (int) $pdo->query("SELECT COUNT(*) FROM trains")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> BIC21203 Web Development &middot; Group Project</div>
        <h1>About this system</h1>
        <p>What the Passenger Information System is, what it does, and how it was built.</p>
    </div>
</div>

<section class="pis-section">
    <h2 class="pis-section-title">What is the PIS?</h2>
    <p style="max-width: 70ch;">
        The Passenger Information System gives passengers and station staff a single, live view of
        train operations across a rail network. The public side shows departures, arrivals, station
        details, and service alerts; the staff side lets authorised admins keep that information
        current &mdash; adding trains and schedules, publishing delay notices, and managing station
        and account records &mdash; so changes made at the back office reach passengers immediately.
    </p>
    <p style="max-width: 70ch;">
        Right now the system is tracking <strong><?= $stationCount ?></strong> station<?= $stationCount === 1 ? '' : 's' ?>
        and <strong><?= $trainCount ?></strong> train<?= $trainCount === 1 ? '' : 's' ?> in its database.
    </p>
</section>

<section class="pis-section" aria-labelledby="fit-title">
    <h2 class="pis-section-title" id="fit-title">How the system fits together</h2>
    <div class="pis-split">
        <img src="<?= $pis_base ?>/assets/img/modules.svg" alt="Diagram with PIS in the centre, joined to four modules: BRD the departure board, SCH schedule search, ALR alerts and ADM the staff admin panel." width="480" height="300">
        <p style="max-width: 62ch;">
            Every public page reads from the same database, and the staff panel writes to it. When a
            member of staff records a delay, the departure board, the schedule search and the alerts
            all show it on the next page load.
        </p>
    </div>
</section>

<section class="pis-section">
    <h2 class="pis-section-title">Core modules</h2>
    <div class="pis-notice-board">
        <div class="pis-notice-row">
            <div class="pis-notice-tag">BRD</div>
            <div>
                <h3>Live departure board</h3>
                <p>Real-time arrivals and departures, platform numbers, and on-time / delayed / boarding
                    status for every station.</p>
            </div>
        </div>
        <div class="pis-notice-row">
            <div class="pis-notice-tag">SCH</div>
            <div>
                <h3>Schedule search</h3>
                <p>Lets passengers look up a train or route and see its full stop-by-stop timing.</p>
            </div>
        </div>
        <div class="pis-notice-row">
            <div class="pis-notice-tag">ALR</div>
            <div>
                <h3>Alerts &amp; announcements</h3>
                <p>Delay notices and service disruptions, plus a simulated audio broadcast module using
                    the browser's text-to-speech engine.</p>
            </div>
        </div>
        <div class="pis-notice-row">
            <div class="pis-notice-tag">ADM</div>
            <div>
                <h3>Staff admin panel</h3>
                <p>A password-protected dashboard for managing trains, delays, stations, and staff
                    accounts &mdash; full CRUD, with prepared statements throughout.</p>
            </div>
        </div>
    </div>
</section>

<section class="pis-section" aria-labelledby="a11y-title">
    <h2 class="pis-section-title" id="a11y-title">Designed for everyone</h2>
    <div class="pis-split">
        <img src="<?= $pis_base ?>/assets/img/accessibility.svg" alt="A person icon surrounded by an eye, larger letters A, a half-dark contrast circle and a loudspeaker, representing the site's accessibility features." width="480" height="300">
        <ul>
            <li><strong>High contrast:</strong> the departure boards use amber on navy (8.45 to 1), well above the 4.5 to 1 minimum in WCAG 2.2.</li>
            <li><strong>Bigger text:</strong> the A&minus; / A / A+ buttons at the top enlarge text up to 150%, and the choice is remembered.</li>
            <li><strong>High-contrast mode:</strong> one button switches to black, white and yellow.</li>
            <li><strong>Not only colour:</strong> every train status is also written in words.</li>
            <li><strong>Keyboard friendly:</strong> a skip link and a clear focus outline on every control.</li>
            <li><strong>Heard as well as seen:</strong> announcements use text-to-speech, and the board's auto-refresh can be paused.</li>
            <li><strong>Any screen:</strong> the layout adapts from phones to desktops.</li>
        </ul>
    </div>
</section>

<section class="pis-section">
    <h2 class="pis-section-title">Built with</h2>
    <div class="pis-tags">
        <span class="pis-ticket">PHP 8</span>
        <span class="pis-ticket">MySQL / MariaDB</span>
        <span class="pis-ticket">PDO prepared statements</span>
        <span class="pis-ticket">Plain HTML5</span>
        <span class="pis-ticket">Own CSS (no framework)</span>
        <span class="pis-ticket">Vanilla JavaScript</span>
        <span class="pis-ticket">Web Speech API</span>
        <span class="pis-ticket">Apache (XAMPP)</span>
    </div>
</section>

<section class="pis-section">
    <h2 class="pis-section-title">Project crew</h2>
    <p class="pis-muted">7 members, 2 pages each &mdash; 14 pages total.</p>
    <div class="pis-table-wrap">
        <table class="pis-roster">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Pages</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>01</td><td>Home, About</td></tr>
                <tr><td>02</td><td>Live Departures Board, Search Schedule</td></tr>
                <tr><td>03</td><td>Station Information, Help / FAQ</td></tr>
                <tr><td>04</td><td>Alerts (public), Contact / Feedback</td></tr>
                <tr><td>05</td><td>Admin Login, Admin Dashboard</td></tr>
                <tr><td>06</td><td>Manage Trains &amp; Schedules, Manage Delays &amp; Alerts</td></tr>
                <tr><td>07</td><td>Manage Stations/Platforms, Manage Staff Accounts</td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
