<?php
$config = app_config();

$host = $config['database']['host'] ?? 'sql111.infinityfree.com';
$database = $config['database']['name'] ?? 'if0_41699671_app_devhire';
$username = $config['database']['user'] ?? 'if0_41699671';
$password = $config['database']['pass'] ?? '2IZyGR6tNp3fyB';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $pdoOptions);
} catch (PDOException $exception) {
    http_response_code(500);
    die('Database connection failed. Please verify your database settings.');
}
