<?php
/**
 * found-item.php - Report a Found Item
 *
 * Demonstrates:
 * - HTML5 Advanced Forms (date, time, email, spellcheck, contenteditable)
 * - Private identifying details for claim validation (security & fraud prevention)
 * - JavaScript client-side validation & events
 * - PHP server-side validation & PDO insertion into MySQL
 * - Automatic AI matching trigger upon report submission
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';

require_login();

$user = current_user();
$errors = [];
$success = false;
$matches = [];

$formData = [
    'name'             => '',
    'category'         => '',
    'description'      => '',
    'color'            => '',
    'location'         => '',
    'item_date'        => date('Y-m-d'),
    'item_time'        => date('H:i'),
    'contact_info'     => $user['email'],
    'private_details'  => '',
    'additional_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'Session security token expired. Please try again.';
    }

    $formData['name']             = trim((string)($_POST['name'] ?? ''));
    $formData['category']         = trim((string)($_POST['category'] ?? ''));
    $formData['description']      = trim((string)($_POST['description'] ?? ''));
    $formData['color']            = trim((string)($_POST['color'] ?? ''));
    $formData['location']         = trim((string)($_POST['location'] ?? ''));
    $formData['item_date']        = trim((string)($_POST['item_date'] ?? ''));
    $formData['item_time']        = trim((string)($_POST['item_time'] ?? ''));
    $formData['contact_info']     = trim((string)($_POST['contact_info'] ?? ''));
    $formData['private_details']  = trim((string)($_POST['private_details'] ?? ''));
    $formData['additional_notes'] = trim((string)($_POST['additional_notes'] ?? ''));

    // Validation
    if ($formData['name'] === '' || mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 120) {
        $errors[] = 'Item name must be between 2 and 120 characters.';
    }
    if (!in_array($formData['category'], CATEGORIES, true)) {
        $errors[] = 'Please select a valid item category.';
    }
    if ($formData['description'] === '' || mb_strlen($formData['description']) < 10) {
        $errors[] = 'Please provide a clear public description (at least 10 characters).';
    }
    if ($formData['location'] === '') {
        $errors[] = 'Please specify where the item was found.';
    }
    if ($formData['item_date'] === '' || !strtotime($formData['item_date'])) {
        $errors[] = 'Please select a valid date when the item was found.';
    } elseif ($formData['item_date'] > date('Y-m-d')) {
        $errors[] = 'Found date cannot be in the future.';
    }
    if ($formData['contact_info'] === '') {
        $errors[] = 'Please provide finder contact details (email or phone).';
    }

    // Handle Image Upload
    $imagePath = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadRes = handle_image_upload($_FILES['item_image']);
        if (!$uploadRes['success']) {
            $errors[] = $uploadRes['error'];
        } else {
            $imagePath = $uploadRes['filename'];
        }
    }

    if (empty($errors)) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                "INSERT INTO items 
                (reporter_id, item_type, name, category, description, color, location, item_date, item_time, image_path, additional_notes, private_details, contact_info, status, created_at)
                VALUES (?, 'found', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'found', NOW())"
            );

            $stmt->execute([
                $user['id'],
                $formData['name'],
                $formData['category'],
                $formData['description'],
                $formData['color'] ?: null,
                $formData['location'],
                $formData['item_date'],
                $formData['item_time'] ?: null,
                $imagePath,
                $formData['additional_notes'] ?: null,
                $formData['private_details'] ?: null,
                $formData['contact_info']
            ]);

            $newItemId = (int)$pdo->lastInsertId();

            // Run AI-assisted matching against all existing lost items
            $matches = update_item_matches($newItemId, 'found', $pdo);

            $success = true;
            flash('success', 'Thank you! The found item report has been logged.');

            // If it's an AJAX request, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response([
                    'success'  => true,
                    'message'  => 'The found item report has been logged successfully.',
                    'item_id'  => $newItemId,
                    'matches'  => $matches
                ]);
            }
        } catch (PDOException $e) {
            error_log('Error saving found item: ' . $e->getMessage());
            $errors[] = 'Could not save report due to a server error. Please try again.';
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response(['success' => false, 'errors' => $errors], 422);
        }
    }
}

$pageTitle = 'Report Found Item';
$activePage = 'found';
$extraScripts = ['assets/js/ajax.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div class="page-narrow" style="width: min(780px, 100% - 2.5rem);">
        <div class="form-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <span class="badge badge-found">Found Item Report</span>
                    <h1 style="font-size:2.1rem; margin-top:0.4rem;">Report a Found Item</h1>
                </div>
                <div style="font-size:0.88rem; color:var(--ink-soft); background:var(--mist); padding:0.5rem 0.9rem; border-radius:var(--radius-sm);">
                    Finder: <strong><?= e($user['name']) ?></strong>
                </div>
            </div>

            <p class="form-note" style="margin-top:-0.5rem; margin-bottom:1.8rem;">
                Found someone&rsquo;s belongings on campus? Logging it here helps re-unite it with its rightful owner.
                Private identifying clues are kept hidden from public view for verification.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="flash flash-error" role="alert">
                    <div>
                        <strong>Please resolve the following:</strong>
                        <ul style="margin:0.4rem 0 0; padding-left:1.2rem;">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="flash flash-success" role="status">
                    <div>
                        <strong>Found Item Registered!</strong> Thank you for your honesty.
                        <a href="<?= e(url('browse-items.php')) ?>" style="text-decoration:underline; font-weight:700; margin-left:0.5rem;">Browse all items &rarr;</a>
                    </div>
                </div>

                <?php if (!empty($matches)): ?>
                    <div style="margin-top:1.5rem; padding:1.25rem; border-radius:var(--radius-md); background:var(--blue-soft); border:1.5px solid var(--blue);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
                            <span class="badge badge-found">AI Potential Match!</span>
                            <h3 style="margin:0; color:var(--blue-dark);">Possible owner reports found:</h3>
                        </div>
                        <p style="font-size:0.92rem; color:var(--ink-soft); margin-bottom:1rem;">
                            The following students recently reported losing an item closely matching this description:
                        </p>
                        <div style="display:grid; gap:0.8rem;">
                            <?php foreach ($matches as $m): ?>
                                <div style="background:var(--white); padding:0.9rem 1.1rem; border-radius:var(--radius-sm); border:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.8rem;">
                                    <div>
                                        <h4 style="margin:0 0 0.2rem; font-size:1.05rem;"><?= e($m['item']['name']) ?> (<?= e($m['item']['category']) ?>)</h4>
                                        <p style="margin:0; font-size:0.86rem; color:var(--ink-soft);"><?= e($m['explanation']) ?></p>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:0.8rem;">
                                        <span class="badge badge-accent" style="font-size:0.9rem;"><?= (int)$m['score'] ?>% Match</span>
                                        <a href="<?= e(url('item-details.php?id=' . (int)$m['lost_item_id'])) ?>" class="btn btn-ghost btn-sm">View Lost Report</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form id="found-item-form" method="post" action="<?= e(url('found-item.php')) ?>" enctype="multipart/form-data" novalidate style="margin-top:1.5rem;">
                <?= csrf_field() ?>

                <div class="form-grid">
                    <!-- Item name -->
                    <div class="field field--full">
                        <label for="name">Item Name <span style="color:var(--danger)">*</span></label>
                        <input id="name" name="name" type="text" required minlength="2" maxlength="120"
                               placeholder="e.g. Scientific Calculator, Fastrack Water Bottle, USB Flash Drive"
                               value="<?= e($formData['name']) ?>" spellcheck="true">
                        <span class="hint">Keep it objective and clear.</span>
                    </div>

                    <!-- Category -->
                    <div class="field">
                        <label for="category">Category <span style="color:var(--danger)">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach (CATEGORIES as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= $formData['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Color -->
                    <div class="field">
                        <label for="color">Primary Colour</label>
                        <input id="color" name="color" type="text" maxlength="50"
                               placeholder="e.g. Silver, Red, Black, Transparent"
                               value="<?= e($formData['color']) ?>" spellcheck="true">
                    </div>

                    <!-- Campus Location -->
                    <div class="field">
                        <label for="location">Location Where Found <span style="color:var(--danger)">*</span></label>
                        <input id="location" name="location" list="location-suggestions" type="text" required maxlength="150"
                               placeholder="e.g. Main Canteen Table 4, Ground Playground"
                               value="<?= e($formData['location']) ?>">
                        <datalist id="location-suggestions">
                            <?php foreach (CAMPUS_LOCATIONS as $loc): ?>
                                <option value="<?= e($loc) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <!-- Date & Time Found -->
                    <div class="field">
                        <label for="item_date">Date Found <span style="color:var(--danger)">*</span></label>
                        <input id="item_date" name="item_date" type="date" max="<?= date('Y-m-d') ?>" required
                               value="<?= e($formData['item_date']) ?>">
                    </div>

                    <div class="field">
                        <label for="item_time">Approximate Time Found</label>
                        <input id="item_time" name="item_time" type="time"
                               value="<?= e($formData['item_time']) ?>">
                    </div>

                    <!-- Finder Contact Info (Hidden from public) -->
                    <div class="field">
                        <label for="contact_info">Finder Contact (Email or Phone) <span style="color:var(--danger)">*</span></label>
                        <input id="contact_info" name="contact_info" type="text" required maxlength="150"
                               placeholder="Phone or email"
                               value="<?= e($formData['contact_info']) ?>">
                        <span class="hint">Kept strictly private; only visible to campus administrators.</span>
                    </div>

                    <!-- Public Description -->
                    <div class="field field--full">
                        <label for="description">Public Description <span style="color:var(--danger)">*</span></label>
                        <textarea id="description" name="description" required minlength="10" spellcheck="true"
                                  placeholder="Describe the item general appearance without giving away all secret identifying traits."><?= e($formData['description']) ?></textarea>
                        <span class="hint">Visible to everyone browsing the lost &amp; found board.</span>
                    </div>

                    <!-- Private Identifying Details (Secret Clues for Verification) -->
                    <div class="field field--full" style="background:var(--mist); padding:1rem; border-radius:var(--radius-sm); border:1px dashed var(--line);">
                        <label for="private_details" style="color:var(--ink); font-weight:700;">
                            🔒 Secret Identifying Details (Never Shown Publicly)
                        </label>
                        <textarea id="private_details" name="private_details" spellcheck="true"
                                  placeholder="e.g. Serial number ending in 842, lockscreen wallpaper of a dog, initials engraved inside, cash amount of ₹350 in wallet."><?= e($formData['private_details']) ?></textarea>
                        <span class="hint">
                            Only college office administrators see this. When someone claims this item, they must state these details to prove genuine ownership!
                        </span>
                    </div>

                    <!-- Image Upload -->
                    <div class="field field--full">
                        <label for="item_image">Item Photo (Optional)</label>
                        <input id="item_image" name="item_image" type="file" accept="image/png, image/jpeg, image/webp">
                        <span class="hint">Formats: JPG, PNG, WEBP. Maximum file size: 2MB.</span>
                    </div>

                    <!-- Contenteditable Additional Notes -->
                    <div class="field field--full">
                        <label>Additional Notes / Drop-off Information</label>
                        <div id="additional-notes-editable" class="editable" contenteditable="true" spellcheck="true"
                             data-placeholder="Click here to type notes (e.g. Handed over to Security Guard Ramu at Main Gate)..."><?= e($formData['additional_notes']) ?></div>
                        <input type="hidden" id="additional_notes" name="additional_notes" value="<?= e($formData['additional_notes']) ?>">
                        <span class="hint">Demonstrating HTML5 <code>contenteditable="true"</code> with two-way form synchronization.</span>
                    </div>
                </div>

                <div style="margin-top:2rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <button class="btn btn-accent" type="submit" id="submit-found-btn">
                        Submit Found Item Report
                    </button>
                    <a class="btn btn-ghost" href="<?= e(url('browse-items.php')) ?>">Cancel</a>
                    <span id="form-spinner" class="loader" style="display:none; margin:0 0.5rem; width:24px; height:24px; border-width:3px;"></span>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Synchronize contenteditable with hidden input before form submit
document.addEventListener('DOMContentLoaded', function () {
    var editable = document.getElementById('additional-notes-editable');
    var hidden = document.getElementById('additional_notes');
    var form = document.getElementById('found-item-form');

    if (editable && hidden) {
        editable.addEventListener('input', function () {
            hidden.value = editable.innerText.trim();
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (editable && hidden) {
                hidden.value = editable.innerText.trim();
            }
        });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
