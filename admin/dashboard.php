<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();

$pageTitle = 'Admin Dashboard';
$currentUser = current_user($pdo);

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$clientCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
$developerCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'developer'")->fetchColumn();
$jobCount = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
$proposalCount = (int) $pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn();
$applicationCount = (int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();

$recentUsers = $pdo->query('SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 8')->fetchAll();
$recentJobs = $pdo->query('SELECT title, budget, status, created_at FROM jobs ORDER BY created_at DESC LIMIT 8')->fetchAll();
$topDevelopers = $pdo->query('SELECT u.name, u.email, COALESCE(rs.score, 50) AS score FROM users u LEFT JOIN reputation_scores rs ON rs.developer_id = u.id WHERE u.role = "developer" ORDER BY COALESCE(rs.score, 50) DESC LIMIT 5')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Users</p><h2 class="mb-0"><?php echo $userCount; ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Clients</p><h2 class="mb-0"><?php echo $clientCount; ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Developers</p><h2 class="mb-0"><?php echo $developerCount; ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Jobs</p><h2 class="mb-0"><?php echo $jobCount; ?></h2></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h4 section-title mb-0">Recent users</h1>
                    <span class="badge bg-primary"><?php echo $proposalCount; ?> proposals</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo e($user['name']); ?></td>
                                    <td><?php echo e($user['email']); ?></td>
                                    <td><span class="badge text-bg-secondary"><?php echo e($user['role']); ?></span></td>
                                    <td><?php echo e($user['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h5">Recent jobs</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentJobs as $job): ?>
                                <tr>
                                    <td><?php echo e($job['title']); ?></td>
                                    <td>$<?php echo number_format((float) $job['budget'], 2); ?></td>
                                    <td><?php echo e($job['status']); ?></td>
                                    <td><?php echo e($job['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Top developers</h2>
                <?php foreach ($topDevelopers as $developer): ?>
                    <div class="border-bottom py-2">
                        <div class="fw-semibold"><?php echo e($developer['name']); ?></div>
                        <div class="small text-muted"><?php echo e($developer['email']); ?></div>
                        <div class="small">Score: <?php echo number_format((float) $developer['score'], 1); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Marketplace health</h2>
                <p class="mb-1">Applications: <?php echo $applicationCount; ?></p>
                <p class="mb-2">Current session: <?php echo e($currentUser['name']); ?></p>
                <a class="btn btn-primary btn-sm" href="<?php echo app_url('admin/table_manager.php'); ?>">Manage All Tables (CRUD)</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
