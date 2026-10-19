<?php
/**
 * BNI Anniversary Celebration
 * Check-In Success Page - Digital Event Pass & Boarding Badge
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Check-in Berhasil';
$activeNav = 'checkin';
$settings = getSettings();
$baseUrl = getBaseUrl();

// Retrieve checked-in participant from session
$participant = $_SESSION['checked_in_participant'] ?? null;

// Fallback if accessed directly by ID with validation
if (!$participant && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db = getDB();
    $stmt = $db->prepare('SELECT id, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran, waktu_checkin FROM participants WHERE id = ? AND status_kehadiran = "HADIR" LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $participant = [
            'id'            => $row['id'],
            'nama_lengkap'  => $row['nama_lengkap'],
            'nomor_undian'  => $row['nomor_undian'],
            'divisi'        => $row['divisi'],
            'cabang'        => $row['cabang'],
            'waktu_checkin' => formatIndonesianDate($row['waktu_checkin']),
            'status'        => 'HADIR'
        ];
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <?php if ($participant): ?>
                <!-- Digital Event Badge Card -->
                <div class="checkin-pass shadow-lg">
                    <div class="checkin-pass-header">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white text-success shadow" style="width: 72px; height: 72px;">
                                <i class="fa-solid fa-check fs-1"></i>
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1">Check-in Berhasil!</h2>
                        <p class="small text-white-50 mb-0">Tiket Kehadiran & Doorprize Resmi BNI</p>
                    </div>

                    <div class="checkin-pass-body">
                        <!-- Welcome message -->
                        <div class="text-center mb-4">
                            <h4 class="fw-bold text-navy mb-2"><?= e($participant['nama_lengkap']) ?></h4>
                            <p class="text-muted small px-2">
                                Selamat datang di <strong><?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?></strong>. Terima kasih telah hadir dan merayakan momen istimewa ini bersama kami.
                            </p>
                        </div>

                        <!-- Lottery Number Badge -->
                        <div class="lottery-badge">
                            <div class="small text-muted fw-bold text-uppercase letter-spacing-1">Nomor Undian Door Prize Anda</div>
                            <div class="lottery-badge-number"><?= e($participant['nomor_undian']) ?></div>
                            <div class="small text-success fw-bold mt-1">
                                <i class="fa-solid fa-circle-check me-1"></i> Terdaftar di Magic Wheel
                            </div>
                        </div>

                        <!-- Attendee Info Summary -->
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="info-row">
                                <span class="info-label"><i class="fa-solid fa-building me-2 text-primary"></i>Divisi / Unit</span>
                                <span class="info-value"><?= e($participant['divisi']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fa-solid fa-map-location-dot me-2 text-primary"></i>Cabang / Lokasi</span>
                                <span class="info-value"><?= e($participant['cabang']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fa-solid fa-clock me-2 text-primary"></i>Waktu Check-in</span>
                                <span class="info-value text-success"><?= e($participant['waktu_checkin']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fa-solid fa-signal me-2 text-primary"></i>Status Kehadiran</span>
                                <span class="badge-hadir">HADIR</span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-column gap-2">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="window.print()">
                                <i class="fa-solid fa-print me-2"></i> Cetak / Simpan Tiket
                            </button>
                            <a href="<?= $baseUrl ?>/index.php" class="btn btn-bni-orange w-100 justify-content-center">
                                <i class="fa-solid fa-house me-2"></i> Kembali ke Beranda
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- No active checkin session -->
                <div class="bni-card text-center p-5 shadow">
                    <div class="stat-icon-wrapper stat-icon-orange mx-auto mb-3">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <h3 class="fw-bold text-navy mb-2">Tidak Ada Sesi Check-in Aktif</h3>
                    <p class="text-muted mb-4">
                        Silakan lakukan check-in terlebih dahulu dengan memindai QR Code atau memasukkan NIK pegawai Anda.
                    </p>
                    <a href="<?= $baseUrl ?>/check-in.php" class="btn btn-bni-orange px-4">
                        <i class="fa-solid fa-qrcode me-2"></i> Menuju Form Check-in
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($participant): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Fire celebratory confetti on page load!
    if (window.fireConfetti) {
        window.fireConfetti(120);
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
