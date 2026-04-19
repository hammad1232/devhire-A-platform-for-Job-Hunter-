<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    session_write_close();
    session_start();
    set_flash('warning', 'Your session expired. Please sign in again.');
    redirect(app_url('auth/login.php'));
}

$_SESSION['last_activity'] = time();

function auth_user(PDO $pdo): ?array
{
    return current_user($pdo);
}
