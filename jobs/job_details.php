<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Job Details';
$jobId = (int) ($_GET['id'] ?? 0);

$jobStatement = $pdo->prepare('SELECT j.*, u.name AS client_name FROM jobs j INNER JOIN users u ON u.id = j.user_id WHERE j.id = ? LIMIT 1');
$jobStatement->execute([$jobId]);
$job = $jobStatement->fetch();

if (!$job) {
    http_response_code(404);
    die('Job not found.');
}

$currentUser = current_user($pdo);
$proposals = [];
$reviews = [];

if ($currentUser && $currentUser['role'] === 'client' && (int) $job['user_id'] === (int) $currentUser['id']) {
    if (is_post()) {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            set_flash('danger', 'Invalid request.');
            redirect(app_url('jobs/job_details.php?id=' . $jobId));
        }

        $action = $_POST['action'] ?? '';
        $developerId = (int) ($_POST['developer_id'] ?? 0);

        if ($action === 'hire' && $developerId > 0) {
            $proposalId = (int) ($_POST['proposal_id'] ?? 0);

            $updateProposal = $pdo->prepare('UPDATE proposals SET status = "hired" WHERE id = ? AND job_id = ?');
            $updateProposal->execute([$proposalId, $jobId]);

            $updateApplication = $pdo->prepare('UPDATE applications SET status = "completed" WHERE job_id = ? AND developer_id = ?');
            $updateApplication->execute([$jobId, $developerId]);

            award_gamification_points($pdo, $developerId, 10);
            sync_reputation_score($pdo, $developerId);

            set_flash('success', 'Developer hired successfully.');
            redirect(app_url('jobs/job_details.php?id=' . $jobId));
        }

        if ($action === 'review' && $developerId > 0) {
            $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
            $feedback = trim($_POST['feedback'] ?? '');

            $reviewStatement = $pdo->prepare('INSERT INTO reviews (developer_id, rating, feedback) VALUES (?, ?, ?)');
            $reviewStatement->execute([$developerId, $rating, $feedback]);

            award_gamification_points($pdo, $developerId, 5);
            sync_reputation_score($pdo, $developerId);

            set_flash('success', 'Review submitted.');
            redirect(app_url('jobs/job_details.php?id=' . $jobId));
        }
    }

    $proposalStatement = $pdo->prepare('SELECT p.id, p.proposal_text, p.status, u.name AS developer_name, u.id AS developer_id FROM proposals p INNER JOIN users u ON u.id = p.developer_id WHERE p.job_id = ? ORDER BY p.created_at DESC');
    $proposalStatement->execute([$jobId]);
    $proposals = $proposalStatement->fetchAll();

    $reviewStatement = $pdo->prepare('SELECT r.rating, r.feedback, u.name AS developer_name FROM reviews r INNER JOIN users u ON u.id = r.developer_id WHERE r.developer_id IN (SELECT developer_id FROM proposals WHERE job_id = ?) ORDER BY r.created_at DESC');
    $reviewStatement->execute([$jobId]);
    $reviews = $reviewStatement->fetchAll();
}

$developerProfile = null;
if ($currentUser && $currentUser['role'] === 'developer') {
    $profileStatement = $pdo->prepare('SELECT skills, experience FROM developer_profiles WHERE user_id = ? LIMIT 1');
    $profileStatement->execute([$currentUser['id']]);
    $developerProfile = $profileStatement->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="h3 section-title mb-2"><?php echo e($job['title']); ?></h1>
                        <p class="text-muted mb-0">Posted by <?php echo e($job['client_name']); ?></p>
                    </div>
                    <span class="badge text-bg-primary fs-6">$<?php echo number_format((float) $job['budget'], 2); ?></span>
                </div>
                <hr>
                <p><?php echo nl2br(e($job['description'])); ?></p>
                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <?php if ($currentUser && $currentUser['role'] === 'developer'): ?>
                        <a href="<?php echo app_url('developer/apply_job.php?id=' . $jobId); ?>" class="btn btn-primary">Apply Now</a>
                    <?php endif; ?>
                    <a href="<?php echo app_url('ai/job_enhance.php'); ?>" class="btn btn-outline-secondary">Enhance Similar Job</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($developerProfile): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5">Match summary</h2>
                    <p class="text-muted mb-1">Skills: <?php echo e($developerProfile['skills']); ?></p>
                    <p class="text-muted mb-0">Experience: <?php echo (int) $developerProfile['experience']; ?> years</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($proposals): ?>
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Proposals</h2>
                    <?php foreach ($proposals as $proposal): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="fw-semibold"><?php echo e($proposal['developer_name']); ?></div>
                            <div class="small text-muted"><?php echo e($proposal['status']); ?></div>
                            <p class="small mb-2"><?php echo e(mb_strimwidth($proposal['proposal_text'], 0, 110, '...')); ?></p>
                            <form method="post" class="d-flex gap-2 flex-wrap">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="hire">
                                <input type="hidden" name="proposal_id" value="<?php echo (int) $proposal['id']; ?>">
                                <input type="hidden" name="developer_id" value="<?php echo (int) $proposal['developer_id']; ?>">
                                <button class="btn btn-sm btn-primary" type="submit">Hire Developer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h2 class="h5">Leave a review</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="review">
                        <input type="hidden" name="developer_id" value="<?php echo (int) ($proposals[0]['developer_id'] ?? 0); ?>">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select">
                                <option value="5">5</option>
                                <option value="4">4</option>
                                <option value="3">3</option>
                                <option value="2">2</option>
                                <option value="1">1</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Feedback</label>
                            <textarea name="feedback" class="form-control" rows="4"></textarea>
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Submit Review</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($reviews): ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h2 class="h5">Reviews</h2>
                    <?php foreach ($reviews as $review): ?>
                        <div class="border-bottom py-2">
                            <div class="fw-semibold"><?php echo e($review['developer_name']); ?> - <?php echo (int) $review['rating']; ?>/5</div>
                            <div class="small text-muted"><?php echo e($review['feedback']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
