<?php
/**
 * BNI Anniversary Celebration
 * Admin & Committee Login Page
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Login Panitia';
$activeNav = 'login';
$settings = getSettings();
$baseUrl = getBaseUrl();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . $baseUrl . '/admin/dashboard.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? ($baseUrl . '/admin/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Token keamanan tidak valid atau sesi berakhir. Silakan coba lagi.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Username dan password wajib diisi.';
        } else {
            $loginResult = loginUser($username, $password);
            if ($loginResult['success']) {
                $target = !empty($_POST['redirect']) ? $_POST['redirect'] : ($baseUrl . '/admin/dashboard.php');
                // Prevent open redirect
                if (strpos($target, 'http') === 0 && strpos($target, $baseUrl) !== 0) {
                    $target = $baseUrl . '/admin/dashboard.php';
                }
                header('Location: ' . $target);
                exit;
            } else {
                $error = $loginResult['message'];
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="bni-card shadow-lg p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="<?= $baseUrl ?>/assets/images/bni-life-30.png" alt="30 Tahun BNI Life" style="height: 72px; width: auto;" class="mb-3">
                    <h3 class="fw-bold text-navy mb-1">Portal Panitia & Admin</h3>
                    <p class="text-muted small">
                        Masuk untuk mengelola peserta, memonitor check-in, dan memutar Magic Wheel.
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                        <div class="small"><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <div class="mb-3">
                        <label for="usernameInput" class="form-label fw-bold small text-navy">
                            <i class="fa-solid fa-user me-1 text-primary"></i> Username
                        </label>
                        <input 
                            type="text" 
                            name="username" 
                            id="usernameInput" 
                            class="form-control form-control-lg" 
                            placeholder="Contoh: superadmin" 
                            required 
                            autofocus
                        >
                    </div>

                    <div class="mb-4">
                        <label for="passwordInput" class="form-label fw-bold small text-navy">
                            <i class="fa-solid fa-key me-1 text-primary"></i> Password
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            id="passwordInput" 
                            class="form-control form-control-lg" 
                            placeholder="Masukkan password Anda" 
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-bni-orange btn-lg w-100 justify-content-center shadow mb-3">
                        <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Masuk Sekarang
                    </button>
                </form>

                <!-- Default Demo Credentials Information Card -->
                <div class="p-3 bg-light rounded-3 border mt-4">
                    <div class="small fw-bold text-navy mb-2 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-info text-primary"></i> Akun Bawaan Sistem (Demo):
                    </div>
                    <div class="small text-muted mb-1">
                        &bull; <strong>superadmin</strong> / <code>AdminBNI2024!</code> (Akses Penuh)
                    </div>
                    <div class="small text-muted mb-1">
                        &bull; <strong>operator</strong> / <code>OperatorBNI2024!</code> (Magic Wheel)
                    </div>
                    <div class="small text-muted">
                        &bull; <strong>panitia</strong> / <code>PanitiaBNI2024!</code> (Meja Registrasi)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
