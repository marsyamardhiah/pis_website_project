<?php
require_once __DIR__ . '/../config/db.php';

$page_title = 'Contact & Feedback';

$success = "";
$error = "";

$name = "";
$email = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if ($name == "" || $email == "" || $message == "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        $sql = "INSERT INTO feedback (name, email, message)
                VALUES (?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $email, $message]);

        $success = "Thank you! Your feedback has been submitted successfully.";

        $name = "";
        $email = "";
        $message = "";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>


<main class="pis-container pis-main">

    <!-- HERO SECTION -->
    <section class="pis-hero">
        <div class="pis-hero-copy">
            <span class="pis-hero-eyebrow">
                PASSENGER INFORMATION SYSTEM
            </span>

            <h1>Contact & Feedback</h1>

            <p>
                We value your feedback. Let us know how we can
                improve your travel experience.
            </p>
        </div>

        <div class="pis-hero-art">
            <span style="font-size: 5rem;">✉️</span>
        </div>
    </section>

    <!-- CONTACT CONTENT -->
    <section class="pis-section">

        <div class="pis-board-heading">
            <h2>Send Us a Message</h2>
            <span class="pis-muted">We would love to hear from you</span>
        </div>

        <?php if ($success != ""): ?>
            <div class="pis-success-message">
    <strong>✓ Feedback Submitted Successfully!</strong>
    <?php echo htmlspecialchars($success); ?>
</div>
        <?php endif; ?>

        <?php if ($error != ""): ?>
            <div class="pis-empty-state">
                <strong>Submission Error</strong>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="contact.php">

            <div class="pis-form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter your full name"
                    value="<?php echo htmlspecialchars($name); ?>"
                    required
                >
            </div>

            <div class="pis-form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >
            </div>

            <div class="pis-form-group">
                <label for="message">Your Message</label>
                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    placeholder="Write your feedback or enquiry here..."
                    required
                ><?php echo htmlspecialchars($message); ?></textarea>
            </div>

            <button type="submit" class="btn-signal">
                Submit Feedback →
            </button>

        </form>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
