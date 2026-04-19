<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user($pdo);
$pageTitle = $pageTitle ?? 'DevHire';
$flash = get_flash();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?> | DevHire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/webproject2/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo app_url('index.php'); ?>">DevHire</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?php echo app_url('jobs/job_list.php'); ?>">Jobs</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo app_url('ai/career.php'); ?>">AI Tools</a></li>
                <?php if ($currentUser && $currentUser['role'] === 'client'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('user/dashboard.php'); ?>">Client Dashboard</a></li>
                <?php endif; ?>
                <?php if ($currentUser && $currentUser['role'] === 'developer'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('developer/dashboard.php'); ?>">Developer Dashboard</a></li>
                <?php endif; ?>
                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo app_url('admin/dashboard.php'); ?>">Admin Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-outline-light btn-sm" id="themeToggle" type="button">Dark Mode</button>
                <?php if ($currentUser): ?>
                    <span class="text-white small d-none d-md-inline">Hi, <?php echo e($currentUser['name']); ?></span>
                    <a class="btn btn-light btn-sm" href="<?php echo app_url('auth/logout.php'); ?>">Logout</a>
                <?php else: ?>
                    <a class="btn btn-outline-light btn-sm" href="<?php echo app_url('auth/login.php'); ?>">Login</a>
                    <a class="btn btn-light btn-sm" href="<?php echo app_url('auth/register.php'); ?>">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="py-4">
    <div class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo e($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
