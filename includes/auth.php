<?php
/**
 * BNI Anniversary Celebration
 * Authentication & Session Management with Role-Based Access Control
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

// Configure session security parameters before starting session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    
    // Auto-detect HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    session_set_cookie_params([
        'lifetime' => 28800, // 8 hours
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

/**
 * Check if current user is logged in
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_user']) && !empty($_SESSION['admin_user']['id']);
}

/**
 * Get logged-in user profile
 */
function getCurrentUser(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

/**
 * Check if user has one of the allowed roles
 * Roles hierarchy: superadmin > admin > operator / panitia
 */
function hasRole($roles): bool
{
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    // superadmin always has access to all admin operations
    if ($user['role'] === 'superadmin') {
        return true;
    }

    return in_array($user['role'], $roles, true);
}

/**
 * Require login and optionally specific role
 */
function requireLogin($allowedRoles = []): void
{
    if (!isLoggedIn()) {
        $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . $redirectUrl);
        exit;
    }

    if (!empty($allowedRoles) && !hasRole($allowedRoles)) {
        http_response_code(403);
        include __DIR__ . '/../admin/unauthorized.php';
        exit;
    }
}

/**
 * Rate limiting check for failed actions (e.g. login or NIK checks)
 */
function checkRateLimit(string $key, int $maxAttempts = 5, int $decaySeconds = 300): bool
{
    $now = time();
    $rateKey = 'rate_' . $key;
    
    if (!isset($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = ['attempts' => 0, 'first_attempt' => $now];
    }
    
    // Reset if window passed
    if ($now - $_SESSION[$rateKey]['first_attempt'] > $decaySeconds) {
        $_SESSION[$rateKey] = ['attempts' => 0, 'first_attempt' => $now];
    }
    
    return $_SESSION[$rateKey]['attempts'] < $maxAttempts;
}

/**
 * Increment failed attempt counter
 */
function hitRateLimit(string $key): void
{
    $rateKey = 'rate_' . $key;
    if (!isset($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = ['attempts' => 1, 'first_attempt' => time()];
    } else {
        $_SESSION[$rateKey]['attempts']++;
    }
}

/**
 * Clear rate limit on success
 */
function clearRateLimit(string $key): void
{
    unset($_SESSION['rate_' . $key]);
}

/**
 * Log an event to audit_logs table
 */
function logAudit(?int $adminId, string $activity, string $details = ''): void
{
    try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO audit_logs (admin_id, aktivitas, detail_aktivitas, waktu_aktivitas) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$adminId, $activity, $details]);
    } catch (Exception $e) {
        error_log('Audit Log Write Failed: ' . $e->getMessage());
    }
}

/**
 * Authenticate admin user
 */
function loginUser(string $username, string $password): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateKey = 'login_' . md5($ip . '_' . $username);

    if (!checkRateLimit($rateKey, 5, 300)) {
        return [
            'success' => false,
            'message' => 'Terlalu banyak percobaan login gagal. Harap tunggu 5 menit sebelum mencoba kembali.'
        ];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, nama_admin, username, password_hash, role, status_akun FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([trim($username)]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            hitRateLimit($rateKey);
            logAudit(null, 'LOGIN_FAILED', "Percobaan login gagal untuk username: $username dari IP: $ip");
            return [
                'success' => false,
                'message' => 'Username atau password yang Anda masukkan tidak sesuai.'
            ];
        }

        if ($admin['status_akun'] !== 'AKTIF') {
            return [
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi Super Admin.'
            ];
        }

        // Authentication success - clear rate limit & regenerate session id
        clearRateLimit($rateKey);
        session_regenerate_id(true);

        $_SESSION['admin_user'] = [
            'id'         => (int)$admin['id'],
            'nama_admin' => $admin['nama_admin'],
            'username'   => $admin['username'],
            'role'       => $admin['role'],
            'logged_at'  => time()
        ];

        logAudit((int)$admin['id'], 'LOGIN_SUCCESS', "Admin {$admin['username']} ({$admin['role']}) berhasil login dari IP: $ip");

        return [
            'success' => true,
            'user'    => $_SESSION['admin_user']
        ];
    } catch (Exception $e) {
        error_log('Login Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Terjadi kesalahan sistem saat memproses login.'
        ];
    }
}

/**
 * Logout current user
 */
function logoutUser(): void
{
    $user = getCurrentUser();
    if ($user) {
        logAudit($user['id'], 'LOGOUT', "Admin {$user['username']} telah logout.");
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
