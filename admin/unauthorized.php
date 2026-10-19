<?php
/**
 * BNI Anniversary Celebration
 * Unauthorized Access Page (HTTP 403)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Akses Ditolak';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 text-center">
    <div class="bni-card p-5 mx-auto shadow" style="max-width: 550px;">
        <div class="text-danger mb-3">
            <i class="fa-solid fa-hand fs-1" style="font-size: 4rem;"></i>
        </div>
        <h2 class="fw-bold text-navy mb-2">Akses Ditolak (403)</h2>
        <p class="text-muted mb-4">
            Peran akun Anda (<strong><?= e(getCurrentUser()['role'] ?? 'Tamu') ?></strong>) tidak memiliki hak akses untuk membuka halaman ini.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= getBaseUrl() ?>/admin/dashboard.php" class="btn btn-bni-orange">
                <i class="fa-solid fa-gauge me-1"></i> Dashboard Utama
            </a>
            <a href="<?= getBaseUrl() ?>/logout.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Ganti Akun
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
