<?php
/**
 * BNI Anniversary Celebration
 * Registration Desk QR Code Printable Stand / Poster
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin', 'panitia']);

$pageTitle = 'Cetak QR Code Registrasi';
$activeMenu = 'print-qr';
$settings = getSettings();
$baseUrl = getBaseUrl();

// Target URL for peserta scan
$checkinUrl = $settings['qr_base_url'] ?? ($baseUrl . '/check-in.php');

include __DIR__ . '/admin-header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <!-- Print Control Bar -->
        <div class="bni-card p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-navy mb-0">QR Code Meja Registrasi</h5>
                <div class="small text-muted">URL Target: <code><?= e($checkinUrl) ?></code></div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-bni-orange" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Cetak Lembar Meja (A4 / Stand)
                </button>
            </div>
        </div>

        <!-- Printable Poster Card -->
        <div class="printable-stand bni-card shadow-lg p-5 text-center border-3 border-warning bg-white position-relative" style="border-radius: 24px;">
            <!-- BNI Header -->
            <div class="mb-4">
                <img src="<?= $baseUrl ?>/assets/images/bni-logo.svg" alt="BNI Logo" style="height: 55px;" class="mb-2">
                <h2 class="fw-bold text-navy mb-1" style="font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;">
                    <?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?>
                </h2>
                <p class="text-muted fw-semibold mb-0">
                    “<?= e($settings['event_tagline'] ?? 'Celebrating Together, Growing Stronger') ?>”
                </p>
            </div>

            <!-- Stand Banner -->
            <div class="p-2 px-4 rounded-pill bg-warning bg-opacity-20 border border-warning d-inline-block mb-4">
                <span class="fw-bold text-dark text-uppercase letter-spacing-1">
                    <i class="fa-solid fa-qrcode me-2 text-warning"></i> SCAN DI SINI UNTUK CHECK-IN PESERTA
                </span>
            </div>

            <!-- Dynamic QR Code Container -->
            <div class="my-3 d-flex justify-content-center">
                <div class="p-3 bg-white border border-2 border-dark rounded-4 shadow-sm d-inline-block">
                    <div id="qrcodeCanvas"></div>
                </div>
            </div>

            <div class="text-center mt-3 mb-4">
                <div class="small fw-bold text-navy">Akses Langsung Melalui Browser:</div>
                <div class="small text-primary font-monospace fw-bold"><?= e($checkinUrl) ?></div>
            </div>

            <!-- Step by step instructions for attendees -->
            <div class="p-4 rounded-4 bg-light border text-start">
                <div class="fw-bold text-navy mb-2 text-center text-uppercase small letter-spacing-1">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i> Panduan Check-in 3 Langkah Mudah:
                </div>
                <div class="row g-2 text-center small">
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <div class="fw-bold text-navy mb-1">1. Scan QR</div>
                            <div class="text-muted">Arahkan kamera smartphone ke QR Code di atas.</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <div class="fw-bold text-navy mb-1">2. Masukkan NIK</div>
                            <div class="text-muted">Ketik 16 digit NIK pegawai Anda secara mandiri.</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <div class="fw-bold text-navy mb-1">3. Tiket Doorprize</div>
                            <div class="text-muted">Dapatkan nomor undian resmi Magic Wheel.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top small text-muted">
                <?= e($settings['event_location'] ?? 'Grand Ballroom Kempinski Jakarta') ?> &bull; <?= formatIndonesianDate($settings['event_date'] ?? '2026-10-25', false) ?>
            </div>
        </div>
    </div>
</div>

<!-- Standalone QR Code JS Generator via CDN with inline fallback -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const targetUrl = <?= json_encode($checkinUrl) ?>;
    const container = document.getElementById('qrcodeCanvas');

    if (typeof QRCode !== 'undefined') {
        new QRCode(container, {
            text: targetUrl,
            width: 250,
            height: 250,
            colorDark: "#0B2240",
            colorLight: "#FFFFFF",
            correctLevel: QRCode.CorrectLevel.H
        });
    } else {
        // Fallback to QR API image
        const img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(targetUrl);
        img.style.width = '250px';
        img.style.height = '250px';
        container.appendChild(img);
    }
});
</script>

<style>
@media print {
    .admin-sidebar, .admin-topbar, .btn, .bni-card.p-3.mb-4, footer {
        display: none !important;
    }
    .admin-content, .admin-body {
        padding: 0 !important;
        margin: 0 !important;
        background: #FFFFFF !important;
    }
    .printable-stand {
        box-shadow: none !important;
        border: 2px solid #000000 !important;
        width: 100% !important;
        page-break-after: always;
    }
}
</style>

<?php include __DIR__ . '/admin-footer.php'; ?>
