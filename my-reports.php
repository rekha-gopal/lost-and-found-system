<?php
/**
 * my-reports.php - User Personal Reports History & Automatic AI Match Alerts
 *
 * Displays:
 * 1. History of all items (lost & found) reported by the authenticated user.
 * 2. Automatic AI Match Alerts for any of their reported lost belongings
 *    whenever matching found items are logged in the system.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';

require_login();
$user = current_user();
$pdo  = db();

// Handle status updates or item deletion by the reporter
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        flash('error', 'Session token expired. Please try again.');
        redirect('my-reports.php');
    }

    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);

    if ($action === 'mark_returned' && $itemId > 0) {
        $stmt = $pdo->prepare("UPDATE items SET status = 'returned' WHERE id = ? AND reporter_id = ?");
        $stmt->execute([$itemId, $user['id']]);
        flash('success', 'Item marked as returned / resolved.');
        redirect('my-reports.php');
    }

    if ($action === 'delete_report' && $itemId > 0) {
        $imgStmt = $pdo->prepare("SELECT image_path FROM items WHERE id = ? AND reporter_id = ?");
        $imgStmt->execute([$itemId, $user['id']]);
        $img = $imgStmt->fetchColumn();

        if ($img && file_exists(UPLOAD_DIR . $img)) {
            @unlink(UPLOAD_DIR . $img);
        }

        $delStmt = $pdo->prepare("DELETE FROM items WHERE id = ? AND reporter_id = ?");
        $delStmt->execute([$itemId, $user['id']]);
        flash('success', 'Report removed from your history.');
        redirect('my-reports.php');
    }
}

// 1. Fetch AI matches for any unresolved lost items reported by this user
$aiMatches = get_user_lost_item_matches((int)$user['id'], $pdo);

// 2. Fetch history of all items reported by this user
$filterType = trim((string)($_GET['type'] ?? ''));
$where = ["reporter_id = ?"];
$params = [$user['id']];

if ($filterType === 'lost') {
    $where[] = "item_type = 'lost'";
} elseif ($filterType === 'found') {
    $where[] = "item_type = 'found'";
} elseif ($filterType === 'returned') {
    $where[] = "status = 'returned'";
}

$whereSql = "WHERE " . implode(' AND ', $where);
$historyStmt = $pdo->prepare("SELECT * FROM items {$whereSql} ORDER BY created_at DESC");
$historyStmt->execute($params);
$userItems = $historyStmt->fetchAll();

// Quick statistics for current user
$countStmt = $pdo->prepare(
    "SELECT 
        SUM(item_type = 'lost') AS lost_count,
        SUM(item_type = 'found') AS found_count,
        SUM(status = 'returned') AS returned_count,
        COUNT(*) AS total_count
     FROM items WHERE reporter_id = ?"
);
$countStmt->execute([$user['id']]);
$userStats = $countStmt->fetch();

$pageTitle = 'My Reports & Match History';
$activePage = 'my-reports';
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div class="section__row">
        <div>
            <span class="section__eyebrow">Personal Dashboard</span>
            <h1 style="font-size:2.3rem; margin-top:0.3rem; margin-bottom:0.3rem;">My Reports &amp; History</h1>
            <p style="color:var(--ink-soft); margin:0;">
                Track every lost or found item you have reported, and view automatic AI match alerts for your belongings.
            </p>
        </div>
        <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
            <a href="<?= e(url('lost-item.php')) ?>" class="btn btn-primary btn-sm">+ Report Lost Item</a>
            <a href="<?= e(url('found-item.php')) ?>" class="btn btn-accent btn-sm">+ Report Found Item</a>
            <a href="<?= e(url('my-claims.php')) ?>" class="btn btn-ghost btn-sm">Track My Claims &rarr;</a>
        </div>
    </div>

    <!-- Quick User Stats -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-top:1.5rem; margin-bottom:2.5rem;">
        <div class="stat">
            <div class="stat__icon-bubble">📦</div>
            <div>
                <div class="stat__value"><?= (int)($userStats['lost_count'] ?? 0) ?></div>
                <p class="stat__label">Lost Reports Filed</p>
            </div>
        </div>
        <div class="stat">
            <div class="stat__icon-bubble">🔍</div>
            <div>
                <div class="stat__value"><?= (int)($userStats['found_count'] ?? 0) ?></div>
                <p class="stat__label">Found Items Logged</p>
            </div>
        </div>
        <div class="stat">
            <div class="stat__icon-bubble">🎉</div>
            <div>
                <div class="stat__value"><?= (int)($userStats['returned_count'] ?? 0) ?></div>
                <p class="stat__label">Recovered &amp; Returned</p>
            </div>
        </div>
        <div class="stat" style="border-color:var(--primary-soft); background:var(--primary-soft);">
            <div class="stat__icon-bubble" style="background:var(--surface);">⚡</div>
            <div>
                <div class="stat__value" style="color:var(--primary);"><?= count($aiMatches) ?></div>
                <p class="stat__label">Active AI Matches</p>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- AUTOMATIC AI MATCH ALERTS (FOR REPORTED LOST ITEMS)            -->
    <!-- ============================================================== -->
    <?php if (!empty($aiMatches)): ?>
        <section style="margin-bottom:3rem;">
            <div style="background:linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%); border:2px solid var(--primary); border-radius:var(--radius-xl); padding:1.75rem 2rem; box-shadow:0 12px 30px rgba(79, 70, 229, 0.12);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1.25rem;">
                    <div style="display:flex; align-items:center; gap:0.6rem;">
                        <span class="live-pulse"></span>
                        <h2 style="font-size:1.45rem; margin:0; color:var(--primary-dark);">
                            ✨ AI Match Alerts: Items matching your lost reports were found!
                        </h2>
                    </div>
                    <span class="badge badge-accent" style="font-size:0.85rem;"><?= count($aiMatches) ?> item(s) have potential matches</span>
                </div>

                <p style="color:var(--ink-soft); font-size:0.95rem; margin-bottom:1.5rem;">
                    Our AI continuous matching engine detected recent found listings that correspond closely to your lost belongings. Review them below and submit your claim for verification.
                </p>

                <div style="display:grid; gap:1.25rem;">
                    <?php foreach ($aiMatches as $itemGroup): ?>
                        <?php $lost = $itemGroup['lost_item']; ?>
                        <div style="background:var(--surface); border:1px solid var(--line); border-radius:var(--radius-lg); padding:1.25rem 1.5rem; box-shadow:var(--shadow-subtle);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; border-bottom:1px solid var(--line); padding-bottom:0.75rem;">
                                <div>
                                    <span style="font-size:0.82rem; font-weight:700; color:var(--primary); text-transform:uppercase;">For Your Reported Lost Item:</span>
                                    <h3 style="margin:0.2rem 0 0; font-size:1.2rem;">
                                        <a href="<?= e(url('item-details.php?id=' . (int)$lost['id'])) ?>"><?= e($lost['name']) ?></a>
                                        <span style="font-size:0.85rem; color:var(--ink-soft); font-weight:500;">(<?= e($lost['category']) ?> &bull; Lost at <?= e($lost['location']) ?> on <?= e(format_date($lost['item_date'])) ?>)</span>
                                    </h3>
                                </div>
                                <span class="badge badge-lost">Your Lost Report</span>
                            </div>

                            <div style="display:grid; gap:0.9rem;">
                                <?php foreach ($itemGroup['matches'] as $m): ?>
                                    <div style="background:var(--mist); border:1px solid var(--line); border-radius:var(--radius-md); padding:1rem 1.25rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                                        <div style="flex:1; min-width:240px;">
                                            <div style="display:flex; align-items:center; gap:0.6rem; margin-bottom:0.35rem;">
                                                <h4 style="margin:0; font-size:1.1rem;">
                                                    <a href="<?= e(url('item-details.php?id=' . (int)$m['found_id'])) ?>" style="text-decoration:underline;">
                                                        <?= e($m['found_name']) ?>
                                                    </a>
                                                </h4>
                                                <span class="badge badge-found">Found Listing</span>
                                                <span class="badge badge-accent" style="font-size:0.82rem;"><?= (int)$m['score'] ?>% AI Match</span>
                                            </div>

                                            <p style="margin:0 0 0.35rem; font-size:0.88rem; color:var(--ink);">
                                                <strong>Location Found:</strong> <?= e($m['found_location']) ?> &bull; <strong>Date:</strong> <?= e(format_date($m['found_date'])) ?>
                                            </p>

                                            <p style="margin:0; font-size:0.85rem; color:var(--primary-dark); background:rgba(79, 70, 229, 0.08); padding:0.35rem 0.65rem; border-radius:6px; display:inline-block;">
                                                💡 <strong>Why it matches:</strong> <?= e($m['explanation']) ?>
                                            </p>
                                        </div>

                                        <div style="display:flex; gap:0.6rem; align-items:center;">
                                            <a href="<?= e(url('item-details.php?id=' . (int)$m['found_id'])) ?>" class="btn btn-primary btn-sm">
                                                Inspect &amp; Claim Item &rarr;
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- COMPLETE REPORTED ITEMS HISTORY                                -->
    <!-- ============================================================== -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.75rem;">
        <h2 style="font-size:1.5rem; margin:0;">My Submission History</h2>

        <!-- History Filter Chips -->
        <div class="chips">
            <a href="<?= e(url('my-reports.php')) ?>" class="chip <?= $filterType === '' ? 'is-selected' : '' ?>">All Reports (<?= (int)($userStats['total_count'] ?? 0) ?>)</a>
            <a href="<?= e(url('my-reports.php?type=lost')) ?>" class="chip <?= $filterType === 'lost' ? 'is-selected' : '' ?>">Lost Only (<?= (int)($userStats['lost_count'] ?? 0) ?>)</a>
            <a href="<?= e(url('my-reports.php?type=found')) ?>" class="chip <?= $filterType === 'found' ? 'is-selected' : '' ?>">Found Only (<?= (int)($userStats['found_count'] ?? 0) ?>)</a>
            <a href="<?= e(url('my-reports.php?type=returned')) ?>" class="chip <?= $filterType === 'returned' ? 'is-selected' : '' ?>">Returned / Resolved (<?= (int)($userStats['returned_count'] ?? 0) ?>)</a>
        </div>
    </div>

    <?php if (!empty($userItems)): ?>
        <div style="display:grid; gap:1.25rem;">
            <?php foreach ($userItems as $it): ?>
                <article class="form-card" style="padding:1.5rem; display:grid; grid-template-columns: 120px 1fr auto; gap:1.5rem; align-items:center;">
                    <!-- Media Thumbnail -->
                    <div style="height:100px; width:120px; border-radius:var(--radius-sm); overflow:hidden; background:var(--mist); display:grid; place-items:center;">
                        <?php if (!empty($it['image_path'])): ?>
                            <img src="<?= e(url('uploads/' . $it['image_path'])) ?>" alt="<?= e($it['name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span class="item-card__initial" style="font-size:2rem;"><?= e(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Details -->
                    <div>
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem; flex-wrap:wrap;">
                            <span class="badge badge-<?= e($it['item_type']) ?>"><?= ucfirst($it['item_type']) ?></span>
                            <span class="badge <?= e(status_class($it['status'])) ?>"><?= e(status_label($it['status'])) ?></span>
                            <span style="font-size:0.83rem; color:var(--ink-muted);">Reported on <?= e(format_date($it['created_at'])) ?></span>
                        </div>

                        <h3 style="margin:0 0 0.25rem; font-size:1.25rem;">
                            <a href="<?= e(url('item-details.php?id=' . (int)$it['id'])) ?>" style="text-decoration:none; color:var(--ink);">
                                <?= e($it['name']) ?>
                            </a>
                        </h3>

                        <p style="margin:0 0 0.4rem; font-size:0.9rem; color:var(--ink-soft);">
                            <strong>Category:</strong> <?= e($it['category']) ?> &bull; 
                            <strong>Location:</strong> <?= e($it['location']) ?> &bull; 
                            <strong>Date:</strong> <?= e(format_date($it['item_date'])) ?>
                        </p>

                        <p style="margin:0; font-size:0.88rem; color:var(--ink-soft); line-height:1.4;">
                            <?= e(mb_strimwidth($it['description'], 0, 140, '...')) ?>
                        </p>

                        <?php if (!empty($it['private_details'])): ?>
                            <div style="margin-top:0.5rem; font-size:0.82rem; background:#fff8e6; padding:0.4rem 0.65rem; border-radius:4px; color:#8a5a00; display:inline-block;">
                                🔒 <strong>Your Secret Clue:</strong> <?= e($it['private_details']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex; flex-direction:column; gap:0.45rem; align-items:flex-end;">
                        <a href="<?= e(url('item-details.php?id=' . (int)$it['id'])) ?>" class="btn btn-primary btn-sm">
                            View Listing
                        </a>

                        <?php if ($it['status'] !== 'returned'): ?>
                            <form method="post" action="<?= e(url('my-reports.php')) ?>" onsubmit="return confirm('Mark this report as resolved/returned?');" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_returned">
                                <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" style="font-size:0.8rem; width:100%;">
                                    ✓ Mark Resolved
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('my-reports.php')) ?>" onsubmit="return confirm('Are you sure you want to permanently delete this report?');" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_report">
                            <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:0.78rem; padding:0.25rem 0.6rem; width:100%;">
                                Delete
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon-wrap">📋</div>
            <h3>No reports in this category</h3>
            <p>You have not submitted any reports matching the selected filter.</p>
            <div style="display:flex; gap:0.6rem; justify-content:center; margin-top:1rem;">
                <a href="<?= e(url('lost-item.php')) ?>" class="btn btn-primary btn-sm">+ Report Lost Item</a>
                <a href="<?= e(url('found-item.php')) ?>" class="btn btn-accent btn-sm">+ Report Found Item</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
