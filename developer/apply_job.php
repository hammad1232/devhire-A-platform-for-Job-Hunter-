<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_role('developer');

$currentUser = current_user($pdo);
$pageTitle = 'Apply to Job';
$jobId = (int) ($_GET['id'] ?? $_POST['job_id'] ?? 0);
$aiError = null;

$jobStatement = $pdo->prepare('SELECT id, title, description, budget FROM jobs WHERE id = ? LIMIT 1');
$jobStatement->execute([$jobId]);
$job = $jobStatement->fetch();

if (!$job) {
    http_response_code(404);
    die('Job not found.');
}

$profileStatement = $pdo->prepare('SELECT skills, experience, bio FROM developer_profiles WHERE user_id = ? LIMIT 1');
$profileStatement->execute([$currentUser['id']]);
$profile = $profileStatement->fetch() ?: ['skills' => '', 'experience' => 0, 'bio' => ''];

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid application request.');
        redirect(app_url('developer/apply_job.php?id=' . $jobId));
    }

    $coverLetter = trim($_POST['cover_letter'] ?? '');
    if ($coverLetter === '') {
        set_flash('danger', 'Cover letter is required.');
        redirect(app_url('developer/apply_job.php?id=' . $jobId));
    }

    $existing = $pdo->prepare('SELECT id FROM applications WHERE job_id = ? AND developer_id = ? LIMIT 1');
    $existing->execute([$jobId, $currentUser['id']]);
    if ($existing->fetch()) {
        set_flash('warning', 'You have already applied to this job.');
        redirect(app_url('jobs/job_details.php?id=' . $jobId));
    }

    $applicationStatement = $pdo->prepare('INSERT INTO applications (job_id, developer_id, cover_letter) VALUES (?, ?, ?)');
    $applicationStatement->execute([$jobId, $currentUser['id'], $coverLetter]);

    $proposalStatement = $pdo->prepare('INSERT INTO proposals (job_id, developer_id, proposal_text) VALUES (?, ?, ?)');
    $proposalStatement->execute([$jobId, $currentUser['id'], $coverLetter]);

    set_flash('success', 'Your application has been submitted.');
    redirect(app_url('developer/dashboard.php'));
}

$aiSuggestion = gemini_chat_completion(
    'You are a senior freelance proposal writer.',
    'Write a short, professional proposal for this job using the developer profile below. Job: ' . $job['title'] . '. Description: ' . $job['description'] . '. Developer profile: ' . json_encode($profile),
    220,
    $aiError
);

if ($aiSuggestion !== '') {
    $aiSuggestion = clean_proposal_output($aiSuggestion, $currentUser['name']);
}

if ($aiSuggestion === '') {
    $aiSuggestion = 'Hello, I would like to apply for this project. My background aligns with the listed requirements, and I can start quickly, communicate clearly, and deliver high-quality work on time.';
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 section-title mb-2">Apply for <?php echo e($job['title']); ?></h1>
                <p class="text-muted">Budget: $<?php echo number_format((float) $job['budget'], 2); ?></p>
                <?php if ($aiError): ?>
                    <div class="alert alert-warning">AI status: <?php echo e($aiError); ?></div>
                <?php endif; ?>
                <div class="alert alert-info">
                    <strong>AI draft:</strong> <?php echo e($aiSuggestion); ?>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="job_id" value="<?php echo (int) $jobId; ?>">
                    <div class="mb-3">
                        <label class="form-label">Cover letter / proposal</label>
                        <textarea name="cover_letter" class="form-control" rows="8" required><?php echo e($aiSuggestion); ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Submit Application</button>
                    <a class="btn btn-outline-secondary" href="<?php echo app_url('jobs/job_details.php?id=' . $jobId); ?>">Back to job</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
