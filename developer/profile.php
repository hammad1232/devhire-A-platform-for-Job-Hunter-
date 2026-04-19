<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('developer');

$currentUser = current_user($pdo);
$pageTitle = 'Developer Profile';

$profileStatement = $pdo->prepare('SELECT * FROM developer_profiles WHERE user_id = ? LIMIT 1');
$profileStatement->execute([$currentUser['id']]);
$profile = $profileStatement->fetch();

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid profile update request.');
        redirect(app_url('developer/profile.php'));
    }

    $skills = trim($_POST['skills'] ?? '');
    $experience = (int) ($_POST['experience'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    $portfolioLinks = trim($_POST['portfolio_links'] ?? '');

    if ($skills === '' || $bio === '') {
        set_flash('danger', 'Skills and bio are required.');
        redirect(app_url('developer/profile.php'));
    }

    $resumePath = $profile['resume'] ?? '';
    if (!empty($_FILES['resume']['name'])) {
        $resumePath = handle_resume_upload($_FILES['resume'], __DIR__ . '/../uploads/resumes');
    }

    if ($profile) {
        $updateStatement = $pdo->prepare('UPDATE developer_profiles SET skills = ?, experience = ?, resume = ?, bio = ?, portfolio_links = ? WHERE user_id = ?');
        $updateStatement->execute([$skills, $experience, $resumePath, $bio, $portfolioLinks, $currentUser['id']]);
    } else {
        $insertStatement = $pdo->prepare('INSERT INTO developer_profiles (user_id, skills, experience, resume, bio, portfolio_links) VALUES (?, ?, ?, ?, ?, ?)');
        $insertStatement->execute([$currentUser['id'], $skills, $experience, $resumePath, $bio, $portfolioLinks]);
    }

    $score = sync_reputation_score($pdo, $currentUser['id']);
    $pointsStatement = $pdo->prepare('SELECT points FROM gamification WHERE user_id = ? LIMIT 1');
    $pointsStatement->execute([$currentUser['id']]);
    $points = (int) ($pointsStatement->fetchColumn() ?: 0);
    $level = readable_level($points);
    $badges = badges_from_points($points);

    $gamificationUpdate = $pdo->prepare('UPDATE gamification SET level = ?, badges = ? WHERE user_id = ?');
    $gamificationUpdate->execute([$level, json_encode($badges), $currentUser['id']]);

    set_flash('success', 'Profile updated successfully. Reputation score: ' . number_format($score, 1));
    redirect(app_url('developer/profile.php'));
}

$gamificationStatement = $pdo->prepare('SELECT points, level, badges FROM gamification WHERE user_id = ? LIMIT 1');
$gamificationStatement->execute([$currentUser['id']]);
$gamification = $gamificationStatement->fetch() ?: ['points' => 0, 'level' => 'Beginner', 'badges' => '[]'];
$score = sync_reputation_score($pdo, $currentUser['id']);

$profile = $profile ?: ['skills' => '', 'experience' => 0, 'resume' => '', 'bio' => '', 'portfolio_links' => ''];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="h4 section-title mb-1">Edit profile</h1>
                        <p class="text-muted mb-0">Keep your skills, experience, and portfolio current.</p>
                    </div>
                    <div class="text-end">
                        <div class="metric-pill mb-2 d-inline-block">Score: <?php echo number_format($score, 1); ?></div><br>
                        <div class="small text-muted">Level: <?php echo e($gamification['level']); ?></div>
                    </div>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Skills</label>
                        <input type="text" name="skills" class="form-control" value="<?php echo e($profile['skills']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Experience (years)</label>
                        <input type="number" name="experience" class="form-control" min="0" value="<?php echo (int) $profile['experience']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Resume (PDF)</label>
                        <input type="file" name="resume" class="form-control" accept="application/pdf">
                        <?php if (!empty($profile['resume'])): ?>
                            <div class="small text-muted mt-2">Current file: <?php echo e($profile['resume']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="5" required><?php echo e($profile['bio']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Portfolio links</label>
                        <textarea name="portfolio_links" class="form-control" rows="3"><?php echo e($profile['portfolio_links']); ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
