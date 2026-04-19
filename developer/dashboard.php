<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('developer');

$currentUser = current_user($pdo);
$pageTitle = 'Developer Dashboard';

$profileStatement = $pdo->prepare('SELECT * FROM developer_profiles WHERE user_id = ? LIMIT 1');
$profileStatement->execute([$currentUser['id']]);
$profile = $profileStatement->fetch() ?: ['skills' => '', 'experience' => 0, 'bio' => '', 'portfolio_links' => ''];

$gamificationStatement = $pdo->prepare('SELECT points, level, badges FROM gamification WHERE user_id = ? LIMIT 1');
$gamificationStatement->execute([$currentUser['id']]);
$gamification = $gamificationStatement->fetch() ?: ['points' => 0, 'level' => 'Beginner', 'badges' => '[]'];

$score = sync_reputation_score($pdo, $currentUser['id']);

$applicationsStatement = $pdo->prepare('SELECT a.status, a.created_at, j.title FROM applications a INNER JOIN jobs j ON j.id = a.job_id WHERE a.developer_id = ? ORDER BY a.created_at DESC LIMIT 5');
$applicationsStatement->execute([$currentUser['id']]);
$applications = $applicationsStatement->fetchAll();

$skills = normalize_skills($profile['skills'] ?? '');
$jobsStatement = $pdo->query('SELECT id, title, description, budget FROM jobs WHERE status = "open" ORDER BY created_at DESC');
$availableJobs = $jobsStatement->fetchAll();
$recommendedJobs = [];

foreach ($availableJobs as $job) {
    $jobSkills = normalize_skills($job['title'] . ', ' . $job['description']);
    $job['match_score'] = skill_match_score($skills, $jobSkills);
    if ($job['match_score'] > 0) {
        $recommendedJobs[] = $job;
    }
}

usort($recommendedJobs, fn ($left, $right) => $right['match_score'] <=> $left['match_score']);

$careerSuggestions = !empty($skills)
    ? 'Suggested next skills: ' . implode(', ', array_slice(array_diff(['git', 'testing', 'api integration', 'cloud deployment', 'ui systems', 'security'], $skills), 0, 3))
    : 'Add your skills to unlock a more tailored career plan.';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Points</p><h2 class="mb-0"><?php echo (int) $gamification['points']; ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Level</p><h2 class="mb-0"><?php echo e($gamification['level']); ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Score</p><h2 class="mb-0"><?php echo number_format($score, 1); ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Applications</p><h2 class="mb-0"><?php echo count($applications); ?></h2></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body p-4">
                <h1 class="h4 section-title mb-3">Your profile</h1>
                <p class="mb-1"><strong>Skills:</strong> <?php echo e($profile['skills']); ?></p>
                <p class="mb-1"><strong>Experience:</strong> <?php echo (int) $profile['experience']; ?> years</p>
                <p class="mb-1"><strong>Bio:</strong> <?php echo e($profile['bio']); ?></p>
                <p class="mb-0"><strong>Portfolio:</strong> <?php echo e($profile['portfolio_links']); ?></p>
                <a class="btn btn-outline-primary mt-3" href="<?php echo app_url('developer/profile.php'); ?>">Edit profile</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Recommended jobs</h2>
                    <a href="<?php echo app_url('jobs/job_list.php'); ?>" class="small">Browse all</a>
                </div>
                <div class="row g-3">
                    <?php foreach (array_slice($recommendedJobs, 0, 4) as $job): ?>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong><?php echo e($job['title']); ?></strong>
                                    <span class="badge bg-success"><?php echo number_format((float) $job['match_score'], 0); ?>%</span>
                                </div>
                                <p class="small text-muted"><?php echo e(mb_strimwidth($job['description'], 0, 95, '...')); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-primary">$<?php echo number_format((float) $job['budget'], 2); ?></span>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo app_url('developer/apply_job.php?id=' . (int) $job['id']); ?>">Apply</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recommendedJobs): ?>
                        <div class="col-12"><div class="alert alert-info">No matching jobs yet. Update your skills to improve recommendations.</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">AI career snapshot</h2>
                <p class="text-muted small"><?php echo e($careerSuggestions); ?></p>
                <a class="btn btn-primary btn-sm" href="<?php echo app_url('ai/career.php'); ?>">Open Career Advisor</a>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Recent applications</h2>
                <?php foreach ($applications as $application): ?>
                    <div class="border-bottom py-2">
                        <div class="fw-semibold"><?php echo e($application['title']); ?></div>
                        <div class="small text-muted"><?php echo e($application['status']); ?> - <?php echo e($application['created_at']); ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$applications): ?>
                    <p class="text-muted mb-0">No applications yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
