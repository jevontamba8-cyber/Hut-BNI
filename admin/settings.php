<?php
/**
 * BNI Anniversary Celebration
 * Event Settings Management (Super Admin Only)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin']);

$pageTitle = 'Pengaturan Acara';
$activeMenu = 'settings';
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $settingsToUpdate = [
        'event_title'    => trim($_POST['event_title'] ?? ''),
        'event_tagline'  => trim($_POST['event_tagline'] ?? ''),
        'event_date'     => trim($_POST['event_date'] ?? ''),
        'event_time'     => trim($_POST['event_time'] ?? ''),
        'event_location' => trim($_POST['event_location'] ?? ''),
        'event_theme'    => trim($_POST['event_theme'] ?? ''),
        'company_name'   => trim($_POST['company_name'] ?? ''),
        'qr_base_url'    => trim($_POST['qr_base_url'] ?? ''),
        'win_rule'       => in_array($_POST['win_rule'] ?? '', ['one_win_only', 'multiple_allowed'], true) ? $_POST['win_rule'] : 'one_win_only'
    ];

    foreach ($settingsToUpdate as $key => $val) {
        updateSetting($key, $val);
    }

    logAudit($currentUser['id'], 'UPDATE_SETTINGS', 'Memperbarui konfigurasi utama acara BNI Anniversary');
    setFlash('success', 'Pengaturan acara berhasil disimpan.');
    header('Location: settings.php');
    exit;
}

$settings = getSettings();

include __DIR__ . '/admin-header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="bni-card shadow p-4 p-md-5">
            <div class="mb-4 pb-3 border-bottom">
                <h3 class="fw-bold text-navy mb-1"><i class="fa-solid fa-sliders me-2 text-warning"></i>Konfigurasi Acara BNI Anniversary</h3>
                <p class="text-muted small mb-0">Informasi ini ditampilkan di Landing Page, Tiket Check-in, dan Magic Wheel.</p>
            </div>

            <form method="POST" action="settings.php">
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-navy">Judul Acara</label>
                    <input type="text" name="event_title" class="form-control form-control-lg fw-bold" value="<?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-navy">Tagline Resmi</label>
                    <input type="text" name="event_tagline" class="form-control" value="<?= e($settings['event_tagline'] ?? 'Celebrating Together, Growing Stronger') ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-navy">Tanggal Acara</label>
                        <input type="date" name="event_date" class="form-control" value="<?= e($settings['event_date'] ?? '2026-10-25') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-navy">Waktu Pelaksanaan</label>
                        <input type="text" name="event_time" class="form-control" value="<?= e($settings['event_time'] ?? '08:30 - 16:00 WIB') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-navy">Lokasi Venue Acara</label>
                    <input type="text" name="event_location" class="form-control" value="<?= e($settings['event_location'] ?? 'Grand Ballroom Hotel Indonesia Kempinski Jakarta') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-navy">Tema Perayaan HUT</label>
                    <input type="text" name="event_theme" class="form-control" value="<?= e($settings['event_theme'] ?? 'Synergy of Excellence & Digital Innovation') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-navy">Nama Perusahaan / Penyelenggara</label>
                    <input type="text" name="company_name" class="form-control" value="<?= e($settings['company_name'] ?? 'PT Bank Negara Indonesia (Persero) Tbk') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-navy">URL Target QR Code Registrasi</label>
                    <input type="url" name="qr_base_url" class="form-control" value="<?= e($settings['qr_base_url'] ?? getBaseUrl() . '/check-in.php') ?>" required>
                    <div class="form-text text-muted">URL ini yang akan dikodekan ke dalam lembar QR Code meja registrasi.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-navy">Aturan Kemenangan Undian Magic Wheel</label>
                    <select name="win_rule" class="form-select">
                        <option value="one_win_only" <?= ($settings['win_rule'] ?? '') === 'one_win_only' ? 'selected' : '' ?>>
                            Satu Peserta Hanya Boleh Menang 1 Kali Selama Acara (Default Rekomendasi)
                        </option>
                        <option value="multiple_allowed" <?= ($settings['win_rule'] ?? '') === 'multiple_allowed' ? 'selected' : '' ?>>
                            Peserta Boleh Menang Lebih Dari 1 Kali di Kategori Hadiah Berbeda
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-bni-orange btn-lg px-4 shadow">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Seluruh Pengaturan
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/admin-footer.php'; ?>
