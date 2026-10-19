<?php
/**
 * BNI Anniversary Celebration
 * Participant QR Code & NIK Check-In Page
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Check-in Peserta';
$activeNav = 'checkin';
$settings = getSettings();
$baseUrl = getBaseUrl();

include __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <!-- Main Check-in Card -->
            <div class="bni-card shadow-lg p-4 p-md-5 position-relative overflow-hidden">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-orange bg-opacity-10 text-orange mb-3" style="background: rgba(243, 112, 33, 0.1);">
                        <i class="fa-solid fa-qrcode fs-1" style="color: var(--bni-orange);"></i>
                    </div>
                    <h2 class="fw-bold text-navy mb-1">Check-in Kehadiran</h2>
                    <p class="text-muted small">
                        Masukkan 16 digit NIK Anda untuk memvalidasi data dan mengonfirmasi kehadiran di <strong><?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?></strong>.
                    </p>
                </div>

                <!-- Alert Message Box -->
                <div id="checkinAlert" class="alert d-none d-flex align-items-center gap-2 mb-4" role="alert">
                    <i id="alertIcon" class="fa-solid fs-5"></i>
                    <div id="alertMsg" class="small"></div>
                </div>

                <!-- STEP 1: Enter NIK Form -->
                <form id="nikForm" onsubmit="handleValidateNik(event)">
                    <?= csrfField() ?>
                    <div class="mb-4">
                        <label for="nikInput" class="form-label fw-bold text-navy">
                            <i class="fa-solid fa-id-card me-1 text-warning"></i> Nomor Induk Kependudukan (NIK)
                        </label>
                        <div class="input-group input-group-lg">
                            <input 
                                type="text" 
                                class="form-control fw-bold letter-spacing-1 text-center" 
                                id="nikInput" 
                                name="nik" 
                                placeholder="16 Digit NIK Pegawai" 
                                maxlength="16" 
                                pattern="[0-9]{16}" 
                                inputmode="numeric" 
                                required 
                                autocomplete="off"
                                autofocus
                            >
                            <button class="btn btn-bni-orange px-4" type="submit" id="btnValidate">
                                <span id="btnValidateSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                                <span id="btnValidateText"><i class="fa-solid fa-magnifying-glass me-1"></i> Periksa</span>
                            </button>
                        </div>
                        <div class="form-text text-muted mt-2">
                            <i class="fa-solid fa-shield-halved text-success me-1"></i> Data Anda dienkripsi dan dilindungi. NIK tidak akan dipublikasikan.
                        </div>
                    </div>
                </form>

                <!-- STEP 2: Identity Confirmation Card (Hidden by default) -->
                <div id="confirmCard" class="d-none">
                    <div class="border rounded-4 p-4 mb-4 bg-light shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <span class="badge bg-success-subtle text-success fw-bold px-3 py-2 rounded-pill">
                                <i class="fa-solid fa-user-check me-1"></i> Data Ditemukan
                            </span>
                            <span class="badge bg-warning-subtle text-warning-emphasis fw-bold px-3 py-2 rounded-pill">
                                Belum Check-in
                            </span>
                        </div>

                        <div class="lottery-badge my-3">
                            <div class="small text-muted fw-bold text-uppercase">Nomor Undian Anda</div>
                            <div class="lottery-badge-number" id="previewNomorUndian">-</div>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Nama Lengkap</span>
                            <span class="info-value" id="previewNama">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">NIK Terdaftar</span>
                            <span class="info-value text-muted font-monospace" id="previewMaskedNik">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Divisi / Unit</span>
                            <span class="info-value" id="previewDivisi">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Cabang / Wilayah</span>
                            <span class="info-value" id="previewCabang">-</span>
                        </div>
                    </div>

                    <form id="confirmForm" onsubmit="handleConfirmCheckin(event)">
                        <input type="hidden" id="confirmedNik" name="nik" value="">
                        <input type="hidden" id="confirmedCsrf" name="csrf_token" value="">
                        <button type="submit" class="btn btn-bni-orange btn-lg w-100 justify-content-center shadow mb-2" id="btnConfirm">
                            <span id="btnConfirmSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            <span id="btnConfirmText"><i class="fa-solid fa-circle-check me-2"></i> Konfirmasi Kehadiran Saya</span>
                        </button>
                        <button type="button" class="btn btn-link text-muted btn-sm w-100 mt-2" onclick="resetForm()">
                            <i class="fa-solid fa-arrow-left me-1"></i> Cari NIK Lain
                        </button>
                    </form>
                </div>

                <!-- Assistance box -->
                <div class="text-center mt-4 pt-3 border-top">
                    <p class="small text-muted mb-0">
                        Mengalami kendala check-in? Silakan hubungi <strong>Panitia Registrasi BNI</strong> di meja resepsionis depan ballroom.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentValidatedData = null;

function showAlert(type, message) {
    const box = document.getElementById('checkinAlert');
    const icon = document.getElementById('alertIcon');
    const text = document.getElementById('alertMsg');

    box.className = `alert alert-${type} d-flex align-items-center gap-2 mb-4 shadow-sm`;
    icon.className = `fa-solid ${type === 'success' ? 'fa-circle-check text-success' : (type === 'warning' ? 'fa-triangle-exclamation text-warning' : 'fa-circle-exclamation text-danger')} fs-5`;
    text.innerText = message;
    box.classList.remove('d-none');
}

function hideAlert() {
    document.getElementById('checkinAlert').classList.add('d-none');
}

function resetForm() {
    hideAlert();
    document.getElementById('confirmCard').classList.add('d-none');
    document.getElementById('nikForm').classList.remove('d-none');
    document.getElementById('nikInput').value = '';
    document.getElementById('nikInput').focus();
    currentValidatedData = null;
}

async function handleValidateNik(e) {
    e.preventDefault();
    hideAlert();

    const nik = document.getElementById('nikInput').value.trim();
    if (!/^[0-9]{16}$/.test(nik)) {
        showAlert('danger', 'Format NIK tidak valid. Pastikan Anda memasukkan tepat 16 digit angka.');
        return;
    }

    const btn = document.getElementById('btnValidate');
    const spinner = document.getElementById('btnValidateSpinner');
    const btnText = document.getElementById('btnValidateText');

    btn.disabled = true;
    spinner.classList.remove('d-none');
    btnText.classList.add('d-none');

    try {
        const response = await fetch('<?= $baseUrl ?>/api/validate-nik.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nik: nik })
        });

        const res = await response.json();

        if (res.success && res.status === 'READY_TO_CONFIRM') {
            currentValidatedData = res.participant;
            document.getElementById('previewNama').innerText = res.participant.nama_lengkap;
            document.getElementById('previewNomorUndian').innerText = res.participant.nomor_undian;
            document.getElementById('previewMaskedNik').innerText = res.participant.masked_nik;
            document.getElementById('previewDivisi').innerText = res.participant.divisi;
            document.getElementById('previewCabang').innerText = res.participant.cabang;

            document.getElementById('confirmedNik').value = nik;
            document.getElementById('confirmedCsrf').value = res.csrf_token;

            document.getElementById('nikForm').classList.add('d-none');
            document.getElementById('confirmCard').classList.remove('d-none');
        } else if (res.status === 'ALREADY_CHECKED_IN') {
            showAlert('warning', `Anda sudah melakukan check-in pada ${res.participant.waktu_checkin}. Nomor Undian Anda: ${res.participant.nomor_undian}`);
        } else {
            showAlert('danger', res.message || 'NIK tidak terdaftar dalam database peserta.');
        }
    } catch (err) {
        console.error(err);
        showAlert('danger', 'Gagal menghubungi server. Mohon periksa koneksi Anda dan coba kembali.');
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
        btnText.classList.remove('d-none');
    }
}

async function handleConfirmCheckin(e) {
    e.preventDefault();
    hideAlert();

    const nik = document.getElementById('confirmedNik').value;
    const csrf = document.getElementById('confirmedCsrf').value;

    const btn = document.getElementById('btnConfirm');
    const spinner = document.getElementById('btnConfirmSpinner');
    const btnText = document.getElementById('btnConfirmText');

    btn.disabled = true;
    spinner.classList.remove('d-none');
    btnText.classList.add('d-none');

    try {
        const response = await fetch('<?= $baseUrl ?>/api/confirm-checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nik: nik, csrf_token: csrf })
        });

        const res = await response.json();

        if (res.success && res.redirect_url) {
            // Check-in confirmed! Redirect to success ticket page
            window.location.href = res.redirect_url;
        } else {
            showAlert('danger', res.message || 'Gagal mengonfirmasi check-in.');
            btn.disabled = false;
            spinner.classList.add('d-none');
            btnText.classList.remove('d-none');
        }
    } catch (err) {
        console.error(err);
        showAlert('danger', 'Terjadi kesalahan sistem saat konfirmasi kehadiran.');
        btn.disabled = false;
        spinner.classList.add('d-none');
        btnText.classList.remove('d-none');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
