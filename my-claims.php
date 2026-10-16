<?php
/**
 * my-claims.php - Student & Staff Claims Management View
 *
 * Demonstrates:
 * - Data retrieval for the authenticated user (Experiment 8)
 * - Review status tracking (pending, approved, rejected)
 * - Clear instructions for collecting approved campus belongings
 */

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$claims = [];
try {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT c.*, i.name AS item_name, i.category, i.location, i.item_date, 
                i.status AS item_status, i.image_path, u.name AS finder_name
         FROM claims c
         JOIN items i ON i.id = c.item_id
         JOIN users u ON u.id = i.reporter_id
         WHERE c.claimant_id = ?
         ORDER BY c.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $claims = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error loading claims: ' . $e->getMessage());
}

$pageTitle = 'My Claims';
$activePage = 'claims';
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div class="section__row">
        <div>
            <span class="badge badge-found">Claims Portal</span>
            <h1 style="font-size:2.2rem; margin-top:0.4rem; margin-bottom:0.3rem;">My Item Claims</h1>
            <p style="color:var(--ink-soft); margin:0;">
                Track verification status for found belongings you have claimed.
            </p>
        </div>
        <div>
            <a href="<?= e(url('browse-items.php')) ?>" class="btn btn-ghost btn-sm">Browse More Items</a>
        </div>
    </div>

    <?php if (!empty($claims)): ?>
        <div style="display:grid; gap:1.25rem; margin-top:1.5rem;">
            <?php foreach ($claims as $c): ?>
                <article class="form-card" style="padding:1.5rem; display:grid; grid-template-columns: 120px 1fr auto; gap:1.5rem; align-items:center;">
                    <!-- Media Thumbnail -->
                    <div style="height:100px; width:120px; border-radius:var(--radius-sm); overflow:hidden; background:var(--mist); display:grid; place-items:center;">
                        <?php if (!empty($c['image_path'])): ?>
                            <img src="<?= e(url('uploads/' . $c['image_path'])) ?>" alt="<?= e($c['item_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <span class="item-card__initial" style="font-size:1.8rem;"><?= e(mb_strtoupper(mb_substr($c['item_name'], 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Claim & Item Info -->
                    <div>
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.3rem; flex-wrap:wrap;">
                            <h3 style="margin:0; font-size:1.2rem;">
                                <a href="<?= e(url('item-details.php?id=' . (int)$c['item_id'])) ?>" style="text-decoration:underline;">
                                    <?= e($c['item_name']) ?>
                                </a>
                            </h3>
                            <span class="badge badge-<?= e($c['status']) ?>"><?= ucfirst($c['status']) ?></span>
                            <span class="badge <?= e(status_class($c['item_status'])) ?>">Item: <?= e(status_label($c['item_status'])) ?></span>
                        </div>

                        <p style="margin:0 0 0.4rem; font-size:0.88rem; color:var(--ink-soft);">
                            Category: <strong><?= e($c['category']) ?></strong> &bull;
                            Found at: <strong><?= e($c['location']) ?></strong> on <?= e(format_date($c['item_date'])) ?>
                        </p>

                        <div style="background:var(--mist); padding:0.6rem 0.8rem; border-radius:var(--radius-sm); font-size:0.88rem; margin-top:0.4rem;">
                            <strong>Your identifying details:</strong> <?= e($c['claim_description']) ?>
                        </div>

                        <?php if ($c['status'] === 'approved'): ?>
                            <div style="margin-top:0.6rem; padding:0.7rem 0.9rem; background:var(--success-soft); border-radius:var(--radius-sm); border:1px solid #b4e5d0; font-size:0.88rem; color:var(--success);">
                                <strong>✓ Claim Approved!</strong> Please visit the Main Campus Administration Office (Room 102) with your valid College ID Card to collect your item.
                                <?php if (!empty($c['admin_note'])): ?>
                                    <div style="margin-top:0.3rem;"><strong>Admin Note:</strong> <?= e($c['admin_note']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($c['status'] === 'rejected'): ?>
                            <div style="margin-top:0.6rem; padding:0.7rem 0.9rem; background:var(--danger-soft); border-radius:var(--radius-sm); border:1px solid #f6c5c0; font-size:0.88rem; color:var(--danger);">
                                <strong>✕ Claim Rejected.</strong>
                                <?php if (!empty($c['admin_note'])): ?>
                                    <?= e($c['admin_note']) ?>
                                <?php else: ?>
                                    The details supplied did not match campus verification records.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p style="margin:0.4rem 0 0; font-size:0.84rem; color:var(--ink-soft);">
                                ⏳ Under review by campus authorities. Submitted on <?= e(format_date($c['created_at'])) ?>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div style="text-align:right;">
                        <a href="<?= e(url('item-details.php?id=' . (int)$c['item_id'])) ?>" class="btn btn-ghost btn-sm">View Item</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state" style="margin-top:2rem;">
            <h3>No claims submitted yet</h3>
            <p>If you recognize one of your lost belongings in the found listings, you can submit a claim with your private identifying details.</p>
            <a href="<?= e(url('browse-items.php?type=found')) ?>" class="btn btn-primary">Browse Found Items</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
