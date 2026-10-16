<?php
/**
 * logout.php - Ends the session. POST only, with a CSRF token.
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf'] ?? null)) {
    logout_user();
    session_start();                 // fresh session just to show the message
    flash('success', 'You have been logged out.');
}
redirect('index.php');
