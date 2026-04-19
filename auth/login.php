<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['user_id'])) {
    $redirectRole = $_SESSION['user_role'] ?? 'client';
    $redirectPath = $redirectRole === 'developer'
        ? app_url('developer/dashboard.php')
        : ($redirectRole === 'admin' ? app_url('admin/dashboard.php') : app_url('user/dashboard.php'));
    redirect($redirectPath);
}

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid login request.');
        redirect(app_url('auth/login.php'));
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        set_flash('success', 'Welcome back, ' . $user['name'] . '.');

        $target = $user['role'] === 'developer'
            ? app_url('developer/dashboard.php')
            : ($user['role'] === 'admin' ? app_url('admin/dashboard.php') : app_url('user/dashboard.php'));
        redirect($target);
    }

    set_flash('danger', 'Invalid email or password.');
    redirect(app_url('auth/login.php'));
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-5 col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 section-title mb-2">Sign in</h1>
                <p class="text-muted mb-4">Access your DevHire dashboard.</p>
                <div class="social-auth-wrap mb-4">
                    <a class="btn btn-social btn-google w-100 mb-2 social-auth-link" data-provider="google" href="<?php echo app_url('auth/social_auth.php?provider=google&action=start&role=client'); ?>">
                        <i class="bi bi-google me-2"></i>
                        Continue with Google
                    </a>
                    <a class="btn btn-social btn-github w-100 social-auth-link" data-provider="github" href="<?php echo app_url('auth/social_auth.php?provider=github&action=start&role=client'); ?>">
                        <i class="bi bi-github me-2"></i>
                        Continue with GitHub
                    </a>
                </div>

                <div class="auth-divider mb-4">
                    <span>or sign in with email</span>
                </div>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
                <p class="mt-3 mb-0 small">New here? <a href="<?php echo app_url('auth/register.php'); ?>">Create an account</a></p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const socialLinks = document.querySelectorAll('.social-auth-link');

    socialLinks.forEach(function (link) {
        const url = new URL(link.getAttribute('href'), window.location.origin);
        link.setAttribute('href', url.pathname + url.search);
    });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
