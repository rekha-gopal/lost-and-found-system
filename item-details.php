<?php
/**
 * item-details.php - Detailed View of a Lost or Found Campus Item
 *
 * Demonstrates:
 * - PHP Data Retrieval with PDO (Experiment 8)
 * - Safe presentation (no sensitive contact details leaked to public)
 * - Claim submission workflow (Section 3.F)
 * - AI matching suggestions display with score and explanation (Section 3.E)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash('error', 'Invalid item requested.');
    redirect('browse-items.php');
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT i.*, u.name AS reporter_name, u.email AS reporter_email
     FROM items i
     JOIN users u ON u.id = i.reporter_id
     WHERE i.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    flash('error', 'The requested item was not found.');
    redirect('browse-items.php');
}

$user = current_user();
$isOwner = $user && ((int)$user['id'] === (int)$item['reporter_id']);
$isAdmin = is_admin();

// Check if user already submitted a claim on this item
$userClaim = null;
if ($user && $item['item_type'] === 'found') {
    $cStmt = $pdo->prepare("SELECT * FROM claims WHERE item_id = ? AND claimant_id = ? ORDER BY id DESC LIMIT 1");
    $cStmt->execute([$item['id'], $user['id']]);
    $userClaim = $cStmt->fetch();
}

// Fetch suggested AI matches for this item
$matches = get_matches_for_item((int)$item['id'], $item['item_type'], $pdo);

// If no cached matches exist yet, run AI matching now
if (empty($matches)) {
    update_item_matches((int)$item['id'], $item['item_type'], $pdo);
    $matches = get_matches_for_item((int)$item['id'], $item['item_type'], $pdo);
}

$pageTitle = e($item['name']) . ' (' . ucfirst($item['item_type']) . ')';
$activePage = 'browse';
$extraScripts = ['assets/js/ajax.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div style="margin-bottom:1.5rem;">
        <a href="<?= e(url('browse-items.php')) ?>" class="btn btn-ghost btn-sm">&larr; Back to all items</a>
    </div>

    <div style="display:grid; grid-template-columns: 1.1fr 0.9fr; gap:2.5rem; align-items:start;">
        <!-- Left: Item Photo and Core Details -->
        <article class="form-card" style="padding:2rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
                <div style="display:flex; gap:0.4rem;">
                    <span class="badge badge-<?= e($item['item_type']) ?>"><?= ucfirst($item['item_type']) ?></span>
                    <span class="badge <?= e(status_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span>
                </div>
                <span style="font-size:0.88rem; color:var(--ink-soft);">Reported: <?= e(format_date($item['created_at'])) ?></span>
            </div>

            <h1 style="font-size:2.2rem; margin-bottom:0.5rem;"><?= e($item['name']) ?></h1>
            <p style="font-size:1.1rem; color:var(--ink-soft); margin-bottom:1.5rem;">
                Category: <strong><?= e($item['category']) ?></strong>
            </p>

            <!-- Media Preview -->
            <div style="margin-bottom:1.5rem; border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--line); background:var(--mist); text-align:center;">
                <?php if (!empty($item['image_path'])): ?>
                    <img src="<?= e(url('uploads/' . $item['image_path'])) ?>" alt="<?= e($item['name']) ?>" style="max-height:380px; width:100%; object-fit:contain; background:#fafafa;">
                <?php else: ?>
                    <div style="padding:3.5rem 1rem; color:var(--ink-soft);">
                        <svg style="width:48px; height:48px; margin:0 auto 0.5rem; display:block; opacity:0.4;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                        </svg>
                        <p style="margin:0; font-size:0.95rem;">No photograph uploaded with this report.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <h3 style="margin-bottom:0.4rem;">Public Description</h3>
            <p style="white-space:pre-line; color:var(--ink); font-size:1rem; line-height:1.6; margin-bottom:1.5rem; background:var(--mist); padding:1rem; border-radius:var(--radius-sm);">
                <?= e($item['description']) ?>
            </p>

            <!-- Key metadata table -->
            <table style="width:100%; border-collapse:collapse; margin-bottom:1.5rem; font-size:0.95rem;">
                <tbody>
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:0.6rem 0; font-weight:700; color:var(--ink-soft); width:35%;">Campus Location:</td>
                        <td style="padding:0.6rem 0; font-weight:600;"><?= e($item['location']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:0.6rem 0; font-weight:700; color:var(--ink-soft);">Date <?= $item['item_type'] === 'lost' ? 'Lost' : 'Found' ?>:</td>
                        <td style="padding:0.6rem 0; font-weight:600;"><?= e(format_date($item['item_date'])) ?> <?= !empty($item['item_time']) ? 'at ' . e($item['item_time']) : '' ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:0.6rem 0; font-weight:700; color:var(--ink-soft);">Primary Colour:</td>
                        <td style="padding:0.6rem 0;"><?= e($item['color'] ?: 'Not specified') ?></td>
                    </tr>
                    <?php if ($item['estimated_value'] !== null): ?>
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:0.6rem 0; font-weight:700; color:var(--ink-soft);">Estimated Value:</td>
                        <td style="padding:0.6rem 0;">₹<?= number_format((float)$item['estimated_value'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($item['additional_notes'])): ?>
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:0.6rem 0; font-weight:700; color:var(--ink-soft);">Additional Notes:</td>
                        <td style="padding:0.6rem 0;"><?= e($item['additional_notes']) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Administrator / Owner Private View -->
            <?php if ($isAdmin || $isOwner): ?>
                <div style="background:#fff8e6; border:1.5px solid #ffd480; padding:1.1rem; border-radius:var(--radius-sm); margin-top:1.5rem;">
                    <div style="display:flex; align-items:center; gap:0.4rem; font-weight:700; color:#8a5a00; margin-bottom:0.4rem;">
                        <span>🔒 Authorized View (Admin / Reporter Only)</span>
                    </div>
                    <?php if (!empty($item['private_details'])): ?>
                        <p style="margin:0 0 0.5rem; font-size:0.92rem;">
                            <strong>Secret Identifying Details:</strong> <?= e($item['private_details']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($item['contact_info'])): ?>
                        <p style="margin:0; font-size:0.92rem;">
                            <strong>Private Contact Info:</strong> <?= e($item['contact_info']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>

        <!-- Right: Actions, Claim Form & AI Match Suggestions -->
        <div>
            <!-- If Found Item: Claim Module -->
            <?php if ($item['item_type'] === 'found'): ?>
                <div class="form-card" style="margin-bottom:2rem; padding:1.75rem; border-color:var(--blue);">
                    <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
                        <span style="font-size:1.4rem;">🤝</span>
                        <h2 style="margin:0; font-size:1.4rem;">Is this your item?</h2>
                    </div>

                    <?php if ($item['status'] === 'returned'): ?>
                        <div class="flash flash-info" style="margin:0;">
                            <span>This item has already been successfully verified and returned to its rightful owner.</span>
                        </div>
                    <?php elseif ($userClaim): ?>
                        <div style="background:var(--blue-soft); padding:1rem; border-radius:var(--radius-sm); border:1px solid var(--blue);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                                <strong>Your Claim Status:</strong>
                                <span class="badge <?= e(status_class($userClaim['status'])) ?>"><?= e(status_label($userClaim['status'])) ?></span>
                            </div>
                            <p style="font-size:0.9rem; margin:0 0 0.5rem; color:var(--ink-soft);">
                                Submitted on <?= e(format_date($userClaim['created_at'])) ?>
                            </p>
                            <p style="font-size:0.92rem; margin:0; background:var(--white); padding:0.6rem; border-radius:var(--radius-sm);">
                                <strong>Your verification details:</strong> <?= e($userClaim['claim_description']) ?>
                            </p>
                            <?php if (!empty($userClaim['admin_note'])): ?>
                                <p style="font-size:0.92rem; margin:0.6rem 0 0; color:var(--blue-dark);">
                                    <strong>Admin Note:</strong> <?= e($userClaim['admin_note']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($user): ?>
                        <p style="color:var(--ink-soft); font-size:0.93rem; margin-bottom:1.2rem;">
                            To claim this found item, state specific identifying details that are not shown in the public listing (e.g. engravings, exact contents, receipts, passwords, or serial numbers).
                        </p>

                        <form id="claim-form" method="post" action="<?= e(url('api/create-claim.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">

                            <div class="field">
                                <label for="claim_description">Your Proof / Secret Identifying Details <span style="color:var(--danger)">*</span></label>
                                <textarea id="claim_description" name="claim_description" required minlength="10" spellcheck="true"
                                          placeholder="State details only the owner would know, e.g. serial numbers, inside marks, lock wallpaper, etc."></textarea>
                                <span class="hint">An administrator verifies these against the finder's report before releasing the item.</span>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:0.5rem;">
                                Submit Claim for Verification
                            </button>
                        </form>
                    <?php else: ?>
                        <p style="color:var(--ink-soft); font-size:0.95rem; margin-bottom:1rem;">
                            Please log in with your college student or staff account to submit a claim for this item.
                        </p>
                        <a href="<?= e(url('login.php')) ?>" class="btn btn-primary" style="width:100%;">Log In to Claim</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- AI-Assisted Matching Section (Section 3.E) -->
            <div class="form-card" style="padding:1.75rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:1.3rem;">🤖</span>
                        <h2 style="margin:0; font-size:1.3rem;">AI Suggested Matches</h2>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" id="recheck-ai-btn" data-id="<?= (int)$item['id'] ?>" data-type="<?= e($item['item_type']) ?>">
                        ↻ Refresh AI
                    </button>
                </div>

                <p style="font-size:0.9rem; color:var(--ink-soft); margin-bottom:1.2rem;">
                    Our AI compares keywords, categories, locations, colours and dates to suggest possible pairs.
                </p>

                <div id="ai-matches-list" style="display:grid; gap:0.9rem;">
                    <?php if (!empty($matches)): ?>
                        <?php foreach ($matches as $m): ?>
                            <div style="background:var(--mist); border:1px solid var(--line); border-radius:var(--radius-sm); padding:0.9rem 1rem;">
                                <div style="display:flex; justify-content:space-between; align-items:start; gap:0.5rem; margin-bottom:0.4rem;">
                                    <h4 style="margin:0; font-size:1rem;">
                                        <a href="<?= e(url('item-details.php?id=' . (int)$m['id'])) ?>" style="text-decoration:underline;">
                                            <?= e($m['name']) ?>
                                        </a>
                                    </h4>
                                    <span class="badge badge-accent" style="font-size:0.85rem; font-weight:700;">
                                        <?= (int)$m['score'] ?>% Match
                                    </span>
                                </div>
                                <p style="font-size:0.84rem; color:var(--ink-soft); margin:0 0 0.5rem;">
                                    <strong>Why it matches:</strong> <?= e($m['explanation']) ?>
                                </p>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; color:var(--ink-soft);">
                                    <span><?= e($m['location']) ?> &bull; <?= e(format_date($m['item_date'])) ?></span>
                                    <a href="<?= e(url('item-details.php?id=' . (int)$m['id'])) ?>" class="btn btn-ghost btn-sm" style="padding:0.2rem 0.5rem; font-size:0.75rem;">
                                        Inspect &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:1.5rem 1rem; color:var(--ink-soft); background:var(--mist); border-radius:var(--radius-sm);">
                            <p style="margin:0; font-size:0.92rem;">No high-confidence AI matches found yet.</p>
                            <span style="font-size:0.82rem;">Matches are automatically created as new reports are filed.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
