<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Job Listings';
$search = trim($_GET['q'] ?? '');
$currentUser = current_user($pdo);
$developerSkills = [];

if ($currentUser && $currentUser['role'] === 'developer') {
    $profileStatement = $pdo->prepare('SELECT skills FROM developer_profiles WHERE user_id = ? LIMIT 1');
    $profileStatement->execute([$currentUser['id']]);
    $profile = $profileStatement->fetch();
    if ($profile) {
        $developerSkills = normalize_skills($profile['skills']);
    }
}

$sql = 'SELECT id, title, description, budget, created_at FROM jobs WHERE status = "open"';
$params = [];
if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR description LIKE ?)';
    $likeSearch = '%' . $search . '%';
    $params = [$likeSearch, $likeSearch];
}
$sql .= ' ORDER BY created_at DESC';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$jobs = $statement->fetchAll();

$recommendedJobs = [];
if ($developerSkills) {
    foreach ($jobs as $job) {
        $keywords = normalize_skills($job['title'] . ', ' . $job['description']);
        $score = skill_match_score($developerSkills, $keywords);
        $job['match_score'] = $score;
        if ($score > 0) {
            $recommendedJobs[] = $job;
        }
    }

    usort($recommendedJobs, fn ($left, $right) => $right['match_score'] <=> $left['match_score']);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 section-title mb-1">Browse jobs</h1>
        <p class="text-muted mb-0">Search open roles and find relevant matches.</p>
    </div>
    <form class="d-flex gap-2" method="get">
        <input type="search" name="q" class="form-control" placeholder="Search jobs" value="<?php echo e($search); ?>">
        <button class="btn btn-primary">Search</button>
    </form>
</div>

<?php if ($recommendedJobs): ?>
    <div class="mb-5">
        <h2 class="h5 section-title mb-3">Recommended jobs</h2>
        <div class="row g-4">
            <?php foreach (array_slice($recommendedJobs, 0, 3) as $job): ?>
                <div class="col-md-4" data-searchable>
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h3 class="h5 mb-0"><?php echo e($job['title']); ?></h3>
                                <span class="badge bg-success"><?php echo number_format((float) $job['match_score'], 0); ?>% match</span>
                            </div>
                            <p class="small text-muted"><?php echo e(safe_trim_excerpt((string) $job['description'], 120)); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-primary">$<?php echo number_format((float) $job['budget'], 2); ?></span>
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo app_url('jobs/job_details.php?id=' . (int) $job['id']); ?>">Open</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($jobs as $job): ?>
        <div class="col-lg-4 col-md-6" data-searchable>
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h5 mb-0"><?php echo e($job['title']); ?></h2>
                        <?php if (isset($job['match_score'])): ?>
                            <span class="badge bg-success"><?php echo number_format((float) $job['match_score'], 0); ?>% match</span>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted"><?php echo e(safe_trim_excerpt((string) $job['description'], 130)); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-primary">$<?php echo number_format((float) $job['budget'], 2); ?></span>
                        <a class="btn btn-outline-primary btn-sm" href="<?php echo app_url('jobs/job_details.php?id=' . (int) $job['id']); ?>">Details</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$jobs): ?>
        <div class="col-12"><div class="alert alert-info">No jobs found.</div></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
