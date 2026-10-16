<?php
/**
 * connection-test.php
 * Open http://localhost/college-lost-found/connection-test.php after importing the SQL.
 * It should show the table row counts. DELETE this file before submitting/deploying.
 */
require_once __DIR__ . '/config/database.php';

$pdo = db();
echo '<h2>Findora AI: database connection OK</h2><ul>';
foreach (['users', 'items', 'claims', 'match_suggestions'] as $table) {
    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo '<li>' . htmlspecialchars($table) . ': ' . (int)$count . ' rows</li>';
}
echo '</ul>';
