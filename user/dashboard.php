<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('client');

$pageTitle = 'Client Dashboard';
$currentUser = current_user($pdo);

$jobStatement = $pdo->prepare('SELECT j.id, j.title, j.budget, j.status, COUNT(p.id) AS proposal_count FROM jobs j LEFT JOIN proposals p ON p.job_id = j.id WHERE j.user_id = ? GROUP BY j.id ORDER BY j.created_at DESC');
$jobStatement->execute([$currentUser['id']]);
$jobs = $jobStatement->fetchAll();

$proposalStatement = $pdo->prepare('SELECT p.id, p.proposal_text, p.status, j.title, u.name AS developer_name FROM proposals p INNER JOIN jobs j ON j.id = p.job_id INNER JOIN users u ON u.id = p.developer_id WHERE j.user_id = ? ORDER BY p.created_at DESC LIMIT 5');
$proposalStatement->execute([$currentUser['id']]);
$recentProposals = $proposalStatement->fetchAll();

$developerStatement = $pdo->query('SELECT u.id, u.name, dp.skills, rs.score FROM users u INNER JOIN developer_profiles dp ON dp.user_id = u.id LEFT JOIN reputation_scores rs ON rs.developer_id = u.id WHERE u.role = "developer" ORDER BY COALESCE(rs.score, 0) DESC LIMIT 5');
$topDevelopers = $developerStatement->fetchAll();

$totalJobs = count($jobs);
$totalProposals = 0;
foreach ($jobs as $job) {
    $totalProposals += (int) $job['proposal_count'];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body"><p class="text-muted mb-1">Jobs posted</p><h2 class="mb-0"><?php echo $totalJobs; ?></h2></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><p class="text-muted mb-1">Total proposals</p><h2 class="mb-0"><?php echo $totalProposals; ?></h2></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><p class="text-muted mb-1">Top developers</p><h2 class="mb-0"><?php echo count($topDevelopers); ?></h2></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 section-title mb-0">Your jobs</h1>
            <a class="btn btn-primary btn-sm" href="<?php echo app_url('user/post_job.php'); ?>">Post Job</a>
        </div>
        <div class="row g-3">
            <?php foreach ($jobs as $job): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="h5"><?php echo e($job['title']); ?></h2>
                            <p class="small text-muted mb-2">Status: <?php echo e($job['status']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-primary">$<?php echo number_format((float) $job['budget'], 2); ?></span>
                                <span class="badge text-bg-secondary"><?php echo (int) $job['proposal_count']; ?> proposals</span>
                            </div>
                            <a class="btn btn-outline-primary btn-sm mt-3" href="<?php echo app_url('jobs/job_details.php?id=' . (int) $job['id']); ?>">View job</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$jobs): ?>
                <div class="col-12"><div class="alert alert-info">No jobs posted yet. Add your first role.</div></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Recent proposals</h2>
                <?php foreach ($recentProposals as $proposal): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="fw-semibold"><?php echo e($proposal['title']); ?></div>
                        <div class="small text-muted">From <?php echo e($proposal['developer_name']); ?></div>
                        <div class="small"><?php echo e(safe_trim_excerpt((string) $proposal['proposal_text'], 90)); ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentProposals): ?>
                    <p class="text-muted mb-0">No proposals yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Best developers</h2>
                <?php foreach ($topDevelopers as $developer): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <div class="fw-semibold"><?php echo e($developer['name']); ?></div>
                            <div class="small text-muted"><?php echo e($developer['skills']); ?></div>
                        </div>
                        <span class="badge bg-success"><?php echo number_format((float) ($developer['score'] ?? 0), 1); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
