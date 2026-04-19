<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'AI Job Enhancer';
$rawJobText = '';
$result = '';
$aiError = null;

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid AI request.');
        redirect(app_url('ai/job_enhance.php'));
    }

    $rawJobText = trim($_POST['raw_job_text'] ?? '');
    $result = gemini_chat_completion(
        'You are an AI Job Description Enhancer for a freelance/job platform. Convert raw, unstructured job notes into a clean, professional, and easy-to-read job post. STRICT RULES: Do NOT repeat the raw input. Fix grammar and spelling mistakes. Keep the meaning the same and do not add irrelevant details. Use simple English that is easy for beginners to understand. Keep the output well-structured and consistent. Avoid overly long paragraphs. Do NOT include explanations or extra commentary. OUTPUT FORMAT: Job Title: [Create a clear and relevant title] Location: [Extract if available, otherwise write "Remote"] Job Type: [Full-Time / Part-Time / Freelance / Not specified] Salary: [If mentioned, otherwise write "Negotiable"] Job Description: [Write 2–3 short sentences describing the role clearly] Responsibilities: - [Bullet point] - [Bullet point] - [Bullet point] Required Skills: - [Skill] - [Skill] - [Skill] Nice to Have (Optional): - [Optional skill if implied] TONE: Professional, clear and simple, client-ready, easy to scan. OUTPUT: Generate only the enhanced job description in the above format.',
        "Raw job notes:\n" . $rawJobText,
        360,
        $aiError
    );

    if ($result === '') {
        $result = "Job Title: Freelance Web Developer\nLocation: Remote\nJob Type: Freelance\nSalary: Negotiable\n\n---\n\nJob Description:\nWe are looking for a freelance web developer to build and improve web features for our project. The role involves turning business needs into a simple and functional web solution.\n\n---\n\nResponsibilities:\n- Build and update web pages or features\n- Fix issues and improve existing functionality\n- Work with the client to deliver clean results\n\n---\n\nRequired Skills:\n- PHP\n- JavaScript\n- MySQL\n\n---\n\nNice to Have (Optional):\n- Bootstrap\n- API integration\n- Responsive design";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body p-4">
                <h1 class="h4 section-title mb-2">AI Job Description Enhancer</h1>
                <p class="text-muted">Turn rough job notes into a clean, client-ready post.</p>
                <?php if ($aiError): ?>
                    <div class="alert alert-warning">AI status: <?php echo e($aiError); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Raw job text</label>
                        <textarea name="raw_job_text" class="form-control" rows="8" placeholder="Paste your rough job note here"><?php echo e($rawJobText); ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Enhance Job</button>
                </form>
            </div>
        </div>

        <?php if ($result !== ''): ?>
            <div class="card">
                <div class="card-body p-4">
                    <h2 class="h5">Enhanced output</h2>
                    <pre class="mb-0 text-wrap" style="white-space: pre-wrap;"><?php echo e($result); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
