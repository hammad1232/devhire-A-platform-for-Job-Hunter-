<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $loadedConfig = require __DIR__ . '/../config.php';
        $config = is_array($loadedConfig) ? $loadedConfig : [];
    }

    if ($key === null || $key === '') {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function app_base_path(): string
{
    $basePath = app_config('app.base_path', '/webproject2');
    if ($basePath === false || $basePath === '') {
        $basePath = '/webproject2';
    }

    return '/' . trim($basePath, '/');
}

function app_url(string $path = ''): string
{
    $basePath = rtrim(app_base_path(), '/');
    $trimmedPath = ltrim($path, '/');

    if ($trimmedPath === '') {
        return $basePath;
    }

    return $basePath . '/' . $trimmedPath;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function normalize_skills(string $skills): array
{
    $items = array_filter(array_map('trim', explode(',', strtolower($skills))));
    return array_values(array_unique($items));
}

function readable_level(int $points): string
{
    if ($points >= 151) {
        return 'Pro';
    }

    if ($points >= 51) {
        return 'Intermediate';
    }

    return 'Beginner';
}

function level_from_score(float $score): string
{
    if ($score >= 85) {
        return 'Top Rated';
    }

    if ($score >= 70) {
        return 'Strong';
    }

    if ($score >= 50) {
        return 'Growing';
    }

    return 'Emerging';
}

function skill_match_score(array $a, array $b): float
{
    if (!$a || !$b) {
        return 0.0;
    }

    $hits = array_intersect($a, $b);
    $overlap = count($hits) / max(1, count(array_unique($b)));
    $joinedA = implode(', ', $a);
    $joinedB = implode(', ', $b);
    $similarity = 0.0;
    similar_text($joinedA, $joinedB, $similarity);

    return round(($overlap * 70) + (($similarity / 100) * 30), 2);
}

function config_value(string $key, string $default = ''): string
{
    $value = app_config($key, $default);

    if ($value === false || $value === '' || $value === null) {
        return $default;
    }

    return (string) $value;
}

function gemini_chat_completion(string $systemPrompt, string $userPrompt, int $maxTokens = 500, ?string &$errorMessage = null): string
{
    $errorMessage = null;
    $apiKey = config_value('services.gemini.api_key');
    if (!$apiKey || !function_exists('curl_init')) {
        $errorMessage = !$apiKey ? 'Gemini API key is missing in config.php.' : 'PHP cURL extension is not available.';
        return '';
    }

    $primaryModel = config_value('services.gemini.model', 'gemma-3-1b-it');
    $fallbackModels = array_values(array_filter(array_map('trim', explode(',', config_value('services.gemini.fallback_models', 'gemma-3-4b-it')))));
    $modelsToTry = array_values(array_unique(array_merge([$primaryModel], $fallbackModels)));
    $baseUrl = rtrim(config_value('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    $lastError = 'Gemini response did not include generated content.';

    foreach ($modelsToTry as $model) {
        $isGemmaModel = str_starts_with(strtolower($model), 'gemma-');
        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $isGemmaModel
                        ? "System instructions:\n" . $systemPrompt . "\n\nUser request:\n" . $userPrompt
                        : $userPrompt],
                ],
            ],
        ];

        $payloadData = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if (!$isGemmaModel) {
            $payloadData['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        $payload = json_encode($payloadData);

        $ch = curl_init($baseUrl . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $lastError = 'Gemini request failed: ' . curl_error($ch);
            curl_close($ch);
            continue;
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            $decodedResponse = json_decode($response, true);
            $lastError = $decodedResponse['error']['message'] ?? ('Gemini returned HTTP ' . $statusCode . ' for model ' . $model . '.');
            continue;
        }

        $data = json_decode($response, true);
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $content = '';

        foreach ($parts as $part) {
            $content .= (string) ($part['text'] ?? '');
        }

        $content = trim($content);

        if ($content !== '') {
            return $content;
        }

        $lastError = 'Gemini response did not include generated content for model ' . $model . '.';
    }

    $errorMessage = $lastError;
    return '';
}

function clean_proposal_output(string $text, string $developerName): string
{
    $cleaned = trim($text);
    if ($cleaned === '') {
        return $cleaned;
    }

    $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);

    $cleaned = preg_replace('/^\s*(ok(?:ay)?[^\n]*|sure[^\n]*|here\'?s[^\n]*|i\'?ve prepared[^\n]*|following our conversation[^\n]*)\n+/im', '', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('/^\s*---+\s*\n?/m', '', $cleaned) ?? $cleaned;
    $cleaned = str_replace(['**', '__'], '', $cleaned);

    $lines = explode("\n", $cleaned);
    $filtered = [];
    foreach ($lines as $line) {
        if (preg_match('/\b(attach(ed|ment)?|document|outline|file)\b/i', $line)) {
            continue;
        }

        $trimmed = trim($line);
        if ($trimmed !== '') {
            $filtered[] = $trimmed;
        }
    }

    $cleaned = trim(implode("\n", $filtered));
    $cleaned = preg_replace('/\bSincerely,\b/i', 'Best regards,', $cleaned) ?? $cleaned;

    if (!preg_match('/^\s*Subject\s*:/im', $cleaned)) {
        $cleaned = "Subject: SEO Optimization Proposal\n\n" . $cleaned;
    }

    if (!preg_match('/\bBest regards,\b/i', $cleaned)) {
        $cleaned .= "\n\nBest regards,\n" . $developerName;
    }

    $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned) ?? $cleaned;
    return trim($cleaned);
}

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        return null;
    }

    return $user;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        set_flash('warning', 'Please log in to continue.');
        redirect(app_url('auth/login.php'));
    }
}

function require_role(string $role): void
{
    require_login();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        http_response_code(403);
        die('Access denied.');
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Access denied.');
    }
}

function reputation_label(float $score): string
{
    return level_from_score($score);
}

function badges_from_points(int $points): array
{
    $badges = [];
    if ($points >= 40) {
        $badges[] = 'Fast Delivery';
    }
    if ($points >= 80) {
        $badges[] = 'Top Rated';
    }
    return $badges;
}

function calculate_reputation_score(PDO $pdo, int $developerId): float
{
    $ratingStatement = $pdo->prepare('SELECT COALESCE(AVG(rating), 0) AS average_rating, COUNT(*) AS review_count FROM reviews WHERE developer_id = ?');
    $ratingStatement->execute([$developerId]);
    $ratingData = $ratingStatement->fetch() ?: ['average_rating' => 0, 'review_count' => 0];

    $applicationStatement = $pdo->prepare('SELECT SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_applications, SUM(CASE WHEN status = "hired" THEN 1 ELSE 0 END) AS hired_applications FROM applications WHERE developer_id = ?');
    $applicationStatement->execute([$developerId]);
    $applicationData = $applicationStatement->fetch() ?: ['completed_applications' => 0, 'hired_applications' => 0];

    $ratingScore = ((float) $ratingData['average_rating']) * 20;
    $completionScore = min(((int) $applicationData['completed_applications']) * 20, 100);
    $onTimeScore = min(((int) $applicationData['hired_applications']) * 20, 100);
    $reviewScore = min(((int) $ratingData['review_count']) * 10, 100);

    if ((int) $ratingData['review_count'] === 0 && (int) $applicationData['completed_applications'] === 0 && (int) $applicationData['hired_applications'] === 0) {
        return 50.0;
    }

    return round(($ratingScore * 0.4) + ($completionScore * 0.3) + ($onTimeScore * 0.2) + ($reviewScore * 0.1), 2);
}

function sync_reputation_score(PDO $pdo, int $developerId): float
{
    $score = calculate_reputation_score($pdo, $developerId);
    $statement = $pdo->prepare('INSERT INTO reputation_scores (developer_id, score) VALUES (?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)');
    $statement->execute([$developerId, $score]);

    return $score;
}

function award_gamification_points(PDO $pdo, int $userId, int $pointsToAdd): array
{
    $selectStatement = $pdo->prepare('SELECT points FROM gamification WHERE user_id = ? LIMIT 1');
    $selectStatement->execute([$userId]);
    $currentPoints = (int) ($selectStatement->fetchColumn() ?: 0);
    $updatedPoints = $currentPoints + $pointsToAdd;
    $level = readable_level($updatedPoints);
    $badges = badges_from_points($updatedPoints);

    $updateStatement = $pdo->prepare('INSERT INTO gamification (user_id, points, level, badges) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE points = VALUES(points), level = VALUES(level), badges = VALUES(badges)');
    $updateStatement->execute([$userId, $updatedPoints, $level, json_encode($badges)]);

    return ['points' => $updatedPoints, 'level' => $level, 'badges' => $badges];
}

function ensure_upload_directory(string $directory): void
{
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
}

function handle_resume_upload(array $file, string $uploadDirectory): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }

    $maxBytes = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Resume must be smaller than 2MB.');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        throw new RuntimeException('Resume must be a PDF file.');
    }

    ensure_upload_directory($uploadDirectory);
    $filename = 'resume_' . bin2hex(random_bytes(8)) . '.pdf';
    $destination = rtrim($uploadDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save resume upload.');
    }

    return 'uploads/resumes/' . $filename;
}
