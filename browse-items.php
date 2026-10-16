<?php
/**
 * browse-items.php - Browse & Search Campus Lost and Found Items
 *
 * Demonstrates:
 * - Real-time jQuery AJAX search and multi-facet filtering without page reload
 * - Interactive Drag-and-Drop workbench with sessionStorage & localStorage
 * - Dynamic card highlight and responsive grid
 */

require_once __DIR__ . '/includes/auth.php';

// Initial server-side query based on GET parameters
$q        = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$type     = trim((string)($_GET['type'] ?? ''));
$location = trim((string)($_GET['location'] ?? ''));
$date     = trim((string)($_GET['date'] ?? ''));
$status   = trim((string)($_GET['status'] ?? ''));

$items = [];
try {
    $pdo = db();
    $where = [];
    $params = [];

    if ($type !== '' && in_array($type, ['lost', 'found'], true)) {
        $where[] = "item_type = ?";
        $params[] = $type;
    }
    if ($category !== '' && in_array($category, CATEGORIES, true)) {
        $where[] = "category = ?";
        $params[] = $category;
    }
    if ($location !== '') {
        $where[] = "location LIKE ?";
        $params[] = '%' . $location . '%';
    }
    if ($status !== '' && in_array($status, ['lost', 'found', 'claim_pending', 'returned'], true)) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    if ($date !== '' && strtotime($date)) {
        $where[] = "item_date = ?";
        $params[] = $date;
    }
    if ($q !== '') {
        $where[] = "(name LIKE ? OR description LIKE ? OR color LIKE ? OR location LIKE ?)";
        $searchTerm = '%' . $q . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("SELECT i.id, i.item_type, i.name, i.category, i.location, i.item_date, i.status, i.image_path, u.name AS reporter_name FROM items i LEFT JOIN users u ON u.id = i.reporter_id {$whereSql} ORDER BY i.created_at DESC, i.id DESC");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Browse query failed: ' . $e->getMessage());
}

$pageTitle = 'Browse Campus Items';
$activePage = 'browse';
$extraScripts = [
    'assets/js/drag-drop.js',
    'assets/js/ajax.js'
];
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div class="browse-hero-header">
        <div>
            <span class="section__eyebrow">Campus Catalog</span>
            <h1 class="page-title">Browse Campus Belongings</h1>
            <p class="page-subtitle">
                Search reports in real-time, filter by campus building, or drag items into your personal saved tray.
            </p>
        </div>
        <div class="browse-header-actions">
            <a href="<?= e(url('lost-item.php')) ?>" class="btn btn-primary btn-sm">+ Report Lost</a>
            <a href="<?= e(url('found-item.php')) ?>" class="btn btn-accent btn-sm">+ Report Found</a>
        </div>
    </div>

    <!-- Filter Control Center -->
    <div class="filter-card">
        <form id="filter-form" action="<?= e(url('browse-items.php')) ?>" method="get">
            <div class="filter-grid-primary">
                <!-- Search Box -->
                <div class="field field-search" style="margin:0;">
                    <label for="search-input">Search Keywords</label>
                    <div class="input-with-icon">
                        <svg viewBox="0 0 24 24" class="field-icon"><use href="#i-search"/></svg>
                        <input id="search-input" name="q" type="search" placeholder="Search by name, color, model, or keywords..." value="<?= e($q) ?>">
                    </div>
                </div>

                <!-- Category -->
                <div class="field" style="margin:0;">
                    <label for="category-select">Category</label>
                    <select id="category-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach (CATEGORIES as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type -->
                <div class="field" style="margin:0;">
                    <label for="type-select">Report Type</label>
                    <select id="type-select" name="type">
                        <option value="">All Types (Lost &amp; Found)</option>
                        <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost Only</option>
                        <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found Only</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="field" style="margin:0;">
                    <label for="status-select">Status</label>
                    <select id="status-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="lost" <?= $status === 'lost' ? 'selected' : '' ?>>Lost</option>
                        <option value="found" <?= $status === 'found' ? 'selected' : '' ?>>Found</option>
                        <option value="claim_pending" <?= $status === 'claim_pending' ? 'selected' : '' ?>>Claim Pending</option>
                        <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned to Owner</option>
                    </select>
                </div>
            </div>

            <div class="filter-grid-secondary">
                <div class="field" style="margin:0;">
                    <label for="location-filter">Campus Location</label>
                    <div class="input-with-icon">
                        <svg viewBox="0 0 24 24" class="field-icon"><use href="#i-pin"/></svg>
                        <input id="location-filter" name="location" list="location-suggestions" type="text" placeholder="e.g. IT Block Library, Main Canteen..." value="<?= e($location) ?>">
                    </div>
                    <datalist id="location-suggestions">
                        <?php foreach (CAMPUS_LOCATIONS as $loc): ?>
                            <option value="<?= e($loc) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="field" style="margin:0;">
                    <label for="date-filter">Date Reported</label>
                    <div class="input-with-icon">
                        <svg viewBox="0 0 24 24" class="field-icon"><use href="#i-calendar"/></svg>
                        <input id="date-filter" name="date" type="date" value="<?= e($date) ?>">
                    </div>
                </div>

                <div class="filter-reset-wrap">
                    <button type="button" id="reset-filters-btn" class="btn btn-ghost">Reset Filters</button>
                </div>
            </div>
        </form>

        <!-- Category Quick Pills -->
        <div class="filter-chips-row">
            <span class="chips__label">Filter by Category:</span>
            <?php
            $catIcons = [
                'Electronics' => '💻',
                'ID Card'     => '🪪',
                'Books'       => '📚',
                'Wallet'      => '👛',
                'Keys'        => '🔑',
                'Accessories' => '⌚',
                'Other'       => '📦'
            ];
            foreach (CATEGORIES as $cat): ?>
                <button type="button" class="chip category-chip-btn <?= $category === $cat ? 'is-selected' : '' ?>" data-category="<?= e($cat) ?>">
                    <span><?= $catIcons[$cat] ?? '🏷️' ?></span>
                    <span><?= e($cat) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Drag & Drop Interactive Workbench -->
    <div class="workbench-grid" id="drag-drop-workbench">
        <!-- Drop Zone 1: Save for Later -->
        <div id="drop-save-zone" class="drop-zone" data-action="save">
            <div class="drop-zone__header">
                <div class="drop-zone__icon-bubble">★</div>
                <div>
                    <strong class="drop-zone__title">Saved for Later Tray</strong>
                    <p class="drop-zone__subtitle">Drag any item card here to bookmark during your visit.</p>
                </div>
            </div>
            <div id="saved-items-container" class="drop-zone__content">
                <p class="drop-zone__hint">Drag &amp; drop an item card here to save</p>
            </div>
            <div class="drop-zone__footer">
                <span id="saved-count-label">0 items saved</span>
                <button type="button" id="clear-saved-btn" class="btn btn-ghost btn-sm" style="padding:0.25rem 0.6rem; font-size:0.75rem;">Clear Tray</button>
            </div>
        </div>

        <!-- Drop Zone 2: Instant AI Match Inspector -->
        <div id="drop-compare-zone" class="drop-zone" data-action="compare">
            <div class="drop-zone__header">
                <div class="drop-zone__icon-bubble drop-zone__icon-bubble--purple">⚡</div>
                <div>
                    <strong class="drop-zone__title">Instant AI Match Inspector</strong>
                    <p class="drop-zone__subtitle">Drag any card here to calculate matching counterparts.</p>
                </div>
            </div>
            <div id="compare-results-container" class="drop-zone__content">
                <p class="drop-zone__hint">Drag an item card here to test the AI Matcher</p>
            </div>
        </div>
    </div>

    <!-- Live Counter and Spinner -->
    <div class="results-header-bar">
        <div class="results-count-text">
            Showing <span id="results-count" class="count-number"><?= count($items) ?></span> campus report(s)
            <span id="filter-indicator" class="filter-indicator-text" style="display:none;"></span>
        </div>
        <div id="ajax-spinner" class="loader" style="display:none; width:22px; height:22px; margin:0; border-width:3px;"></div>
    </div>

    <!-- Item Cards Grid -->
    <div id="items-grid" class="grid-cards">
        <?php if (!empty($items)): ?>
            <?php foreach ($items as $item) { echo item_card($item, true); } ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <div class="empty-icon-wrap">🔍</div>
                <h3>No items matched your search criteria</h3>
                <p>Try modifying your search keywords, clearing campus location, or resetting filters.</p>
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('reset-filters-btn').click();">Reset All Filters</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
