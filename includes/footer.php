
</main>

<footer class="pis-footer">
    <div class="pis-container">
        <span class="pis-footer-mark">PIS</span>
        <span class="pis-footer-text">&copy; <?= date('Y') ?> Passenger Information System &mdash; BIC21203 Web Development Group Project</span>
    </div>
</footer>

<!-- Browser text-to-speech helper for announcements (Automated Audio Broadcast Module) -->
<script>
function announce(text) {
    if (!('speechSynthesis' in window)) {
        alert('Text-to-speech is not supported in this browser.');
        return;
    }
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    window.speechSynthesis.speak(utterance);
}

// Live clock — reads the visitor's own device clock (whatever timezone
// that is), so this never disagrees with the time on their wall the way a
// server-rendered timestamp can.
(function () {
    const clocks = document.querySelectorAll('[data-pis-clock]');
    if (!clocks.length) return;

    function tick() {
        const now = new Date().toLocaleTimeString('en-GB', { hour12: false });
        clocks.forEach(function (el) { el.textContent = now; });
    }

    tick();
    setInterval(tick, 1000);
})();
</script>

<script src="<?= $pis_base ?>/assets/js/script.js"></script>
</body>
</html>
