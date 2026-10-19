<?php
/**
 * BNI Anniversary Celebration
 * Admin Layout Header & Sidebar
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure user is logged in
requireLogin();

$currentUser = getCurrentUser();
$settings = getSettings();
$baseUrl = getBaseUrl();
$activeMenu = $activeMenu ?? 'dashboard';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin Panel') ?> - <?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?></title>
    <meta name="theme-color" content="#0B2240">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Styling -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/magic-wheel.css">
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar shadow-lg">
        <div class="admin-sidebar-header d-flex align-items-center justify-content-between">
            <a href="<?= $baseUrl ?>/admin/dashboard.php" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="<?= $baseUrl ?>/assets/images/bni-logo.svg" alt="BNI Logo" style="height: 38px; filter: brightness(0) invert(1);">
            </a>
        </div>

        <div class="admin-sidebar-nav">
            <div class="admin-nav-category">Utama</div>
            <a href="<?= $baseUrl ?>/admin/dashboard.php" class="admin-nav-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
            </a>

            <div class="admin-nav-category">Peserta & Registrasi</div>
            <a href="<?= $baseUrl ?>/admin/participants.php" class="admin-nav-item <?= $activeMenu === 'participants' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i> <span>Data Peserta</span>
            </a>
            <a href="<?= $baseUrl ?>/admin/import.php" class="admin-nav-item <?= $activeMenu === 'import' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-import"></i> <span>Import & Export</span>
            </a>
            <a href="<?= $baseUrl ?>/admin/checkin-monitor.php" class="admin-nav-item <?= $activeMenu === 'checkin-monitor' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-check"></i> <span>Monitor Check-in</span>
            </a>
            <a href="<?= $baseUrl ?>/admin/print-qr.php" class="admin-nav-item <?= $activeMenu === 'print-qr' ? 'active' : '' ?>">
                <i class="fa-solid fa-qrcode"></i> <span>Cetak QR Meja</span>
            </a>

            <div class="admin-nav-category">Pengundian Doorprize</div>
            <a href="<?= $baseUrl ?>/admin/magic-wheel.php" class="admin-nav-item <?= $activeMenu === 'magic-wheel' ? 'active' : '' ?>">
                <i class="fa-solid fa-arrows-spin text-warning"></i> <span>Magic Wheel</span>
            </a>
            <a href="<?= $baseUrl ?>/admin/prizes.php" class="admin-nav-item <?= $activeMenu === 'prizes' ? 'active' : '' ?>">
                <i class="fa-solid fa-gift"></i> <span>Manajemen Hadiah</span>
            </a>
            <a href="<?= $baseUrl ?>/admin/draw-history.php" class="admin-nav-item <?= $activeMenu === 'draw-history' ? 'active' : '' ?>">
                <i class="fa-solid fa-trophy"></i> <span>Riwayat Undian</span>
            </a>

            <div class="admin-nav-category">Laporan & Pengaturan</div>
            <a href="<?= $baseUrl ?>/admin/reports.php" class="admin-nav-item <?= $activeMenu === 'reports' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> <span>Laporan Acara</span>
            </a>

            <?php if (hasRole(['superadmin'])): ?>
                <a href="<?= $baseUrl ?>/admin/settings.php" class="admin-nav-item <?= $activeMenu === 'settings' ? 'active' : '' ?>">
                    <i class="fa-solid fa-sliders"></i> <span>Pengaturan Acara</span>
                </a>
                <a href="<?= $baseUrl ?>/admin/users.php" class="admin-nav-item <?= $activeMenu === 'users' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-shield"></i> <span>Manajemen Akun</span>
                </a>
            <?php endif; ?>

            <?php if (hasRole(['superadmin', 'admin'])): ?>
                <a href="<?= $baseUrl ?>/admin/audit-logs.php" class="admin-nav-item <?= $activeMenu === 'audit-logs' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-list"></i> <span>Audit Log</span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Sidebar User Footer -->
        <div class="p-3 border-top border-secondary border-opacity-25 mt-auto">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <div class="bg-warning text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <?= strtoupper(substr($currentUser['nama_admin'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="text-truncate">
                        <div class="small fw-bold text-white text-truncate"><?= e($currentUser['nama_admin']) ?></div>
                        <div class="text-white-50" style="font-size: 0.72rem; text-transform: uppercase;"><?= e($currentUser['role']) ?></div>
                    </div>
                </div>
                <a href="<?= $baseUrl ?>/logout.php" class="btn btn-sm btn-outline-danger p-1 px-2" title="Logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-content">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="d-flex align-items-center gap-3">
                <h4 class="mb-0 fw-bold text-navy"><?= e($pageTitle ?? 'Dashboard') ?></h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= $baseUrl ?>/index.php" target="_blank" class="btn btn-sm btn-outline-secondary" title="Buka Website Publik">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Lihat Web
                </a>
                <a href="<?= $baseUrl ?>/admin/magic-wheel.php" class="btn btn-sm btn-bni-orange">
                    <i class="fa-solid fa-arrows-spin me-1"></i> Putar Roda
                </a>
            </div>
        </header>

        <!-- Flash Message in Admin Area -->
        <?php if ($flash): ?>
        <div class="px-4 pt-3">
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-0" role="alert">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-danger' ?> fs-5"></i>
                <div><?= e($flash['message']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Body Container -->
        <main class="admin-body">
