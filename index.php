<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Home';

$jobCount = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
$developerCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'developer'")->fetchColumn();
$clientCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
$recentJobs = $pdo->query('SELECT id, title, budget, description FROM jobs ORDER BY created_at DESC LIMIT 3')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="hero-shell p-4 p-md-5 mb-5">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge badge-soft rounded-pill mb-3">Developer Hiring Marketplace</span>
            <h1 class="display-5 fw-bold section-title">Hire stronger teams, or get hired by better projects.</h1>
            <p class="lead text-muted mt-3">DevHire connects clients and developers with role-based dashboards, AI-assisted workflows, secure onboarding, and reputation-driven matching.</p>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="<?php echo app_url('auth/register.php'); ?>" class="btn btn-primary btn-lg">Get Started</a>
                <a href="<?php echo app_url('jobs/job_list.php'); ?>" class="btn btn-outline-primary btn-lg">Browse Jobs</a>
                <button class="btn btn-outline-secondary btn-lg" data-bs-toggle="modal" data-bs-target="#aiQuickModal">AI Tools</button>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card rounded-4 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <p class="text-muted mb-1">Marketplace snapshot</p>
                            <h2 class="h4 mb-0">Live platform metrics</h2>
                        </div>
                        <i class="bi bi-graph-up-arrow fs-2 text-primary"></i>
                    </div>
                    <div class="row g-3 text-center">
                        <div class="col-4"><div class="metric-pill d-inline-block w-100"><?php echo $jobCount; ?> Jobs</div></div>
                        <div class="col-4"><div class="metric-pill d-inline-block w-100"><?php echo $developerCount; ?> Developers</div></div>
                        <div class="col-4"><div class="metric-pill d-inline-block w-100"><?php echo $clientCount; ?> Clients</div></div>
                    </div>
                    <hr>
                    <p class="small text-muted mb-0">Built for shared hosting, with PHP backend logic and a Bootstrap frontend that can also be previewed as static pages where needed.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3 class="h5">Client workflow</h3>
                    <p class="text-muted">Post jobs, review proposals, and hire developers from a single dashboard.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3 class="h5">Developer workflow</h3>
                    <p class="text-muted">Build a profile, apply to jobs, and use AI tools to sharpen proposals and career direction.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h3 class="h5">Trust layer</h3>
                    <p class="text-muted">Gamification and reputation scores help highlight reliable contributors and serious talent.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 section-title mb-0">Recent jobs</h2>
        <a href="<?php echo app_url('jobs/job_list.php'); ?>" class="small text-decoration-none">View all</a>
    </div>
    <div class="row g-4">
        <?php foreach ($recentJobs as $job): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h5"><?php echo e($job['title']); ?></h3>
                        <p class="text-muted small"><?php echo e(safe_trim_excerpt((string) $job['description'], 110)); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-primary">$<?php echo number_format((float) $job['budget'], 2); ?></span>
                            <a href="<?php echo app_url('jobs/job_details.php?id=' . (int) $job['id']); ?>" class="btn btn-sm btn-outline-primary">Details</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$recentJobs): ?>
            <div class="col-12">
                <div class="alert alert-info">No jobs posted yet. Be the first to add one.</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="modal fade" id="aiQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI Quick Start</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Use the AI tools to improve careers, job posts, and proposals.</p>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary" href="<?php echo app_url('ai/career.php'); ?>">Career Advisor</a>
                    <a class="btn btn-outline-primary" href="<?php echo app_url('ai/job_enhance.php'); ?>">Job Enhancer</a>
                    <a class="btn btn-outline-primary" href="<?php echo app_url('ai/proposal.php'); ?>">Proposal Generator</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
