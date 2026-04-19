<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

function oauth_request(string $url, string $method = 'GET', array $headers = [], array $fields = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'PHP cURL extension is not available.'];
    }

    $ch = curl_init();
    $normalizedHeaders = array_merge(['Accept: application/json', 'User-Agent: DevHire OAuth'], $headers);
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $normalizedHeaders,
        CURLOPT_TIMEOUT => 30,
    ];

    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POST] = true;
        if (!empty($fields)) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields);
        }
    }

    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $error = $body === false ? curl_error($ch) : null;
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body === false ? '' : $body,
        'error' => $error,
    ];
}

function oauth_provider_config(string $provider): array
{
    if ($provider === 'google') {
        return [
            'client_id' => config_value('services.oauth.google.client_id'),
            'client_secret' => config_value('services.oauth.google.client_secret'),
            'redirect_uri' => config_value('services.oauth.google.redirect_uri', app_url('auth/social_auth.php?provider=google&action=callback')),
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope' => 'openid email profile',
        ];
    }

    return [
        'client_id' => config_value('services.oauth.github.client_id'),
        'client_secret' => config_value('services.oauth.github.client_secret'),
        'redirect_uri' => config_value('services.oauth.github.redirect_uri', app_url('auth/social_auth.php?provider=github&action=callback')),
        'authorize_url' => 'https://github.com/login/oauth/authorize',
        'token_url' => 'https://github.com/login/oauth/access_token',
        'scope' => 'read:user user:email',
    ];
}

function ensure_oauth_accounts_table(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS oauth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_user_id VARCHAR(190) NOT NULL,
    provider_email VARCHAR(180) DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_provider_identity (provider, provider_user_id),
    UNIQUE KEY uniq_user_provider (user_id, provider),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL);
}

function oauth_update_user_records(PDO $pdo, int $userId, string $role): void
{
    $gamification = $pdo->prepare('INSERT INTO gamification (user_id, points, level, badges) VALUES (?, 0, ?, ?) ON DUPLICATE KEY UPDATE user_id = user_id');
    $gamification->execute([$userId, readable_level(0), json_encode([])]);

    if ($role === 'developer') {
        $reputation = $pdo->prepare('INSERT INTO reputation_scores (developer_id, score) VALUES (?, 50) ON DUPLICATE KEY UPDATE score = VALUES(score)');
        $reputation->execute([$userId]);

        $profile = $pdo->prepare('SELECT id FROM developer_profiles WHERE user_id = ? LIMIT 1');
        $profile->execute([$userId]);
        if (!$profile->fetch()) {
            $createProfile = $pdo->prepare('INSERT INTO developer_profiles (user_id, skills, experience, resume, bio, portfolio_links) VALUES (?, ?, 0, ?, ?, ?)');
            $createProfile->execute([$userId, '', '', '', '']);
        }
    }
}

function oauth_profile_from_provider(string $provider, string $accessToken): array
{
    if ($provider === 'google') {
        $response = oauth_request('https://www.googleapis.com/oauth2/v2/userinfo', 'GET', [
            'Authorization: Bearer ' . $accessToken,
        ]);

        if (!$response['ok']) {
            return ['error' => 'Unable to fetch Google profile.'];
        }

        $payload = json_decode($response['body'], true) ?: [];
        if (empty($payload['id']) || empty($payload['email'])) {
            return ['error' => 'Google did not return a valid profile.'];
        }

        if (isset($payload['verified_email']) && !$payload['verified_email']) {
            return ['error' => 'Google email address is not verified.'];
        }

        return [
            'provider_user_id' => (string) $payload['id'],
            'email' => (string) $payload['email'],
            'name' => (string) ($payload['name'] ?? $payload['given_name'] ?? 'Google User'),
            'avatar_url' => (string) ($payload['picture'] ?? ''),
        ];
    }

    $response = oauth_request('https://api.github.com/user', 'GET', [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/vnd.github+json',
    ]);

    if (!$response['ok']) {
        return ['error' => 'Unable to fetch GitHub profile.'];
    }

    $payload = json_decode($response['body'], true) ?: [];
    $email = trim((string) ($payload['email'] ?? ''));

    if ($email === '') {
        $emailsResponse = oauth_request('https://api.github.com/user/emails', 'GET', [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/vnd.github+json',
        ]);

        if ($emailsResponse['ok']) {
            $emails = json_decode($emailsResponse['body'], true) ?: [];
            foreach ($emails as $item) {
                if (!empty($item['primary']) && !empty($item['verified']) && !empty($item['email'])) {
                    $email = (string) $item['email'];
                    break;
                }
            }

            if ($email === '') {
                foreach ($emails as $item) {
                    if (!empty($item['verified']) && !empty($item['email'])) {
                        $email = (string) $item['email'];
                        break;
                    }
                }
            }
        }
    }

    if (empty($payload['id']) || $email === '') {
        return ['error' => 'GitHub did not return a usable email address.'];
    }

    return [
        'provider_user_id' => (string) $payload['id'],
        'email' => $email,
        'name' => (string) ($payload['name'] ?? $payload['login'] ?? 'GitHub User'),
        'avatar_url' => (string) ($payload['avatar_url'] ?? ''),
    ];
}

function oauth_flash_and_redirect(string $type, string $message, string $target = ''): never
{
    set_flash($type, $message);
    redirect($target !== '' ? $target : app_url('auth/register.php'));
}

$provider = strtolower(trim($_GET['provider'] ?? ''));
$action = strtolower(trim($_GET['action'] ?? 'start'));
$allowedProviders = ['google', 'github'];

if (!in_array($provider, $allowedProviders, true)) {
    oauth_flash_and_redirect('warning', 'Invalid social sign-in provider.', app_url('auth/register.php'));
}

ensure_oauth_accounts_table($pdo);

$config = oauth_provider_config($provider);
$desiredRole = in_array($_SESSION['oauth_desired_role'][$provider] ?? '', ['client', 'developer'], true)
    ? $_SESSION['oauth_desired_role'][$provider]
    : 'client';

if ($action === 'start' || empty($_GET['code'])) {
    if (empty($config['client_id']) || empty($config['client_secret'])) {
        oauth_flash_and_redirect('danger', ucfirst($provider) . ' OAuth is not configured yet. Add the client ID and secret in config.php.', app_url('auth/register.php'));
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'][$provider] = [
        'state' => $state,
        'role' => $desiredRole,
        'started_at' => time(),
    ];
    $_SESSION['oauth_desired_role'][$provider] = $desiredRole;

    $query = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => $config['scope'],
        'state' => $state,
    ];

    if ($provider === 'google') {
        $query['access_type'] = 'online';
        $query['prompt'] = 'select_account';
        $query['include_granted_scopes'] = 'true';
    }

    redirect($config['authorize_url'] . '?' . http_build_query($query));
}

if (!empty($_GET['error'])) {
    $errorDescription = trim((string) ($_GET['error_description'] ?? $_GET['error']));
    oauth_flash_and_redirect('danger', ucfirst($provider) . ' sign-in was cancelled or denied: ' . $errorDescription, app_url('auth/register.php'));
}

$state = (string) ($_GET['state'] ?? '');
$expectedState = $_SESSION['oauth_state'][$provider]['state'] ?? '';
$storedRole = $_SESSION['oauth_state'][$provider]['role'] ?? 'client';

if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
    oauth_flash_and_redirect('danger', 'OAuth state verification failed. Please try again.', app_url('auth/register.php'));
}

unset($_SESSION['oauth_state'][$provider]);

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    oauth_flash_and_redirect('danger', 'OAuth callback did not include an authorization code.', app_url('auth/register.php'));
}

if (empty($config['client_id']) || empty($config['client_secret'])) {
    oauth_flash_and_redirect('danger', ucfirst($provider) . ' OAuth is not configured yet. Add the client ID and secret in config.php.', app_url('auth/register.php'));
}

if ($provider === 'google') {
    $tokenResponse = oauth_request($config['token_url'], 'POST', [], [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);
} else {
    $tokenResponse = oauth_request($config['token_url'], 'POST', [
        'Accept: application/json',
    ], [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
    ]);
}

if (!$tokenResponse['ok']) {
    $message = $tokenResponse['error'] ?: ('Token exchange failed with HTTP ' . $tokenResponse['status'] . '.');
    oauth_flash_and_redirect('danger', ucfirst($provider) . ' sign-in failed: ' . $message, app_url('auth/register.php'));
}

$tokenPayload = json_decode($tokenResponse['body'], true) ?: [];
$accessToken = (string) ($tokenPayload['access_token'] ?? '');

if ($accessToken === '') {
    oauth_flash_and_redirect('danger', ucfirst($provider) . ' did not return an access token.', app_url('auth/register.php'));
}

$profile = oauth_profile_from_provider($provider, $accessToken);
if (!empty($profile['error'])) {
    oauth_flash_and_redirect('danger', $profile['error'], app_url('auth/register.php'));
}

$providerUserId = $profile['provider_user_id'];
$email = strtolower($profile['email']);
$name = trim($profile['name']);
$avatarUrl = trim($profile['avatar_url']);

$accountRow = null;
$accountLookup = $pdo->prepare('SELECT u.id, u.name, u.email, u.role FROM oauth_accounts oa INNER JOIN users u ON u.id = oa.user_id WHERE oa.provider = ? AND oa.provider_user_id = ? LIMIT 1');
$accountLookup->execute([$provider, $providerUserId]);
$accountRow = $accountLookup->fetch();

$userRow = null;
if (!$accountRow) {
    $userLookup = $pdo->prepare('SELECT id, name, email, role FROM users WHERE email = ? LIMIT 1');
    $userLookup->execute([$email]);
    $userRow = $userLookup->fetch();
}

try {
    $pdo->beginTransaction();

    if ($accountRow) {
        $userId = (int) $accountRow['id'];
        $role = $accountRow['role'];

        if ($role !== 'admin' && $storedRole === 'developer' && $role !== 'developer') {
            $role = 'developer';
            $userUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?');
            $userUpdate->execute([$name !== '' ? $name : $accountRow['name'], $email, $role, $userId]);
        } else {
            $userUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $userUpdate->execute([$name !== '' ? $name : $accountRow['name'], $email, $userId]);
        }
    } elseif ($userRow) {
        $userId = (int) $userRow['id'];
        $role = $userRow['role'];

        if ($role !== 'admin' && $storedRole === 'developer' && $role !== 'developer') {
            $role = 'developer';
            $roleUpdate = $pdo->prepare('UPDATE users SET role = ?, name = ?, email = ? WHERE id = ?');
            $roleUpdate->execute([$role, $name !== '' ? $name : $userRow['name'], $email, $userId]);
        } else {
            $userUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $userUpdate->execute([$name !== '' ? $name : $userRow['name'], $email, $userId]);
        }
    } else {
        $role = $storedRole === 'developer' ? 'developer' : 'client';
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $createUser = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $createUser->execute([$name !== '' ? $name : ucfirst($provider) . ' User', $email, $passwordHash, $role]);
        $userId = (int) $pdo->lastInsertId();
    }

    $accountUpsert = $pdo->prepare('INSERT INTO oauth_accounts (user_id, provider, provider_user_id, provider_email, avatar_url) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), provider_email = VALUES(provider_email), avatar_url = VALUES(avatar_url)');
    $accountUpsert->execute([$userId, $provider, $providerUserId, $email, $avatarUrl]);

    oauth_update_user_records($pdo, $userId, $role);

    if (!$accountRow && $userRow && $role === 'developer' && $userRow['role'] !== 'developer') {
        $role = 'developer';
    }

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['oauth_desired_role'][$provider]);

    set_flash('success', ucfirst($provider) . ' sign-in completed successfully.');

    if ($role === 'developer') {
        redirect(app_url('developer/profile.php'));
    }

    if ($role === 'admin') {
        redirect(app_url('admin/dashboard.php'));
    }

    redirect(app_url('user/dashboard.php'));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    oauth_flash_and_redirect('danger', ucfirst($provider) . ' sign-in failed. Please try again.', app_url('auth/register.php'));
}
