<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('client');

$currentUser = current_user($pdo);

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid job submission.');
        redirect(app_url('user/post_job.php'));
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $budget = (float) ($_POST['budget'] ?? 0);

    if ($title === '' || $description === '' || $budget <= 0) {
        set_flash('danger', 'Please provide a valid title, description, and budget.');
        redirect(app_url('user/post_job.php'));
    }

    $statement = $pdo->prepare('INSERT INTO jobs (user_id, title, description, budget, status) VALUES (?, ?, ?, ?, "open")');
    $statement->execute([$currentUser['id'], $title, $description, $budget]);

    set_flash('success', 'Your job has been posted.');
    redirect(app_url('user/dashboard.php'));
}

$pageTitle = 'Post Job';
$jobsStatement = $pdo->prepare('SELECT id, title, budget, status FROM jobs WHERE user_id = ? ORDER BY created_at DESC');
$jobsStatement->execute([$currentUser['id']]);
$myJobs = $jobsStatement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body p-4">
                <h1 class="h4 section-title mb-3">Post a new job</h1>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Job title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="6" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Budget (USD)</label>
                        <input type="number" name="budget" class="form-control" min="1" step="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Publish Job</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h5">Your posted jobs</h2>
                <div class="list-group list-group-flush">
                    <?php foreach ($myJobs as $job): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?php echo e($job['title']); ?></div>
                                <div class="small text-muted">$<?php echo number_format((float) $job['budget'], 2); ?> - <?php echo e($job['status']); ?></div>
                            </div>
                            <a href="<?php echo app_url('jobs/job_details.php?id=' . (int) $job['id']); ?>" class="btn btn-outline-primary btn-sm">Open</a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$myJobs): ?>
                        <div class="list-group-item text-muted">No jobs yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
