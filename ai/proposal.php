<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'AI Proposal Generator';
$currentUser = current_user($pdo);
$proposalText = '';
$result = '';
$aiError = null;
$developerName = $currentUser['name'] ?? 'Developer';
$developerEmail = $currentUser['email'] ?? 'your-email@example.com';

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid AI request.');
        redirect(app_url('ai/proposal.php'));
    }

    $proposalText = trim($_POST['proposal_text'] ?? '');

    $result = gemini_chat_completion(
        'You are an AI Proposal Simplifier for a freelance platform. Your job is to take a messy or AI-generated proposal and rewrite it into a CLEAN, SIMPLE, HUMAN-READABLE proposal. CRITICAL RULES: You MUST NOT copy or reuse sentences from the input. You MUST completely rewrite the content in your own simple words. REMOVE all AI filler like "Okay here is", "I have developed", "aligned perfectly", and similar lines. DO NOT sound like an AI assistant. DO NOT be repetitive. DO NOT mention attachments or extra documents. Keep language natural like a real freelancer writing to a client. LENGTH RULE: Maximum 100-130 words total. STYLE: Simple English, human tone, short sentences, clear and direct. OUTPUT FORMAT (STRICT): Subject: SEO Optimization Proposal Dear Client, [Write 2-3 short sentences introducing interest in the job] [Write 2-3 short sentences explaining relevant skills in simple words] [Write 2-3 short sentences explaining what actions will be done (SEO tasks)] [Write 1-2 short sentences closing confidently] Best regards, [Your Name]. OUTPUT: Return ONLY the final rewritten proposal.',
        "INPUT:\n" . $proposalText . "\n\nYour Name: " . $developerName . "\nEmail: " . $developerEmail,
        220,
        $aiError
    );

    if ($result !== '') {
        $result = clean_proposal_output($result, $developerName);
    }

    if ($result === '') {
        $result = "Subject: SEO Optimization Proposal\n\nDear Client,\n\nI am interested in your SEO project and I would like to support your goals. I can help improve search ranking and bring more relevant traffic to your site.\n\nI have practical SEO experience and I focus on clear results. I work with simple strategies that improve visibility and user experience over time.\n\nI will review your current pages, improve keywords, and fix technical SEO issues. I will also optimize metadata, content structure, and page performance.\n\nI am confident I can deliver measurable progress. I would be glad to discuss your project details.\n\nBest regards,\n" . $developerName;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card mb-4">
            <div class="card-body p-4">
                <h1 class="h4 section-title mb-2">Smart Proposal Generator</h1>
                <p class="text-muted">Rewrite and simplify a proposal into a client-ready format.</p>
                <?php if ($aiError): ?>
                    <div class="alert alert-warning">AI status: <?php echo e($aiError); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Proposal text</label>
                        <textarea name="proposal_text" class="form-control" rows="8" required><?php echo e($proposalText); ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Generate Proposal</button>
                </form>
            </div>
        </div>

        <?php if ($result !== ''): ?>
            <div class="card">
                <div class="card-body p-4">
                    <h2 class="h5">Generated proposal</h2>
                    <pre class="mb-0 text-wrap" style="white-space: pre-wrap;"><?php echo e($result); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
