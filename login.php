<?php
/**
 * login.php - Student and staff login (admins use admin/login.php)
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        // One generic message for wrong email or wrong password
        if (!$row || !password_verify($password, $row['password_hash'])) {
            $error = 'Email or password is incorrect.';
        } elseif ($row['role'] === 'admin') {
            $error = 'Administrators sign in from the Admin Login page.';
        } else {
            login_user($row);
            flash('success', 'Welcome back, ' . explode(' ', $row['name'])[0] . '.');
            redirect('index.php');
        }
    }
}

$pageTitle  = 'Log in';
$activePage = '';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <div class="form-card">
        <h1 style="font-size:2rem">Log in</h1>
        <p class="form-note" style="margin-top:0">Use your college email to report items and submit claims.</p>

        <?php if ($error): ?>
            <div class="flash flash-error" role="alert"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">College email</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       placeholder="name@cit.edu.in" value="<?= e($email) ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%">Log in</button>
        </form>
        <p class="form-note">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a>.</p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
