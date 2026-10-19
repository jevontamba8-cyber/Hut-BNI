<?php
/**
 * BNI Anniversary Celebration
 * Admin Dashboard Main Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Dashboard Utama';
$activeMenu = 'dashboard';
$stats = getEventStats();
$db = getDB();

// Recent check-ins
$recentCheckins = $db->query("
    SELECT id, nomor_undian, nama_lengkap, divisi, cabang, waktu_checkin 
    FROM participants 
    WHERE status_kehadiran = 'HADIR' 
    ORDER BY waktu_checkin DESC LIMIT 6
")->fetchAll();

// Recent draw winners
$recentWinners = $db->query("
    SELECT dr.id, dr.waktu_undian, p.nama_lengkap, p.nomor_undian, p.cabang, pr.nama_hadiah 
    FROM draw_results dr
    JOIN participants p ON dr.participant_id = p.id
    JOIN prizes pr ON dr.prize_id = pr.id
    WHERE dr.status_hasil = 'SAH'
    ORDER BY dr.id DESC LIMIT 5
")->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<!-- Statistics Overview -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-orange">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div class="stat-value"><?= number_format($stats['total_participants']) ?></div>
                <div class="stat-label">Total Peserta</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-teal">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <div class="stat-value text-success"><?= number_format($stats['hadir']) ?></div>
                <div class="stat-label">Telah Hadir (<?= $stats['kehadiran_persen'] ?>%)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-navy">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
            <div>
                <div class="stat-value text-danger"><?= number_format($stats['belum_hadir']) ?></div>
                <div class="stat-label">Belum Hadir</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-gold">
                <i class="fa-solid fa-trophy"></i>
            </div>
            <div>
                <div class="stat-value text-warning"><?= number_format($stats['total_winners']) ?> / <?= number_format($stats['total_prize_quota']) ?></div>
                <div class="stat-label">Pemenang / Kuota Hadiah</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Shortcuts -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <a href="<?= $baseUrl ?>/admin/magic-wheel.php" class="bni-card h-100 text-decoration-none d-flex align-items-center gap-3 p-3 border-start border-4 border-warning bg-gradient">
            <div class="p-3 bg-warning text-dark rounded-circle fs-4">
                <i class="fa-solid fa-arrows-spin"></i>
            </div>
            <div>
                <div class="fw-bold text-navy">Magic Wheel</div>
                <div class="small text-muted">Undi Hadiah Sekarang</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= $baseUrl ?>/admin/import.php" class="bni-card h-100 text-decoration-none d-flex align-items-center gap-3 p-3 border-start border-4 border-primary">
            <div class="p-3 bg-primary text-white rounded-circle fs-4">
                <i class="fa-solid fa-file-excel"></i>
            </div>
            <div>
                <div class="fw-bold text-navy">Import Peserta</div>
                <div class="small text-muted">Upload Excel / CSV 1.200 Peserta</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= $baseUrl ?>/admin/print-qr.php" class="bni-card h-100 text-decoration-none d-flex align-items-center gap-3 p-3 border-start border-4 border-success">
            <div class="p-3 bg-success text-white rounded-circle fs-4">
                <i class="fa-solid fa-qrcode"></i>
            </div>
            <div>
                <div class="fw-bold text-navy">Cetak QR Code</div>
                <div class="small text-muted">Pasang di Meja Registrasi</div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= $baseUrl ?>/admin/checkin-monitor.php" class="bni-card h-100 text-decoration-none d-flex align-items-center gap-3 p-3 border-start border-4 border-info">
            <div class="p-3 bg-info text-white rounded-circle fs-4">
                <i class="fa-solid fa-desktop"></i>
            </div>
            <div>
                <div class="fw-bold text-navy">Live Monitor</div>
                <div class="small text-muted">Pantau Kedatangan Real-time</div>
            </div>
        </a>
    </div>
</div>

<!-- Tables Grid -->
<div class="row g-4">
    <!-- Live Check-in Monitor Feed -->
    <div class="col-lg-6">
        <div class="bni-card h-100 p-0 overflow-hidden shadow-sm">
            <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold text-navy">
                    <i class="fa-solid fa-user-check me-2 text-success"></i> Check-in Terkini
                </h5>
                <a href="<?= $baseUrl ?>/admin/checkin-monitor.php" class="small fw-bold text-primary">Lihat Semua &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="bni-table">
                    <thead>
                        <tr>
                            <th>Peserta</th>
                            <th>No. Undian</th>
                            <th>Cabang</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentCheckins)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">Belum ada peserta yang check-in hari ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentCheckins as $c): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-navy"><?= e($c['nama_lengkap']) ?></div>
                                        <div class="small text-muted"><?= e($c['divisi']) ?></div>
                                    </td>
                                    <td><span class="badge bg-primary font-monospace"><?= e($c['nomor_undian']) ?></span></td>
                                    <td class="small"><?= e($c['cabang']) ?></td>
                                    <td class="small text-success"><?= date('H:i:s', strtotime($c['waktu_checkin'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Live Winners Feed -->
    <div class="col-lg-6">
        <div class="bni-card h-100 p-0 overflow-hidden shadow-sm">
            <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold text-navy">
                    <i class="fa-solid fa-trophy me-2 text-warning"></i> Pemenang Undian Terakhir
                </h5>
                <a href="<?= $baseUrl ?>/admin/draw-history.php" class="small fw-bold text-primary">Riwayat Lengkap &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="bni-table">
                    <thead>
                        <tr>
                            <th>Hadiah</th>
                            <th>Pemenang</th>
                            <th>No. Undian</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentWinners)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">Belum ada undian yang dilakukan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentWinners as $w): ?>
                                <tr>
                                    <td><span class="badge bg-warning-subtle text-warning-emphasis fw-bold"><?= e($w['nama_hadiah']) ?></span></td>
                                    <td class="fw-bold text-navy"><?= e($w['nama_lengkap']) ?></td>
                                    <td><span class="badge bg-secondary font-monospace"><?= e($w['nomor_undian']) ?></span></td>
                                    <td class="small text-muted"><?= date('H:i', strtotime($w['waktu_undian'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/admin-footer.php'; ?>
