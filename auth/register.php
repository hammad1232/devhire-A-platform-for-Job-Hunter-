<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['user_id'])) {
    $redirectPath = ($_SESSION['user_role'] ?? 'client') === 'developer' ? app_url('developer/dashboard.php') : app_url('user/dashboard.php');
    redirect($redirectPath);
}

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid registration request.');
        redirect(app_url('auth/register.php'));
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    $skills = trim($_POST['skills'] ?? '');
    $experience = (int) ($_POST['experience'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    $portfolioLinks = trim($_POST['portfolio_links'] ?? '');

    if ($name === '' || $email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please fill in all required fields with valid values.');
        redirect(app_url('auth/register.php'));
    }

    if (!in_array($role, ['client', 'developer'], true)) {
        set_flash('danger', 'Invalid account role.');
        redirect(app_url('auth/register.php'));
    }

    if ($role === 'developer' && ($skills === '' || $bio === '')) {
        set_flash('danger', 'Developer accounts require skills and bio.');
        redirect(app_url('auth/register.php'));
    }

    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    if ($existing->fetch()) {
        set_flash('warning', 'That email address is already registered.');
        redirect(app_url('auth/register.php'));
    }

    try {
        $pdo->beginTransaction();

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userStatement = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $userStatement->execute([$name, $email, $hashedPassword, $role]);
        $userId = (int) $pdo->lastInsertId();

        $gamification = $pdo->prepare('INSERT INTO gamification (user_id, points, level, badges) VALUES (?, 0, ?, ?)');
        $gamification->execute([$userId, readable_level(0), json_encode([])]);

        if ($role === 'developer') {
            $resumePath = '';
            if (!empty($_FILES['resume']['name'])) {
                $uploadDirectory = __DIR__ . '/../uploads/resumes';
                $resumePath = handle_resume_upload($_FILES['resume'], $uploadDirectory);
            }

            $profileStatement = $pdo->prepare('INSERT INTO developer_profiles (user_id, skills, experience, resume, bio, portfolio_links) VALUES (?, ?, ?, ?, ?, ?)');
            $profileStatement->execute([$userId, $skills, $experience, $resumePath, $bio, $portfolioLinks]);

            $reputationStatement = $pdo->prepare('INSERT INTO reputation_scores (developer_id, score) VALUES (?, 50)');
            $reputationStatement->execute([$userId]);
        }

        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['last_activity'] = time();

        set_flash('success', 'Account created successfully.');
        redirect($role === 'developer' ? app_url('developer/dashboard.php') : app_url('user/dashboard.php'));
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        set_flash('danger', 'Registration failed. Please try again.');
        redirect(app_url('auth/register.php'));
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 section-title mb-2">Create your DevHire account</h1>
                <p class="text-muted mb-4">Choose the role that fits your marketplace journey.</p>
                <form method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Type</label>
                            <select name="role" id="roleSelect" class="form-select" required>
                                <option value="client">I am a Client</option>
                                <option value="developer">I am a Developer</option>
                            </select>
                        </div>
                    </div>

                    <div id="developerFields" class="mt-4 d-none">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Skills</label>
                                <input type="text" name="skills" class="form-control" placeholder="PHP, Laravel, MySQL">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Experience (years)</label>
                                <input type="number" name="experience" class="form-control" min="0" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Resume (PDF)</label>
                                <input type="file" name="resume" class="form-control" accept="application/pdf">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bio</label>
                                <textarea name="bio" class="form-control" rows="4"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Portfolio Links</label>
                                <textarea name="portfolio_links" class="form-control" rows="3" placeholder="https://github.com/username, https://portfolio.example"></textarea>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary mt-4" type="submit">Create Account</button>

                    <div class="auth-divider auth-divider-end my-4">
                        <span>Or continue with</span>
                    </div>

                    <div class="social-auth-wrap social-auth-end">
                        <a class="btn btn-social btn-google w-100 social-auth-link" data-provider="google" href="<?php echo app_url('auth/social_auth.php?provider=google&action=start&role=client'); ?>">
                            <i class="bi bi-google"></i>
                            <span>Continue with Google</span>
                        </a>
                        <a class="btn btn-social btn-github w-100 social-auth-link" data-provider="github" href="<?php echo app_url('auth/social_auth.php?provider=github&action=start&role=client'); ?>">
                            <i class="bi bi-github"></i>
                            <span>Continue with GitHub</span>
                        </a>
                    </div>
                </form>
                <p class="mt-3 mb-0 small">Already registered? <a href="<?php echo app_url('auth/login.php'); ?>">Login here</a></p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('roleSelect');
    const developerFields = document.getElementById('developerFields');
    const socialLinks = document.querySelectorAll('.social-auth-link');

    function toggleDeveloperFields() {
        developerFields.classList.toggle('d-none', roleSelect.value !== 'developer');
    }

    function updateSocialLinks() {
        socialLinks.forEach(function (link) {
            const url = new URL(link.getAttribute('href'), window.location.origin);
            url.searchParams.set('action', 'start');
            url.searchParams.set('role', roleSelect.value);
            link.setAttribute('href', url.pathname + url.search);
        });
    }

    roleSelect.addEventListener('change', toggleDeveloperFields);
    roleSelect.addEventListener('change', updateSocialLinks);
    toggleDeveloperFields();
    updateSocialLinks();
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
