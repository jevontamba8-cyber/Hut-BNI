<?php
/**
 * BNI Anniversary Celebration
 * Event Executive Reports & Analytics
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin']);

$pageTitle = 'Laporan Acara';
$activeMenu = 'reports';
$stats = getEventStats();
$db = getDB();

// Attendance by Division
$divStmt = $db->query("
    SELECT 
        divisi,
        COUNT(*) AS total,
        SUM(CASE WHEN status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status_kehadiran = 'BELUM HADIR' THEN 1 ELSE 0 END) AS belum_hadir
    FROM participants
    GROUP BY divisi
    ORDER BY total DESC
");
$divisionReport = $divStmt->fetchAll();

// Attendance by Branch
$branchStmt = $db->query("
    SELECT 
        cabang,
        COUNT(*) AS total,
        SUM(CASE WHEN status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status_kehadiran = 'BELUM HADIR' THEN 1 ELSE 0 END) AS belum_hadir
    FROM participants
    GROUP BY cabang
    ORDER BY total DESC
");
$branchReport = $branchStmt->fetchAll();

// Prize Distribution Summary
$prizeReport = $db->query("
    SELECT 
        pr.nama_hadiah,
        pr.jumlah_pemenang,
        pr.status_hadiah,
        COUNT(CASE WHEN dr.status_hasil = 'SAH' THEN 1 END) AS winners_count
    FROM prizes pr
    LEFT JOIN draw_results dr ON pr.id = dr.prize_id
    GROUP BY pr.id
    ORDER BY pr.urutan ASC
")->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1">Laporan Eksekutif BNI Anniversary</h3>
        <p class="text-muted small mb-0">Ringkasan performa kehadiran registrasi dan distribusi hadiah perayaan.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Cetak Laporan
        </button>
        <a href="<?= $baseUrl ?>/export-participants.php" class="btn btn-bni-orange">
            <i class="fa-solid fa-file-excel me-1"></i> Download Rekap Lengkap
        </a>
    </div>
</div>

<!-- Key Metrics Grid -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-orange"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['total_participants']) ?></div>
                <div class="stat-label">Total Undangan</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-teal"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <div class="stat-value text-success"><?= number_format($stats['hadir']) ?></div>
                <div class="stat-label">Total Hadir (<?= $stats['kehadiran_persen'] ?>%)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-gold"><i class="fa-solid fa-gift"></i></div>
            <div>
                <div class="stat-value text-warning"><?= number_format($stats['total_prize_quota']) ?></div>
                <div class="stat-label">Total Kuota Hadiah</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-navy"><i class="fa-solid fa-trophy"></i></div>
            <div>
                <div class="stat-value text-primary"><?= number_format($stats['total_winners']) ?></div>
                <div class="stat-label">Pemenang Terpilih</div>
            </div>
        </div>
    </div>
</div>

<!-- Reports Section -->
<div class="row g-4 mb-4">
    <!-- Division Breakdown -->
    <div class="col-lg-6">
        <div class="bni-card p-0 overflow-hidden shadow-sm h-100">
            <div class="p-3 bg-light border-bottom">
                <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-building me-2 text-primary"></i>Tingkat Kehadiran per Divisi</h5>
            </div>
            <div class="table-responsive">
                <table class="bni-table">
                    <thead>
                        <tr>
                            <th>Divisi</th>
                            <th class="text-center">Undangan</th>
                            <th class="text-center">Hadir</th>
                            <th class="text-center">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($divisionReport as $dr): ?>
                            <?php $p = $dr['total'] > 0 ? round(($dr['hadir'] / $dr['total']) * 100, 1) : 0; ?>
                            <tr>
                                <td class="fw-bold small"><?= e($dr['divisi']) ?></td>
                                <td class="text-center"><?= $dr['total'] ?></td>
                                <td class="text-center text-success fw-bold"><?= $dr['hadir'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $p >= 80 ? 'success' : ($p >= 50 ? 'warning' : 'danger') ?>"><?= $p ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Prize Breakdown -->
    <div class="col-lg-6">
        <div class="bni-card p-0 overflow-hidden shadow-sm h-100">
            <div class="p-3 bg-light border-bottom">
                <h5 class="fw-bold text-navy mb-0"><i class="fa-solid fa-gifts me-2 text-warning"></i>Distribusi Hadiah Undian</h5>
            </div>
            <div class="table-responsive">
                <table class="bni-table">
                    <thead>
                        <tr>
                            <th>Kategori Hadiah</th>
                            <th class="text-center">Kuota</th>
                            <th class="text-center">Pemenang</th>
                            <th class="text-center">Sisa</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prizeReport as $pz): ?>
                            <?php 
                            $q = (int)$pz['jumlah_pemenang'];
                            $w = (int)$pz['winners_count'];
                            $rem = max(0, $q - $w);
                            ?>
                            <tr>
                                <td class="fw-bold small"><?= e($pz['nama_hadiah']) ?></td>
                                <td class="text-center"><?= $q ?></td>
                                <td class="text-center text-success fw-bold"><?= $w ?></td>
                                <td class="text-center text-danger fw-bold"><?= $rem ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $w >= $q ? 'secondary' : 'success' ?>"><?= $w >= $q ? 'SELESAI' : 'AKTIF' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/admin-footer.php'; ?>
