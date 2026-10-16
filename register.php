<?php
/**
 * register.php - Create a student account (role is always 'student'; admins are created in the database).
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']  = trim((string)($_POST['name'] ?? ''));
    $old['email'] = trim((string)($_POST['email'] ?? ''));
    $password     = (string)($_POST['password'] ?? '');
    $confirm      = (string)($_POST['password_confirm'] ?? '');

    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) {
        $errors[] = 'Enter your full name (2 to 100 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Try logging in.';
        } else {
            $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $ins->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), 'student']);
            flash('success', 'Account created. You can log in now.');
            redirect('login.php');
        }
    }
}

$pageTitle  = 'Create account';
$activePage = '';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <div class="form-card">
        <h1 style="font-size:2rem">Create account</h1>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error" role="alert"><span><?= e($err) ?></span></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" style="margin-top:1rem">
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" autocomplete="name" required minlength="2" maxlength="100"
                       placeholder="Your full name" value="<?= e($old['name']) ?>">
            </div>
            <div class="field">
                <label for="email">College email</label>
                <input id="email" name="email" type="email" autocomplete="email" required maxlength="150"
                       placeholder="name@cit.edu.in" value="<?= e($old['email']) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8"
                       placeholder="At least 8 characters">
            </div>
            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="8">
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%">Create account</button>
        </form>
        <p class="form-note">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a>.</p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
