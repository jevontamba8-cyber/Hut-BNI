<?php
/**
 * BNI Anniversary Celebration
 * Landing Page - Modern, Festive & Interactive
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Beranda';
$activeNav = 'home';
$settings = getSettings();
$stats = getEventStats();
$baseUrl = getBaseUrl();

// Fetch active prize list for showcase
$db = getDB();
$prizesStmt = $db->query("SELECT id, nama_hadiah, deskripsi, jumlah_pemenang, status_hadiah FROM prizes WHERE status_hadiah != 'NONAKTIF' ORDER BY urutan ASC LIMIT 6");
$prizes = $prizesStmt->fetchAll();

// Fetch recent winners ticker
$recentWinnersStmt = $db->query("
    SELECT p.nama_lengkap, p.cabang, pr.nama_hadiah, dr.waktu_undian 
    FROM draw_results dr 
    JOIN participants p ON dr.participant_id = p.id 
    JOIN prizes pr ON dr.prize_id = pr.id 
    WHERE dr.status_hasil = 'SAH' 
    ORDER BY dr.id DESC LIMIT 5
");
$recentWinners = $recentWinnersStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-celebration">
    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-5">
            <div class="col-lg-7 text-center text-lg-start">
                <div class="hero-badge">
                    <i class="fa-solid fa-sparkles"></i> <?= e($settings['company_name'] ?? 'PT Bank Negara Indonesia (Persero) Tbk') ?>
                </div>
                <h1 class="hero-title">
                    <?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?>
                    <br><span class="highlight-orange">Grand Event 2026</span>
                </h1>
                <p class="hero-tagline">
                    “<?= e($settings['event_tagline'] ?? 'Celebrating Together, Growing Stronger') ?>”
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start mb-4">
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-3 text-white small">
                        <i class="fa-regular fa-calendar-check text-warning"></i>
                        <span><?= formatIndonesianDate($settings['event_date'] ?? '2026-10-25', false) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-3 text-white small">
                        <i class="fa-solid fa-clock text-warning"></i>
                        <span><?= e($settings['event_time'] ?? '08:30 WIB') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-3 text-white small">
                        <i class="fa-solid fa-map-pin text-warning"></i>
                        <span><?= e($settings['event_location'] ?? 'Grand Ballroom Kempinski Jakarta') ?></span>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start pt-2">
                    <a href="<?= $baseUrl ?>/check-in.php" class="btn btn-bni-orange btn-lg px-4 shadow">
                        <i class="fa-solid fa-qrcode fs-5"></i> Check-in Peserta Sekarang
                    </a>
                    <a href="<?= isLoggedIn() ? $baseUrl . '/admin/magic-wheel.php' : $baseUrl . '/login.php?redirect=' . urlencode('admin/magic-wheel.php') ?>" class="btn btn-bni-gold btn-lg px-4 shadow">
                        <i class="fa-solid fa-arrows-spin fs-5"></i> Buka Magic Wheel
                    </a>
                </div>
            </div>

            <!-- Hero Celebration Visual Card -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-lg bg-white bg-opacity-10 backdrop-blur rounded-4 p-4 text-white border border-white border-opacity-20 text-center position-relative overflow-hidden">
                    <div class="position-absolute top-0 end-0 p-3">
                        <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill shadow-sm">
                            <i class="fa-solid fa-gift me-1"></i> Special Prizes
                        </span>
                    </div>
                    <div class="my-4">
                        <i class="fa-solid fa-award text-warning" style="font-size: 5rem; filter: drop-shadow(0 0 25px rgba(255, 215, 0, 0.6));"></i>
                    </div>
                    <h3 class="fw-bold mb-2">Tema Perayaan HUT</h3>
                    <p class="text-white-50 px-3 mb-4">
                        “<?= e($settings['event_theme'] ?? 'Synergy of Excellence & Digital Innovation') ?>”
                    </p>
                    <div class="p-3 bg-black bg-opacity-25 rounded-3 border border-white border-opacity-10">
                        <div class="small text-warning fw-bold text-uppercase mb-1">Status Pintu Masuk</div>
                        <div class="h5 mb-0 text-success fw-bold d-flex align-items-center justify-content-center gap-2">
                            <span class="spinner-grow spinner-grow-sm" role="status"></span>
                            REGISTRASI DIBUKA
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Real-time Event Statistics Cards -->
<section class="py-5" style="margin-top: -30px; position: relative; z-index: 3;">
    <div class="container">
        <div class="row g-4">
            <!-- Total Peserta Terdaftar -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-orange">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-total-p"><?= number_format($stats['total_participants']) ?></div>
                        <div class="stat-label">Total Peserta</div>
                    </div>
                </div>
            </div>

            <!-- Total Peserta Hadir (Check-in) -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-teal">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <div class="stat-value text-success" id="stat-hadir"><?= number_format($stats['hadir']) ?></div>
                        <div class="stat-label">Telah Hadir (<?= $stats['kehadiran_persen'] ?>%)</div>
                    </div>
                </div>
            </div>

            <!-- Jumlah Hadiah -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-gold">
                        <i class="fa-solid fa-gifts"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-prizes"><?= number_format($stats['total_prizes']) ?></div>
                        <div class="stat-label">Kategori Hadiah</div>
                    </div>
                </div>
            </div>

            <!-- Jumlah Pemenang Terpilih -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper stat-icon-navy">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div>
                        <div class="stat-value text-warning" id="stat-winners"><?= number_format($stats['total_winners']) ?></div>
                        <div class="stat-label">Pemenang Sah</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Interactive Content -->
<section class="py-5">
    <div class="container">
        <!-- Live Winners Ticker (if any) -->
        <?php if (!empty($recentWinners)): ?>
        <div class="bni-card mb-5 border-start border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bullhorn text-warning fs-5"></i>
                    <span>Pemenang Undian Terbaru:</span>
                </div>
                <a href="<?= $baseUrl ?>/winners.php" class="small fw-bold text-primary">Lihat Seluruh Pemenang &rarr;</a>
            </div>
            <div class="row g-3">
                <?php foreach ($recentWinners as $rw): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="p-3 rounded-3 bg-light border d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-25 text-warning p-2 rounded-circle fs-4">
                            <i class="fa-solid fa-medal"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark"><?= e($rw['nama_lengkap']) ?></div>
                            <div class="small text-muted"><?= e($rw['cabang']) ?> &bull; <span class="text-primary fw-semibold"><?= e($rw['nama_hadiah']) ?></span></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Highlight Grid -->
        <div class="row g-4 mb-5">
            <!-- Check-in Guide Card -->
            <div class="col-lg-6">
                <div class="bni-card h-100 d-flex flex-column justify-content-between p-4 p-md-5">
                    <div>
                        <div class="d-inline-flex p-3 rounded-4 bg-primary bg-opacity-10 text-primary mb-3">
                            <i class="fa-solid fa-mobile-screen-button fs-2 text-warning"></i>
                        </div>
                        <h3 class="fw-bold text-navy mb-3">Registrasi & Check-in Mandiri</h3>
                        <p class="text-muted mb-4">
                            Seluruh peserta acara BNI Anniversary dapat melakukan konfirmasi kehadiran dengan mudah tanpa perlu memasang aplikasi tambahan. Cukup gunakan kamera smartphone untuk scan QR Code atau akses form check-in.
                        </p>
                        <ul class="list-unstyled d-flex flex-column gap-3 mb-4">
                            <li class="d-flex align-items-start gap-3">
                                <span class="badge rounded-circle bg-success p-2 mt-1"><i class="fa-solid fa-check text-white"></i></span>
                                <div>
                                    <strong>Scan QR Code Registrasi</strong>
                                    <div class="small text-muted">Arahkan kamera smartphone ke QR Code yang tersedia di meja panitia.</div>
                                </div>
                            </li>
                            <li class="d-flex align-items-start gap-3">
                                <span class="badge rounded-circle bg-success p-2 mt-1"><i class="fa-solid fa-check text-white"></i></span>
                                <div>
                                    <strong>Masukkan 16 Digit NIK Pegawai</strong>
                                    <div class="small text-muted">Sistem akan mencocokkan identitas dan nomor undian Anda secara aman.</div>
                                </div>
                            </li>
                            <li class="d-flex align-items-start gap-3">
                                <span class="badge rounded-circle bg-success p-2 mt-1"><i class="fa-solid fa-check text-white"></i></span>
                                <div>
                                    <strong>Konfirmasi & Dapatkan Tiket Digital</strong>
                                    <div class="small text-muted">Nama Anda otomatis masuk ke dalam putaran Magic Wheel doorprize.</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <a href="<?= $baseUrl ?>/check-in.php" class="btn btn-bni-orange btn-lg w-100 justify-content-center">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk Halaman Check-in
                    </a>
                </div>
            </div>

            <!-- Magic Wheel Highlight Card -->
            <div class="col-lg-6">
                <div class="bni-card h-100 d-flex flex-column justify-content-between p-4 p-md-5" style="background: radial-gradient(circle at top right, #112948 0%, #071526 100%); color: #FFFFFF;">
                    <div>
                        <div class="d-inline-flex p-3 rounded-4 bg-warning bg-opacity-20 text-warning mb-3">
                            <i class="fa-solid fa-arrows-spin fs-2"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-3">Interactive Magic Wheel</h3>
                        <p class="text-white-50 mb-4">
                            Pusat sorotan kemeriahan HUT BNI! Roda keberuntungan digital dengan tampilan visual megah yang menampilkan <strong>Nama Lengkap</strong> peserta secara langsung dari database MySQL.
                        </p>
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10 mb-4">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <i class="fa-solid fa-shield-halved text-warning fs-4"></i>
                                <div>
                                    <div class="fw-bold">Randomness Terenkripsi & Adil</div>
                                    <div class="small text-white-50">Pengundian dikendalikan langsung oleh backend PHP dengan validasi kehadiran 100%.</div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-10 mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fa-solid fa-tv text-warning fs-4"></i>
                                <div>
                                    <div class="fw-bold">Mode Presentasi Fullscreen Proyektor</div>
                                    <div class="small text-white-50">Dirancang khusus untuk layar panggung acara dengan efek suara dan ledakan confetti.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="<?= isLoggedIn() ? $baseUrl . '/admin/magic-wheel.php' : $baseUrl . '/login.php?redirect=' . urlencode('admin/magic-wheel.php') ?>" class="btn btn-bni-gold btn-lg w-100 justify-content-center">
                        <i class="fa-solid fa-gamepad"></i> Buka Arena Magic Wheel
                    </a>
                </div>
            </div>
        </div>

        <!-- Prize Showcase Section -->
        <div class="mb-5">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-navy mb-1"><i class="fa-solid fa-gift text-warning me-2"></i>Daftar Hadiah Spektakuler</h3>
                    <p class="text-muted mb-0">Ratusan hadiah siap dibagikan untuk seluruh insan BNI yang hadir.</p>
                </div>
                <a href="<?= $baseUrl ?>/winners.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fa-solid fa-list-check me-1"></i> Lihat Status Pemenang
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($prizes as $prize): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="bni-card h-100 mb-0 position-relative">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="stat-icon-wrapper stat-icon-gold">
                                <i class="fa-solid fa-trophy"></i>
                            </div>
                            <span class="badge <?= $prize['status_hadiah'] === 'SELESAI' ? 'bg-secondary' : 'bg-success' ?> px-3 py-2 rounded-pill">
                                <?= $prize['status_hadiah'] === 'SELESAI' ? 'Selesai Diundi' : 'Tersedia' ?>
                            </span>
                        </div>
                        <h5 class="fw-bold text-navy mb-2"><?= e($prize['nama_hadiah']) ?></h5>
                        <p class="text-muted small mb-3"><?= e($prize['deskripsi'] ?: 'Hadiah persembahan BNI Anniversary Celebration.') ?></p>
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center small">
                            <span class="text-muted">Kuota Pemenang:</span>
                            <span class="fw-bold text-navy bg-light px-2 py-1 rounded"><?= (int)$prize['jumlah_pemenang'] ?> Orang</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
