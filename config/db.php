<?php
/**
 * Shared database connection (PDO).
 * Every page includes this file to get a $pdo connection.
 *
 * All group members should use $pdo with prepared statements
 * (never build SQL by concatenating strings) to avoid SQL injection.
 */

// The system's default timezone (often UTC) otherwise makes every date()/
// time() call on the site disagree with the clock on a visitor's wall —
// the whole point of a "live" board is that its clock is right.
date_default_timezone_set('Asia/Kuala_Lumpur');

$DB_HOST = '127.0.0.1:3306';
$DB_NAME = 'pis_db';
$DB_USER = 'root';      // change to your XAMPP/WAMP MySQL username
$DB_PASS = '';          // change to your XAMPP/WAMP MySQL password

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // In production you would log this instead of echoing it.
    die('Database connection failed: ' . $e->getMessage());
}
