<?php
/**
 * Session/auth helper.
 * Every page under /admin should include this AFTER db.php,
 * then call require_login() at the very top (before any HTML output).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['admin_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_admin_name(): string {
    return $_SESSION['admin_name'] ?? 'Staff';
}
