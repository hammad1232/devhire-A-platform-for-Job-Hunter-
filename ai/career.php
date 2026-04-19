<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'AI Career Advisor';
$currentUser = current_user($pdo);
$skills = '';
$experience = '';
$bio = '';
$result = '';
$aiError = null;

if ($currentUser && $currentUser['role'] === 'developer') {
    $profileStatement = $pdo->prepare('SELECT skills, experience, bio FROM developer_profiles WHERE user_id = ? LIMIT 1');
    $profileStatement->execute([$currentUser['id']]);
    $profile = $profileStatement->fetch();
    if ($profile) {
        $skills = $profile['skills'];
        $experience = $profile['experience'];
        $bio = $profile['bio'];
    }
}

if (is_post()) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Invalid AI request.');
        redirect(app_url('ai/career.php'));
    }

    $skills = trim($_POST['skills'] ?? $skills);
    $experience = trim($_POST['experience'] ?? $experience);
    $bio = trim($_POST['bio'] ?? $bio);

    $prompt = 'Skills: ' . $skills . "\nExperience: " . $experience . "\nBio: " . $bio;
    $result = gemini_chat_completion(
        'You are an AI Career Advisor for a developer platform. Analyze the developer skills, experience in years, and bio, then provide clear, structured, and practical career guidance. STRICT RULES: Use simple and easy English. Keep the output clean and well-formatted. Do NOT write long paragraphs. Do NOT repeat the input. Be concise but helpful. Tailor the advice based on experience level. Focus on practical and actionable guidance. OUTPUT FORMAT: Career Summary: [2–3 short sentences describing the developer level and direction] Recommended Career Path: [Choose 1–2 suitable paths like Frontend, Backend, Full Stack, AI, etc.] Recommended Skills to Learn Next: - [Skill 1] - [Skill 2] - [Skill 3] - [Skill 4] Tools & Technologies: - [Tool/Framework] - [Tool/Framework] Next Steps: 1. [Actionable step] 2. [Actionable step] 3. [Actionable step] TONE: Supportive and practical, clear and beginner-friendly, professional but simple. OUTPUT: Generate only the structured career advice in the above format.',
        $prompt,
        320,
        $aiError
    );

    if ($result === '') {
        $skillList = $skills !== '' ? array_slice(array_values(array_filter(array_map('trim', explode(',', $skills)))), 0, 4) : ['Git', 'Problem solving', 'APIs', 'Testing'];
        $careerPath = 'Full Stack';
        if (stripos($skills, 'html') !== false || stripos($skills, 'css') !== false || stripos($skills, 'javascript') !== false) {
            $careerPath = 'Frontend';
        } elseif (stripos($skills, 'php') !== false || stripos($skills, 'mysql') !== false || stripos($skills, 'laravel') !== false) {
            $careerPath = 'Backend';
        }

        $result = "Career Summary:\nYou are building a solid foundation and can grow faster by focusing on one clear path. Keep improving your practical project work and show results in your portfolio.\n\n---\n\nRecommended Career Path:\n" . $careerPath . "\n\n---\n\nRecommended Skills to Learn Next:\n- " . ($skillList[0] ?? 'Git') . "\n- " . ($skillList[1] ?? 'APIs') . "\n- " . ($skillList[2] ?? 'Testing') . "\n- " . ($skillList[3] ?? 'Deployment') . "\n\n---\n\nTools & Technologies:\n- Git\n- VS Code\n\n---\n\nNext Steps:\n1. Build one small project that matches your career path.\n2. Add your work to a portfolio or GitHub profile.\n3. Learn one new tool and apply it in a real project.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body p-4">
                <h1 class="h4 section-title mb-2">AI Career Advisor</h1>
                <p class="text-muted">Get skill recommendations and career direction from your developer profile.</p>
                <?php if ($aiError): ?>
                    <div class="alert alert-warning">AI status: <?php echo e($aiError); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Skills</label>
                        <input type="text" name="skills" class="form-control" value="<?php echo e($skills); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Experience (years)</label>
                        <input type="number" name="experience" class="form-control" value="<?php echo e((string) $experience); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="4"><?php echo e($bio); ?></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Analyze Profile</button>
                </form>
            </div>
        </div>

        <?php if ($result !== ''): ?>
            <div class="card">
                <div class="card-body p-4">
                    <h2 class="h5">Results</h2>
                    <pre class="mb-0 text-wrap" style="white-space: pre-wrap;"><?php echo e($result); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
