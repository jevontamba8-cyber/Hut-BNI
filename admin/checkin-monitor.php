<?php
/**
 * BNI Anniversary Celebration
 * Live Check-in Monitor for Registration Desk Panitia
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin', 'panitia', 'operator']);

$pageTitle = 'Monitor Check-in Real-time';
$activeMenu = 'checkin-monitor';
$stats = getEventStats();
$baseUrl = getBaseUrl();
$db = getDB();

// Fetch last 30 checked in participants
$stmt = $db->query("
    SELECT id, nomor_undian, nama_lengkap, divisi, cabang, waktu_checkin 
    FROM participants 
    WHERE status_kehadiran = 'HADIR' 
    ORDER BY waktu_checkin DESC LIMIT 50
");
$recentAttendees = $stmt->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<!-- Header with Live Pulse -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1 d-flex align-items-center gap-2">
            <span class="spinner-grow spinner-grow-sm text-success" role="status"></span>
            Monitor Registrasi & Check-in Langsung
        </h3>
        <p class="text-muted small mb-0">Halaman ini otomatis memperbarui daftar kehadiran setiap 5 detik.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/print-qr.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-qrcode me-1"></i> Buka QR Meja
        </a>
        <a href="<?= $baseUrl ?>/check-in.php" target="_blank" class="btn btn-bni-orange">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Halaman Check-in Peserta
        </a>
    </div>
</div>

<!-- Stats Bar -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-orange"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="stat-value" id="mon-total"><?= number_format($stats['total_participants']) ?></div>
                <div class="stat-label">Total Peserta</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-teal"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <div class="stat-value text-success" id="mon-hadir"><?= number_format($stats['hadir']) ?></div>
                <div class="stat-label">Telah Hadir</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-navy"><i class="fa-solid fa-user-xmark"></i></div>
            <div>
                <div class="stat-value text-danger" id="mon-belum"><?= number_format($stats['belum_hadir']) ?></div>
                <div class="stat-label">Belum Hadir</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-icon-gold"><i class="fa-solid fa-chart-pie"></i></div>
            <div>
                <div class="stat-value text-primary" id="mon-persen"><?= $stats['kehadiran_persen'] ?>%</div>
                <div class="stat-label">Tingkat Kehadiran</div>
            </div>
        </div>
    </div>
</div>

<!-- Live Attendees Table -->
<div class="bni-card p-0 overflow-hidden shadow-sm">
    <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-bold text-navy">
            <i class="fa-solid fa-clock-rotate-left me-2 text-warning"></i> Antrean Kedatangan Terbaru
        </h5>
        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-2 rounded-pill">
            <i class="fa-solid fa-circle-dot me-1"></i> Live Auto-Sync
        </span>
    </div>

    <div class="table-responsive">
        <table class="bni-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 60px;">No</th>
                    <th>Nama Lengkap</th>
                    <th class="text-center">No. Undian</th>
                    <th>Divisi</th>
                    <th>Cabang</th>
                    <th>Waktu Check-in</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody id="liveAttendeesBody">
                <?php if (empty($recentAttendees)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            Belum ada peserta yang check-in.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentAttendees as $idx => $a): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                            <td><strong class="text-navy"><?= e($a['nama_lengkap']) ?></strong></td>
                            <td class="text-center">
                                <span class="badge bg-primary font-monospace px-2 py-1"><?= e($a['nomor_undian']) ?></span>
                            </td>
                            <td><?= e($a['divisi']) ?></td>
                            <td><?= e($a['cabang']) ?></td>
                            <td class="text-success fw-semibold small">
                                <i class="fa-regular fa-clock me-1"></i> <?= formatIndonesianDate($a['waktu_checkin']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-hadir"><i class="fa-solid fa-check"></i> HADIR</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Auto-refresh stats every 5 seconds
setInterval(async () => {
    try {
        const res = await fetch('<?= $baseUrl ?>/api/stats.php');
        const data = await res.json();
        if (data.success && data.stats) {
            document.getElementById('mon-total').innerText = Number(data.stats.total_participants).toLocaleString();
            document.getElementById('mon-hadir').innerText = Number(data.stats.hadir).toLocaleString();
            document.getElementById('mon-belum').innerText = Number(data.stats.belum_hadir).toLocaleString();
            document.getElementById('mon-persen').innerText = data.stats.kehadiran_persen + '%';
        }
    } catch (e) {
        // silent fail
    }
}, 5000);
</script>

<?php include __DIR__ . '/admin-footer.php'; ?>
