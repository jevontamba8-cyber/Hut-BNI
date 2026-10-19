<?php
/**
 * BNI Anniversary Celebration
 * Global Public Header
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$settings = getSettings();
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . ($settings['event_title'] ?? 'BNI Anniversary Celebration') : ($settings['event_title'] ?? 'BNI Anniversary Celebration');
$activeNav = $activeNav ?? 'home';
$baseUrl = getBaseUrl();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?> - <?= e($settings['event_tagline'] ?? 'Celebrating Together, Growing Stronger') ?>. Sistem check-in QR Code dan pengundian doorprize Magic Wheel.">
    <meta name="theme-color" content="#F37021">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Styling -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/magic-wheel.css">
</head>
<body>

<!-- Header Navbar -->
<nav class="navbar navbar-expand-lg bni-navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $baseUrl ?>/index.php">
            <img src="<?= $baseUrl ?>/assets/images/bni-logo.svg" alt="BNI Logo">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#bniNavbarNav">
            <i class="fa-solid fa-bars text-primary fs-4"></i>
        </button>
        <div class="collapse navbar-collapse" id="bniNavbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php">
                        <i class="fa-solid fa-house me-1"></i> Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'checkin' ? 'active' : '' ?>" href="<?= $baseUrl ?>/check-in.php">
                        <i class="fa-solid fa-qrcode me-1 text-warning"></i> Check-in Peserta
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeNav === 'winners' ? 'active' : '' ?>" href="<?= $baseUrl ?>/winners.php">
                        <i class="fa-solid fa-trophy me-1 text-warning"></i> Hasil Undian
                    </a>
                </li>

                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle btn btn-sm btn-outline-secondary px-3 py-1" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-user-shield text-success me-1"></i> <?= e(getCurrentUser()['nama_admin']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/dashboard.php"><i class="fa-solid fa-gauge me-2 text-primary"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/magic-wheel.php"><i class="fa-solid fa-arrows-spin me-2 text-warning"></i>Magic Wheel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= $baseUrl ?>/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Keluar</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-sm btn-bni-orange px-3" href="<?= $baseUrl ?>/login.php">
                            <i class="fa-solid fa-lock me-1"></i> Login Panitia
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Message Notifications -->
<?php if ($flash): ?>
<div class="container mt-3">
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-danger' ?> fs-5"></i>
        <div><?= e($flash['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>
