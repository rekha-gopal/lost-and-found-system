<?php
/**
 * lost-item.php - Report a Lost Item
 *
 * Demonstrates:
 * - HTML5 Advanced Forms (date, time, number, email, spellcheck, contenteditable)
 * - JavaScript client-side validation & events (input, change, submit)
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
    'estimated_value'  => '',
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
    $formData['estimated_value']  = trim((string)($_POST['estimated_value'] ?? ''));
    $formData['additional_notes'] = trim((string)($_POST['additional_notes'] ?? ''));

    // Validation
    if ($formData['name'] === '' || mb_strlen($formData['name']) < 2 || mb_strlen($formData['name']) > 120) {
        $errors[] = 'Item name must be between 2 and 120 characters.';
    }
    if (!in_array($formData['category'], CATEGORIES, true)) {
        $errors[] = 'Please select a valid item category.';
    }
    if ($formData['description'] === '' || mb_strlen($formData['description']) < 10) {
        $errors[] = 'Please provide a detailed description (at least 10 characters).';
    }
    if ($formData['location'] === '') {
        $errors[] = 'Please specify where the item was lost.';
    }
    if ($formData['item_date'] === '' || !strtotime($formData['item_date'])) {
        $errors[] = 'Please select a valid date when the item was lost.';
    } elseif ($formData['item_date'] > date('Y-m-d')) {
        $errors[] = 'Lost date cannot be in the future.';
    }

    $estimatedValue = null;
    if ($formData['estimated_value'] !== '') {
        if (!is_numeric($formData['estimated_value']) || (float)$formData['estimated_value'] < 0) {
            $errors[] = 'Estimated value must be a positive number.';
        } else {
            $estimatedValue = (float)$formData['estimated_value'];
        }
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
                (reporter_id, item_type, name, category, description, color, location, item_date, item_time, estimated_value, image_path, additional_notes, status, created_at)
                VALUES (?, 'lost', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'lost', NOW())"
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
                $estimatedValue,
                $imagePath,
                $formData['additional_notes'] ?: null
            ]);

            $newItemId = (int)$pdo->lastInsertId();

            // Run AI-assisted matching against all existing found items
            $matches = update_item_matches($newItemId, 'lost', $pdo);

            $success = true;
            flash('success', 'Your lost item report has been published successfully.');

            // If it's an AJAX request, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response([
                    'success'  => true,
                    'message'  => 'Your lost item report has been published successfully.',
                    'item_id'  => $newItemId,
                    'matches'  => $matches
                ]);
            }
        } catch (PDOException $e) {
            error_log('Error saving lost item: ' . $e->getMessage());
            $errors[] = 'Could not save report due to a server error. Please try again.';
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response(['success' => false, 'errors' => $errors], 422);
        }
    }
}

$pageTitle = 'Report Lost Item';
$activePage = 'lost';
$extraScripts = ['assets/js/ajax.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container section">
    <div class="page-narrow" style="width: min(780px, 100% - 2.5rem);">
        <div class="form-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <span class="badge badge-lost">Lost Item Report</span>
                    <h1 style="font-size:2.1rem; margin-top:0.4rem;">Report a Lost Item</h1>
                </div>
                <div style="font-size:0.88rem; color:var(--ink-soft); background:var(--mist); padding:0.5rem 0.9rem; border-radius:var(--radius-sm);">
                    Logged in as: <strong><?= e($user['name']) ?></strong> (<?= e($user['email']) ?>)
                </div>
            </div>

            <p class="form-note" style="margin-top:-0.5rem; margin-bottom:1.8rem;">
                Fill in the details about what you lost. Our smart AI matcher will automatically scan campus found reports and alert you of high-confidence matches!
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
                        <strong>Report Published!</strong> Your lost report has been registered.
                        <a href="<?= e(url('browse-items.php')) ?>" style="text-decoration:underline; font-weight:700; margin-left:0.5rem;">Browse all items &rarr;</a>
                    </div>
                </div>

                <?php if (!empty($matches)): ?>
                    <div style="margin-top:1.5rem; padding:1.25rem; border-radius:var(--radius-md); background:var(--purple-soft); border:1.5px solid var(--purple);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.75rem;">
                            <span class="badge badge-accent">AI Match Found!</span>
                            <h3 style="margin:0; color:var(--purple-dark);">Possible matches detected immediately:</h3>
                        </div>
                        <p style="font-size:0.92rem; color:var(--ink-soft); margin-bottom:1rem;">
                            Our AI algorithm compared your report with items currently in the college office:
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
                                        <a href="<?= e(url('item-details.php?id=' . (int)$m['found_item_id'])) ?>" class="btn btn-primary btn-sm">Inspect &amp; Claim</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form id="lost-item-form" method="post" action="<?= e(url('lost-item.php')) ?>" enctype="multipart/form-data" novalidate style="margin-top:1.5rem;">
                <?= csrf_field() ?>

                <div class="form-grid">
                    <!-- Student details (Readonly from session) -->
                    <div class="field">
                        <label for="student_name">Student / Reporter Name</label>
                        <input id="student_name" type="text" value="<?= e($user['name']) ?>" readonly style="background:var(--mist); color:var(--ink-soft);">
                    </div>

                    <div class="field">
                        <label for="college_email">College Email</label>
                        <input id="college_email" type="email" value="<?= e($user['email']) ?>" readonly style="background:var(--mist); color:var(--ink-soft);">
                    </div>

                    <!-- Item name -->
                    <div class="field field--full">
                        <label for="name">Item Name <span style="color:var(--danger)">*</span></label>
                        <input id="name" name="name" type="text" required minlength="2" maxlength="120"
                               placeholder="e.g. Dell Inspiron Laptop, Black Titan Watch, Blue Spiral Notebook"
                               value="<?= e($formData['name']) ?>" spellcheck="true">
                        <span class="hint">Be concise and specific with brand, model, or type.</span>
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
                               placeholder="e.g. Matte Black, Navy Blue, Silver"
                               value="<?= e($formData['color']) ?>" spellcheck="true">
                    </div>

                    <!-- Campus Location -->
                    <div class="field">
                        <label for="location">Last Seen Location <span style="color:var(--danger)">*</span></label>
                        <input id="location" name="location" list="location-suggestions" type="text" required maxlength="150"
                               placeholder="e.g. IT Block Library, Main Canteen"
                               value="<?= e($formData['location']) ?>">
                        <datalist id="location-suggestions">
                            <?php foreach (CAMPUS_LOCATIONS as $loc): ?>
                                <option value="<?= e($loc) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <!-- Estimated Value -->
                    <div class="field">
                        <label for="estimated_value">Estimated Value (₹ / Optional)</label>
                        <input id="estimated_value" name="estimated_value" type="number" min="0" step="1"
                               placeholder="e.g. 1500" value="<?= e($formData['estimated_value']) ?>">
                        <span class="hint">Helps in verification and security.</span>
                    </div>

                    <!-- Date & Time -->
                    <div class="field">
                        <label for="item_date">Date Lost <span style="color:var(--danger)">*</span></label>
                        <input id="item_date" name="item_date" type="date" max="<?= date('Y-m-d') ?>" required
                               value="<?= e($formData['item_date']) ?>">
                    </div>

                    <div class="field">
                        <label for="item_time">Approximate Time Lost</label>
                        <input id="item_time" name="item_time" type="time"
                               value="<?= e($formData['item_time']) ?>">
                    </div>

                    <!-- Description -->
                    <div class="field field--full">
                        <label for="description">Detailed Description <span style="color:var(--danger)">*</span></label>
                        <textarea id="description" name="description" required minlength="10" spellcheck="true"
                                  placeholder="Describe distinctive marks, stickers, scratches, contents, brand labels, etc."><?= e($formData['description']) ?></textarea>
                        <span class="hint">Used by our AI to match with found reports. Include any notable features.</span>
                    </div>

                    <!-- Image Upload -->
                    <div class="field field--full">
                        <label for="item_image">Item Photo (Optional)</label>
                        <input id="item_image" name="item_image" type="file" accept="image/png, image/jpeg, image/webp">
                        <span class="hint">Formats: JPG, PNG, WEBP. Maximum file size: 2MB.</span>
                    </div>

                    <!-- Contenteditable Additional Notes (HTML5 advanced form demonstration) -->
                    <div class="field field--full">
                        <label>Additional Notes / Special Instructions</label>
                        <div id="additional-notes-editable" class="editable" contenteditable="true" spellcheck="true"
                             data-placeholder="Click here to type additional remarks (e.g. contact phone, reward info, urgency)..."><?= e($formData['additional_notes']) ?></div>
                        <input type="hidden" id="additional_notes" name="additional_notes" value="<?= e($formData['additional_notes']) ?>">
                        <span class="hint">Demonstrating HTML5 <code>contenteditable="true"</code> with two-way sync on submit.</span>
                    </div>
                </div>

                <div style="margin-top:2rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <button class="btn btn-primary" type="submit" id="submit-lost-btn">
                        Submit Lost Report &amp; Run AI Match
                    </button>
                    <a class="btn btn-ghost" href="<?= e(url('browse-items.php')) ?>">Cancel</a>
                    <span id="form-spinner" class="loader" style="display:none; margin:0 0.5rem; width:24px; height:24px; border-width:3px;"></span>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Synchronize contenteditable with hidden input before form submit (Experiment 3 & 6)
document.addEventListener('DOMContentLoaded', function () {
    var editable = document.getElementById('additional-notes-editable');
    var hidden = document.getElementById('additional_notes');
    var form = document.getElementById('lost-item-form');

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
