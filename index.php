<?php
/**
 * index.php - Home page
 * Modern, responsive campus lost and found portal with live statistics,
 * AI match preview, and real-time report listings.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';

$stats  = ['lost' => 0, 'found' => 0, 'returned' => 0];
$match  = null;
$recent = [];
$userAiMatches = [];

$user = current_user();

try {
    $pdo = db();

    if ($user) {
        $userAiMatches = get_user_lost_item_matches((int)$user['id'], $pdo);
    }


    $row = $pdo->query(
        "SELECT SUM(item_type = 'lost')  AS lost,
                SUM(item_type = 'found') AS found,
                SUM(status = 'returned') AS returned
         FROM items"
    )->fetch();
    $stats = [
        'lost'     => (int)($row['lost'] ?? 0),
        'found'    => (int)($row['found'] ?? 0),
        'returned' => (int)($row['returned'] ?? 0),
    ];

    // Best current AI match between two unresolved reports
    $match = $pdo->query(
        "SELECT m.score, m.explanation,
                l.name AS lost_name,  l.location AS lost_location,  l.item_date AS lost_date,
                f.name AS found_name, f.location AS found_location, f.item_date AS found_date,
                f.id AS found_id
         FROM match_suggestions m
         JOIN items l ON l.id = m.lost_item_id
         JOIN items f ON f.id = m.found_item_id
         WHERE l.status <> 'returned' AND f.status <> 'returned'
         ORDER BY m.score DESC LIMIT 1"
    )->fetch() ?: null;

    // Latest reports (public columns only: no private details, no contact info)
    $recent = $pdo->query(
        "SELECT i.id, i.item_type, i.name, i.category, i.location, i.item_date, i.status, i.image_path, u.name AS reporter_name
         FROM items i
         JOIN users u ON u.id = i.reporter_id
         ORDER BY i.created_at DESC, i.id DESC LIMIT 4"
    )->fetchAll();
} catch (PDOException $ex) {
    error_log('Home page query failed: ' . $ex->getMessage());
}

$pageTitle  = 'Lost something on campus?';
$activePage = 'home';
require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($userAiMatches)): ?>
    <div style="background:var(--primary-soft); border-bottom:2px solid #c7d2fe; padding:1rem 0;">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <span class="live-pulse"></span>
                <div>
                    <strong style="color:var(--primary-dark); font-size:1.02rem;">
                        ✨ AI Match Alert: A found item matches your reported "<?= e($userAiMatches[0]['lost_item']['name']) ?>"!
                    </strong>
                    <div style="font-size:0.86rem; color:var(--ink-soft); margin-top:0.15rem;">
                        Match confidence: <strong><?= (int)$userAiMatches[0]['matches'][0]['score'] ?>%</strong> &bull; Found at <strong><?= e($userAiMatches[0]['matches'][0]['found_location']) ?></strong>: &ldquo;<?= e($userAiMatches[0]['matches'][0]['explanation']) ?>&rdquo;
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <a href="<?= e(url('item-details.php?id=' . (int)$userAiMatches[0]['matches'][0]['found_id'])) ?>" class="btn btn-primary btn-sm">
                    Inspect &amp; Claim Item &rarr;
                </a>
                <a href="<?= e(url('my-reports.php')) ?>" class="btn btn-ghost btn-sm">
                    View All Matches (<?= count($userAiMatches) ?>)
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Hero Section with Modern Visual Hierarchy -->
<section class="hero">
    <div class="container hero__grid">
        <div class="hero__content">
            <div class="hero__badge">
                <svg viewBox="0 0 24 24" class="hero__badge-icon" aria-hidden="true"><use href="#i-spark"/></svg>
                <span>AI-Assisted Campus Recovery Platform</span>
            </div>

            <h1 class="hero__title">
                Lost Something on Campus? <span class="gradient-text">Let&rsquo;s Find It Together.</span>
            </h1>

            <p class="hero__lead">
                Report lost items, log found belongings, search campus records in real time, and let our smart algorithm suggest matching pairs automatically.
            </p>

            <form class="search-bar" action="<?= e(url('browse-items.php')) ?>" method="get" role="search">
                <div class="search-bar__input-wrap">
                    <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-search"/></svg>
                    <input id="q" name="q" type="search" placeholder="Search for keys, wallet, laptop, earbuds..." maxlength="100">
                </div>
                <select id="category" name="category" aria-label="Select Category">
                    <option value="">All Categories</option>
                    <?php foreach (CATEGORIES as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Search Items</button>
            </form>

            <div class="hero__cta-row">
                <a class="btn btn-primary btn-glow" href="<?= e(url('lost-item.php')) ?>">+ Report a Lost Item</a>
                <a class="btn btn-accent" href="<?= e(url('found-item.php')) ?>">+ Report a Found Item</a>
                <a class="btn btn-ghost" href="<?= e(url('browse-items.php')) ?>">Browse All Items &rarr;</a>
            </div>

            <div class="chips" aria-label="Quick category filters">
                <span class="chips__label">Popular:</span>
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
                    <a class="chip" href="<?= e(url('browse-items.php?category=' . urlencode($cat))) ?>">
                        <span class="chip__emoji"><?= $catIcons[$cat] ?? '🏷️' ?></span>
                        <span><?= e($cat) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AI Match Highlight Preview -->
        <div class="hero__visual">
            <?php if (false): ?>
<?php endif; ?>
<div class="hero__visual">
    <div class="home-card" style="padding:2rem; background:var(--primary-soft); border-radius:0.5rem; text-align:center;">
        <h3>Secure Your Belongings</h3>
        <p>Report lost or found items instantly. Our AI suggests matches for you.</p>
        <a href="<?= e(url('lost-item.php')) ?>" class="btn btn-primary btn-sm" style="margin:0.5rem;">Report Lost Item</a>
        <a href="<?= e(url('found-item.php')) ?>" class="btn btn-accent btn-sm" style="margin:0.5rem;">Report Found Item</a>
    </div>
</div>
        </div>
    </div>
</section>

<!-- Animated Campus Impact Statistics -->
<div class="container stats-container">
    <section class="stats" aria-label="Campus statistics">
        <div class="stat stat--lost">
            <div class="stat__icon-bubble">📦</div>
            <div class="stat__content">
                <div class="stat__value" data-count="<?= $stats['lost'] ?>">0</div>
                <p class="stat__label">Lost Items Reported</p>
            </div>
        </div>
        <div class="stat stat--found">
            <div class="stat__icon-bubble">🔍</div>
            <div class="stat__content">
                <div class="stat__value" data-count="<?= $stats['found'] ?>">0</div>
                <p class="stat__label">Found Items Logged</p>
            </div>
        </div>
        <div class="stat stat--returned">
            <div class="stat__icon-bubble">🎉</div>
            <div class="stat__content">
                <div class="stat__value" data-count="<?= $stats['returned'] ?>">0</div>
                <p class="stat__label">Returned to Owners</p>
            </div>
        </div>
    </section>
</div>

<!-- Core Features -->
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Smart Workflow</span>
            <h2>Everything you need to recover missing belongings</h2>
            <p>From the moment an item is lost or found to the secure verified handover at the administration office.</p>
        </div>
        <div class="feature-grid">
            <article class="feature">
                <div class="feature__icon feature__icon--purple"><svg aria-hidden="true"><use href="#i-plus"/></svg></div>
                <h3>1-Minute Fast Reporting</h3>
                <p>Describe your belongings, approximate location, date, and upload a photo directly from your phone or laptop.</p>
            </article>
            <article class="feature">
                <div class="feature__icon feature__icon--blue"><svg aria-hidden="true"><use href="#i-search"/></svg></div>
                <h3>Real-Time Live Search</h3>
                <p>Filter seamlessly by campus facility, category, color or date with instant AJAX results as you type.</p>
            </article>
            <article class="feature">
                <div class="feature__icon feature__icon--indigo"><svg aria-hidden="true"><use href="#i-spark"/></svg></div>
                <h3>AI Suggested Matching</h3>
                <p>Our intelligent engine scans keywords, descriptions, colours and dates to suggest high-confidence matches.</p>
            </article>
            <article class="feature">
                <div class="feature__icon feature__icon--green"><svg aria-hidden="true"><use href="#i-shield"/></svg></div>
                <h3>Verified &amp; Fraud-Free Claims</h3>
                <p>Secret identifying traits are kept hidden. Genuine owners prove ownership before collection from Room 102.</p>
            </article>
        </div>
    </div>
</section>

<!-- How It Works Step Progression -->
<section class="section section--mist">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">How It Works</span>
            <h2>Simple 4-Step Campus Process</h2>
        </div>
        <ol class="steps">
            <li>
                <div class="step-card">
                    <span class="step-num">01</span>
                    <h3>File a Report</h3>
                    <p>Post a lost report or log an item you found somewhere on campus.</p>
                </div>
            </li>
            <li>
                <div class="step-card">
                    <span class="step-num">02</span>
                    <h3>Smart Match</h3>
                    <p>The AI compares details and highlights possible matching pairs instantly.</p>
                </div>
            </li>
            <li>
                <div class="step-card">
                    <span class="step-num">03</span>
                    <h3>Submit Claim</h3>
                    <p>Provide private identifying details only the true owner would know.</p>
                </div>
            </li>
            <li>
                <div class="step-card">
                    <span class="step-num">04</span>
                    <h3>Collect Item</h3>
                    <p>Pick up your item from Admin Block Room 102 with your college ID card.</p>
                </div>
            </li>
        </ol>
    </div>
</section>

<!-- Latest Campus Listings Grid -->
<section class="section">
    <div class="container">
        <div class="section__row">
            <div>
                <span class="section__eyebrow">Recent Activity</span>
                <h2>Latest Campus Reports</h2>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('browse-items.php')) ?>">Browse All Listings &rarr;</a>
        </div>
        <?php if (!empty($recent)): ?>
            <div class="grid-cards">
                <?php foreach ($recent as $item) { echo item_card($item, true); } ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon-wrap">🔍</div>
                <h3>No reports logged yet</h3>
                <p>Be the first to report a lost or found item on campus.</p>
                <a class="btn btn-primary" href="<?= e(url('lost-item.php')) ?>">Report a Lost Item</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Modern Call to Action Band -->
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="cta-band">
            <div class="cta-band__content">
                <span class="cta-band__pill">✨ Be a Good Samaritan</span>
                <h2>Found someone&rsquo;s belongings on campus?</h2>
                <p>Taking 60 seconds to post it here gives a fellow student or faculty member peace of mind.</p>
            </div>
            <div class="cta-band__actions">
                <a class="btn btn-accent btn-lg" href="<?= e(url('found-item.php')) ?>">+ Report Found Item</a>
                <a class="btn btn-ghost-light btn-lg" href="<?= e(url('lost-item.php')) ?>">Report Lost Item</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
