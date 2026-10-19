<?php
/**
 * BNI Anniversary Celebration
 * Global Public Footer
 */

declare(strict_types=1);

$settings = getSettings();
$baseUrl = getBaseUrl();
?>

<!-- Footer -->
<footer class="bni-footer">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-md-6 text-center text-md-start">
                <div class="d-flex align-items-center gap-3 justify-content-center justify-content-md-start mb-2">
                    <img src="<?= $baseUrl ?>/assets/images/bni-logo.svg" alt="BNI Logo" style="height: 32px; filter: brightness(0) invert(1);">
                    <div class="vr bg-secondary"></div>
                    <span class="fw-bold text-white"><?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?></span>
                </div>
                <p class="small text-white-50 mb-0">
                    &copy; <?= date('Y') ?> <?= e($settings['company_name'] ?? 'PT Bank Negara Indonesia (Persero) Tbk') ?>. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="small text-white-50">
                    <div><i class="fa-regular fa-calendar me-2 text-warning"></i><?= formatIndonesianDate($settings['event_date'] ?? '2026-10-25', false) ?> (<?= e($settings['event_time'] ?? '08:30 WIB') ?>)</div>
                    <div><i class="fa-solid fa-location-dot me-2 text-warning"></i><?= e($settings['event_location'] ?? 'Grand Ballroom Kempinski Jakarta') ?></div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Audio Synthesizer & Confetti -->
<script src="<?= $baseUrl ?>/assets/js/audio-synth.js"></script>
<script src="<?= $baseUrl ?>/assets/js/confetti.browser.min.js"></script>

<!-- Global Main JS -->
<script src="<?= $baseUrl ?>/assets/js/main.js"></script>

</body>
</html>
