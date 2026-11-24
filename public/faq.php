<?php
require_once __DIR__ . '/../config/db.php';
$page_title = 'Help & FAQ';

/*
 * Help / FAQ (Member 3)
 *
 * Questions live in the $faqGroups array below, so adding one is a matter
 * of adding an entry — the page, the topic list, the counts and the search
 * all pick it up automatically.
 *
 * Answers are trusted HTML written by us (links, lists), so they're output
 * as-is. Anything pulled from the database is escaped before it goes in.
 *
 * Keep the answers true to how the site actually behaves: the Boarding /
 * Departed timings below mirror pis_effective_status() in
 * includes/functions.php, and the 30-second refresh mirrors departures.php.
 */

// Live station codes for the "station codes" answer
$stations = $pdo->query('SELECT station_name, station_code FROM stations ORDER BY station_name')->fetchAll();

$codeList = '';
if ($stations) {
    $codeList = '<ul class="pis-code-list">';
    foreach ($stations as $s) {
        $codeList .= '<li><span class="pis-code-badge">' . htmlspecialchars($s['station_code']) . '</span>'
                   . htmlspecialchars($s['station_name']) . '</li>';
    }
    $codeList .= '</ul>';
}

$faqGroups = [
    'board' => [
        'code'  => 'BRD',
        'title' => 'Departure board',
        'items' => [
            'faq-refresh' => [
                'q' => 'How often does the departure board update?',
                'a' => '<p>The <a href="departures.php">Departures</a> page reloads itself every 30 seconds, so you never need to refresh it. Each time it loads, every train\'s status is checked again against the current time.</p>',
            ],
            'faq-status' => [
                'q' => 'What does each train status mean?',
                'a' => '<p>The status column on every board shows one of five states:</p>
                    <ul class="pis-legend">
                        <li><span class="status-on_time">On time</span><span>Running to schedule.</span></li>
                        <li><span class="status-boarding">Boarding</span><span>From 10 minutes before the scheduled time until 3 minutes after. Head to the platform now.</span></li>
                        <li><span class="status-delayed">Delayed (+15m)</span><span>Station staff have reported a delay. The number in brackets is how many minutes late the train is expected to be.</span></li>
                        <li><span class="status-cancelled">Cancelled</span><span>The train will not run. Check <a href="alerts.php">Alerts</a> for any notice about it.</span></li>
                        <li><span class="status-departed">Departed</span><span>More than 3 minutes past its scheduled time.</span></li>
                    </ul>
                    <p><strong>On time</strong>, <strong>Boarding</strong> and <strong>Departed</strong> change automatically as the clock moves on. <strong>Delayed</strong> and <strong>Cancelled</strong> are set by staff and stay until they change them.</p>',
            ],
            'faq-departed' => [
                'q' => 'The board says my train has departed, but it\'s still at the platform. Why?',
                'a' => '<p>A train is shown as <strong>Departed</strong> once it is more than 3 minutes past its scheduled time, unless staff have marked it as delayed. If your train is still at the platform, follow the instructions of station staff and on-platform announcements &mdash; they always take priority over the website.</p>',
            ],
            'faq-working' => [
                'q' => 'What does the "Working" column show?',
                'a' => '<p>It shows where the train is going, relative to the station you\'re looking at:</p>
                    <ul>
                        <li><strong>To Butterworth</strong> &mdash; the train starts here and runs to Butterworth.</li>
                        <li><strong>From Ipoh</strong> &mdash; the train ends its journey here.</li>
                        <li><strong>Padang Besar &rarr; Seremban</strong> &mdash; the train is passing through; this is its full route.</li>
                    </ul>',
            ],
            'faq-timezone' => [
                'q' => 'What time zone are the times in?',
                'a' => '<p>All scheduled times are Malaysia time (MYT, GMT+8). The live clock at the top of each page shows <em>your device\'s</em> time, so if your device is set to a different time zone the clock and the schedule won\'t line up.</p>',
            ],
        ],
    ],

    'search' => [
        'code'  => 'SCH',
        'title' => 'Searching for a train',
        'items' => [
            'faq-route' => [
                'q' => 'How do I find a train between two stations?',
                'a' => '<p>Open <a href="search.php?mode=route">Search Schedule</a>, stay on the <strong>By route</strong> tab, and choose where you\'re travelling from and to. You\'ll see every direct train that calls at both stations, with its departure and arrival platforms.</p>',
            ],
            'faq-no-results' => [
                'q' => 'Why does my route search say "No trains found"?',
                'a' => '<p>Route search only shows <strong>direct</strong> trains: one train that stops at your starting station and then, later in the same journey, at your destination. If no single train does that, there are no results &mdash; the site doesn\'t plan journeys that involve changing trains.</p>
                    <p>Try searching <a href="search.php?mode=station">by station</a> for each end of your journey to see which trains call there.</p>',
            ],
            'faq-time' => [
                'q' => 'Can I search by time of day?',
                'a' => '<p>Yes. The <a href="search.php?mode=time">By time</a> tab lists every train calling in one of four windows: early morning (12am&ndash;6am), morning (6am&ndash;12pm), afternoon (12pm&ndash;6pm) or evening (6pm&ndash;midnight). You can narrow it to one station or leave it on <em>Any station</em>.</p>',
            ],
            'faq-tickets' => [
                'q' => 'Can I buy tickets on this website?',
                'a' => '<p>No. The Passenger Information System shows train times, platforms and service alerts only. Tickets are sold separately by the rail operator.</p>',
            ],
        ],
    ],

    'stations' => [
        'code'  => 'STN',
        'title' => 'Stations &amp; platforms',
        'items' => [
            'faq-platforms' => [
                'q' => 'Where can I see which platform my train uses?',
                'a' => '<p>The platform is shown on every board and search result. To see a whole station laid out platform by platform, open <a href="stations.php">Stations</a> and choose <strong>Station info</strong>.</p>',
            ],
            'faq-platform-change' => [
                'q' => 'Can a train\'s platform change?',
                'a' => '<p>Yes &mdash; platforms can change at short notice for operational reasons. The board always shows the latest platform staff have set, so check it again shortly before your train is due and listen for announcements at the station.</p>',
            ],
            'faq-codes' => [
                'q' => 'What are the short station codes?',
                'a' => '<p>Each station has a short code used where space is tight, such as on departure boards. The current codes are:</p>' . $codeList,
            ],
            'faq-empty-board' => [
                'q' => 'Why is a station\'s board empty?',
                'a' => '<p>A station\'s board only lists trains booked to stop there today. If it\'s empty, no service is scheduled to call at that station. Check <a href="alerts.php">Alerts</a> in case of a disruption.</p>',
            ],
        ],
    ],

    'alerts' => [
        'code'  => 'ALR',
        'title' => 'Alerts &amp; announcements',
        'items' => [
            'faq-alerts' => [
                'q' => 'Where can I find delay and disruption notices?',
                'a' => '<p>The <a href="alerts.php">Alerts</a> page lists current service notices for the whole network. The <a href="index.php">home page</a> shows how many alerts are active right now, and each station\'s info page on <a href="stations.php">Stations</a> lists the notices for that station.</p>',
            ],
            'faq-alert-expiry' => [
                'q' => 'Do alerts disappear on their own?',
                'a' => '<p>An alert about a specific train stops counting as active a few minutes after that train\'s scheduled time has passed. General notices, such as planned platform cleaning, stay up until staff remove them.</p>',
            ],
            'faq-audio' => [
                'q' => 'How do the audio announcements work?',
                'a' => '<p>Announcements are spoken by your browser\'s built-in text-to-speech, so there\'s nothing to download. Press <strong>Play welcome announcement</strong> on the <a href="index.php">home page</a> to hear one.</p>
                    <p>If you hear nothing, check your device\'s volume. A few older browsers don\'t support text-to-speech &mdash; you\'ll see a message saying so if yours doesn\'t.</p>',
            ],
        ],
    ],

    'site' => [
        'code'  => 'WEB',
        'title' => 'Using this website',
        'items' => [
            'faq-mobile' => [
                'q' => 'Does the site work on a phone?',
                'a' => '<p>Yes. Pages adapt to small screens, and on a phone the menu folds into the button at the top right. Wide tables, such as departure boards, scroll sideways.</p>',
            ],
            'faq-access' => [
                'q' => 'Is the site accessible?',
                'a' => '<p>Every link and button can be reached with the keyboard and shows a clear amber outline when focused. If your device is set to reduce motion, page animations are switched off. Announcements can also be played aloud with text-to-speech.</p>',
            ],
            'faq-account' => [
                'q' => 'Do I need an account?',
                'a' => '<p>No &mdash; everything for passengers is open to all. The <strong>Staff Login</strong> link is only for station staff, whose accounts are created by an administrator.</p>',
            ],
            'faq-feedback' => [
                'q' => 'How do I report a problem or send feedback?',
                'a' => '<p>Use the <a href="contact.php">Contact</a> page to send a message to the team. If it\'s about a specific service, include the station and the train number.</p>',
            ],
        ],
    ],
];

// Plain-text version of a question + answer, used by the live search box.
// Tags become spaces so words in neighbouring elements don't run together.
function faq_search_text(string $question, string $answerHtml): string
{
    $text = $question . ' ' . preg_replace('/<[^>]+>/', ' ', $answerHtml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text));
}

$totalQuestions = array_sum(array_map(fn ($g) => count($g['items']), $faqGroups));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pis-hero">
    <div class="pis-hero-copy">
        <div class="pis-hero-eyebrow"><span class="pis-dot"></span> Help desk &mdash; <span class="pis-live-clock" data-pis-clock></span></div>
        <h1>Help &amp; FAQ</h1>
        <p>Answers to common questions about the departure boards, schedule search, stations and service alerts.</p>
    </div>
</div>

<div class="pis-filter-bar">
    <label class="pis-filter-label" for="faqFilter">Search the FAQ</label>
    <input type="search" id="faqFilter" class="pis-input pis-filter-input"
           placeholder="e.g. platform, delayed, tickets" autocomplete="off"
           data-pis-filter="#faqList"
           data-pis-filter-empty="#faqEmpty"
           data-pis-filter-count="#faqCount">
    <span class="pis-filter-count" id="faqCount" aria-live="polite"><?= $totalQuestions ?> questions</span>
</div>

<div class="pis-faq-layout">
    <nav class="pis-faq-toc" aria-label="FAQ topics">
        <div class="pis-faq-toc-head">TOPICS</div>
        <ol>
            <?php foreach ($faqGroups as $key => $group): ?>
                <li>
                    <a href="#topic-<?= htmlspecialchars($key) ?>">
                        <?= $group['title'] ?>
                        <span><?= count($group['items']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div id="faqList">
        <?php foreach ($faqGroups as $key => $group): ?>
            <section class="pis-faq-group" id="topic-<?= htmlspecialchars($key) ?>" data-filter-group aria-labelledby="topic-<?= htmlspecialchars($key) ?>-title">
                <h2 id="topic-<?= htmlspecialchars($key) ?>-title">
                    <span class="pis-code-badge"><?= htmlspecialchars($group['code']) ?></span>
                    <?= $group['title'] ?>
                </h2>
                <div class="pis-faq-list">
                    <?php foreach ($group['items'] as $id => $item): ?>
                        <details class="pis-faq" id="<?= htmlspecialchars($id) ?>"
                                 data-filter-text="<?= htmlspecialchars(faq_search_text($item['q'], $item['a'])) ?>">
                            <summary><?= htmlspecialchars($item['q']) ?></summary>
                            <div class="pis-faq-answer">
                                <?= $item['a'] ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="pis-empty-state" id="faqEmpty" hidden>
            <strong>No matching questions</strong>
            Try a different word, or <a href="contact.php">send us your question</a>.
        </div>
    </div>
</div>

<div class="pis-cta">
    <div>
        <h2>Still need help?</h2>
        <p>Can't find your answer here? Send a message to the team through the contact form.</p>
    </div>
    <a class="btn-signal" href="contact.php">Contact us</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
