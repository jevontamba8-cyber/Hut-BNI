<?php
/**
 * BNI Anniversary Celebration
 * CSRF Protection Middleware & Helper Functions
 */

declare(strict_types=1);

if (!isset($_SESSION)) {
    session_start();
}

/**
 * Generate or retrieve existing CSRF token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output HTML input element for CSRF
 */
function csrfField(): string
{
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validate submitted CSRF token
 */
function validateCsrfToken(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Strict verification for mutating requests
 */
function verifyCsrfOrDie(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken()) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'Token keamanan (CSRF) tidak valid atau sesi telah kedaluwarsa. Silakan refresh halaman.'
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
