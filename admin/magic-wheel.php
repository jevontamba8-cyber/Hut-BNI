<?php
/**
 * BNI Anniversary Celebration
 * Magic Wheel Arena - Live Lottery Draw
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin', 'operator']);

$pageTitle = 'Magic Wheel Undian';
$activeMenu = 'magic-wheel';
$baseUrl = getBaseUrl();
$db = getDB();
$settings = getSettings();

// Fetch active prizes
$prizesStmt = $db->query("
    SELECT 
        pr.*, 
        COUNT(CASE WHEN dr.status_hasil = 'SAH' THEN 1 END) AS winners_so_far
    FROM prizes pr
    LEFT JOIN draw_results dr ON pr.id = dr.prize_id
    WHERE pr.status_hadiah = 'AKTIF'
    GROUP BY pr.id
    ORDER BY pr.urutan ASC
");
$availablePrizes = $prizesStmt->fetchAll();

// Eligible participants count
$winRule = getSetting('win_rule', 'one_win_only');
$countSql = "SELECT COUNT(*) FROM participants WHERE status_kehadiran = 'HADIR'";
if ($winRule === 'one_win_only') {
    $countSql .= " AND id NOT IN (SELECT participant_id FROM draw_results WHERE status_hasil = 'SAH')";
}
$eligibleCount = (int)$db->query($countSql)->fetchColumn();

include __DIR__ . '/admin-header.php';
?>

<div class="row g-4 align-items-start">
    <!-- Wheel Stage (Main Arena) -->
    <div class="col-xl-9 col-lg-8">
        <div class="wheel-stage shadow-lg">
            <!-- Stage Top Bar (Presentation Mode Controls) -->
            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill shadow-sm">
                        <i class="fa-solid fa-sparkles me-1"></i> BNI MAGIC WHEEL
                    </span>
                    <span class="text-white-50 small d-none d-sm-inline">
                        <i class="fa-solid fa-users me-1 text-warning"></i> <strong class="text-white" id="eligibleCountLabel"><?= number_format($eligibleCount) ?></strong> Peserta Berhak Diundi
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light" id="btnToggleSound" onclick="toggleMute()" title="Nyalakan / Matikan Suara">
                        <i class="fa-solid fa-volume-high" id="soundIcon"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="togglePresentationMode()" title="Mode Presentasi Layar Penuh">
                        <i class="fa-solid fa-expand me-1"></i> Mode Presentasi
                    </button>
                </div>
            </div>

            <!-- Prize Selector Ribbon -->
            <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-20 mb-4">
                <div class="row align-items-center g-3">
                    <div class="col-md-3">
                        <label for="selectPrize" class="form-label text-warning small fw-bold text-uppercase mb-0">
                            <i class="fa-solid fa-gift me-1"></i> Pilih Kategori Hadiah:
                        </label>
                    </div>
                    <div class="col-md-9">
                        <select id="selectPrize" class="form-select form-select-lg fw-bold text-navy" onchange="handlePrizeChange()">
                            <?php if (empty($availablePrizes)): ?>
                                <option value="0">-- Semua Hadiah Telah Selesai Diundi --</option>
                            <?php else: ?>
                                <?php foreach ($availablePrizes as $ap): ?>
                                    <option value="<?= $ap['id'] ?>" data-quota="<?= $ap['jumlah_pemenang'] ?>" data-won="<?= $ap['winners_so_far'] ?>">
                                        <?= e($ap['nama_hadiah']) ?> (Progres: <?= $ap['winners_so_far'] ?> / <?= $ap['jumlah_pemenang'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- The Big Wheel Canvas Container -->
            <div class="wheel-container my-3">
                <!-- Needle Flapper at 12 o'clock -->
                <svg class="wheel-pointer" viewBox="0 0 48 60">
                    <polygon points="24,58 6,6 42,6" fill="#FFD700" stroke="#FFFFFF" stroke-width="3" />
                    <circle cx="24" cy="14" r="8" fill="#F37021" stroke="#FFFFFF" stroke-width="2" />
                </svg>

                <!-- Outer decorative golden ring -->
                <div class="wheel-outer-ring"></div>

                <!-- Canvas -->
                <canvas id="wheelCanvas" width="600" height="600"></canvas>

                <!-- Center Hub Spin Trigger -->
                <div class="wheel-center-hub" onclick="handleSpinClick()" title="Klik untuk Putar!">
                    <i class="fa-solid fa-arrows-spin fs-2 text-white mb-1"></i>
                    <span class="wheel-center-text">SPIN</span>
                </div>
            </div>

            <!-- Spin Action Bar -->
            <div class="spin-action-bar">
                <button type="button" id="btnSpinNow" class="btn-spin-now" onclick="handleSpinClick()">
                    <i class="fa-solid fa-play"></i> SPIN NOW
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar: Real-time Live Winners Feed -->
    <div class="col-xl-3 col-lg-4 hide-on-presentation">
        <div class="bni-card shadow-sm p-3 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <h6 class="fw-bold text-navy mb-0">
                    <i class="fa-solid fa-trophy me-2 text-warning"></i> Pemenang Terbaru
                </h6>
                <a href="<?= $baseUrl ?>/admin/draw-history.php" class="small fw-bold text-primary">Semua</a>
            </div>
            <div id="sidebarWinnersList" class="d-flex flex-column gap-2" style="max-height: 480px; overflow-y: auto;">
                <div class="text-center py-4 text-muted small" id="sidebarWinnersEmpty">
                    Memuat data pemenang...
                </div>
            </div>
        </div>

        <div class="bni-card bg-light border p-3">
            <h6 class="fw-bold text-navy mb-2"><i class="fa-solid fa-shield-halved text-success me-2"></i>Integritas Pengundian</h6>
            <p class="small text-muted mb-0">
                Pemenang dipilih secara acak murni oleh server PHP melalui transaksi database aman. Hanya peserta yang telah check-in yang memenuhi syarat diundi.
            </p>
        </div>
    </div>
</div>

<!-- Giant Winner Celebration Modal Overlay -->
<div id="winnerOverlay" class="winner-overlay">
    <div class="winner-card">
        <div class="winner-trophy-icon">
            <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="small text-uppercase text-warning fw-bold letter-spacing-1 mb-1">SELAMAT KEPADA PEMENANG!</div>
        <div class="winner-badge-prize" id="modalPrizeName">Logam Mulia Antam 50g</div>
        
        <div class="winner-name-huge" id="modalWinnerName">Jevon Hot Asi Alexander Tamba</div>
        <div class="winner-lottery-pill">
            <i class="fa-solid fa-ticket me-2"></i><span id="modalLotteryNum">BNI-0001</span>
        </div>

        <div class="winner-details-grid">
            <div>
                <div class="small text-white-50">Divisi / Unit:</div>
                <div class="fw-bold text-white text-truncate" id="modalDivisi">-</div>
            </div>
            <div>
                <div class="small text-white-50">Cabang / Wilayah:</div>
                <div class="fw-bold text-white text-truncate" id="modalCabang">-</div>
            </div>
        </div>

        <div class="d-flex gap-3 justify-content-center">
            <button type="button" class="btn btn-bni-gold btn-lg px-4" onclick="closeWinnerOverlay()">
                <i class="fa-solid fa-rotate-right me-1"></i> Lanjut Putaran Berikutnya
            </button>
            <a href="<?= $baseUrl ?>/winners.php" target="_blank" class="btn btn-outline-light btn-lg px-3">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
            </a>
        </div>
    </div>
</div>

<script>
let magicWheel = null;
let csrfToken = <?= json_encode(generateCsrfToken()) ?>;
let eligibleParticipants = [];

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Initialize Wheel Engine
    magicWheel = new MagicWheel('wheelCanvas');

    // 2. Load Eligible Participants from MySQL
    await loadEligibleParticipants();

    // 3. Load Recent Winners Feed
    loadSidebarWinners();
});

async function loadEligibleParticipants() {
    try {
        const res = await fetch('<?= $baseUrl ?>/api/get-eligible-participants.php');
        const data = await res.json();
        if (data.success) {
            eligibleParticipants = data.participants || [];
            document.getElementById('eligibleCountLabel').innerText = Number(data.count).toLocaleString();
            if (magicWheel) {
                magicWheel.setParticipants(eligibleParticipants);
            }
        }
    } catch (e) {
        console.error('Error fetching eligible participants:', e);
    }
}

async function loadSidebarWinners() {
    try {
        const res = await fetch('<?= $baseUrl ?>/api/get-draw-results.php?limit=8');
        const data = await res.json();
        const list = document.getElementById('sidebarWinnersList');
        if (data.success && data.winners && data.winners.length > 0) {
            list.innerHTML = '';
            data.winners.forEach(w => {
                const item = document.createElement('div');
                item.className = 'p-2 rounded-3 bg-white border small shadow-xs';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-navy text-truncate">${w.nama_lengkap}</strong>
                        <span class="badge bg-primary font-monospace">${w.nomor_undian}</span>
                    </div>
                    <div class="text-muted" style="font-size: 0.78rem;">
                        <span class="text-warning fw-semibold">${w.nama_hadiah}</span> &bull; ${w.cabang}
                    </div>
                `;
                list.appendChild(item);
            });
        } else {
            list.innerHTML = '<div class="text-center py-4 text-muted small">Belum ada pemenang yang tercatat.</div>';
        }
    } catch (e) {
        console.error('Error loading sidebar winners:', e);
    }
}

function handlePrizeChange() {
    // Prize selected changed
}

async function handleSpinClick() {
    if (!magicWheel || magicWheel.isSpinning) return;

    const prizeSelect = document.getElementById('selectPrize');
    const prizeId = parseInt(prizeSelect.value);

    if (!prizeId || prizeId <= 0) {
        alert('Silakan pilih kategori hadiah yang akan diundi terlebih dahulu.');
        return;
    }

    if (eligibleParticipants.length === 0) {
        alert('Tidak ada peserta yang memenuhi syarat undian saat ini (Pastikan peserta sudah check-in).');
        return;
    }

    const spinBtn = document.getElementById('btnSpinNow');
    spinBtn.disabled = true;

    try {
        // CALL BACKEND TO SECURELY SELECT WINNER FIRST
        const response = await fetch('<?= $baseUrl ?>/api/draw-winner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prize_id: prizeId,
                csrf_token: csrfToken
            })
        });

        const res = await response.json();

        if (!res.success) {
            alert(res.message || 'Gagal melakukan pengundian.');
            spinBtn.disabled = false;
            return;
        }

        // Backend chose the winner successfully! Now run visual wheel animation toward this winner
        magicWheel.spinToWinner(res.winner, res.prize, (winner, prize) => {
            // Animation finished! Reveal winner popup
            showWinnerOverlay(winner, prize);
            spinBtn.disabled = false;
            // Reload participants and sidebar
            loadEligibleParticipants();
            loadSidebarWinners();
        });

    } catch (err) {
        console.error('Draw winner error:', err);
        alert('Terjadi kesalahan koneksi server saat pengundian.');
        spinBtn.disabled = false;
    }
}

function showWinnerOverlay(winner, prize) {
    document.getElementById('modalPrizeName').innerText = prize.nama_hadiah;
    document.getElementById('modalWinnerName').innerText = winner.nama_lengkap;
    document.getElementById('modalLotteryNum').innerText = winner.nomor_undian;
    document.getElementById('modalDivisi').innerText = winner.divisi;
    document.getElementById('modalCabang').innerText = winner.cabang;

    document.getElementById('winnerOverlay').classList.add('active');
}

function closeWinnerOverlay() {
    document.getElementById('winnerOverlay').classList.remove('active');
}

function toggleMute() {
    if (window.soundEngine) {
        const isMuted = window.soundEngine.toggleMute();
        const icon = document.getElementById('soundIcon');
        icon.className = isMuted ? 'fa-solid fa-volume-xmark text-danger' : 'fa-solid fa-volume-high';
    }
}

function togglePresentationMode() {
    document.body.classList.toggle('presentation-mode');
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    } else {
        document.documentElement.requestFullscreen().catch(() => {});
    }
    // Resize wheel canvas
    setTimeout(() => {
        if (magicWheel) magicWheel.resize();
    }, 200);
}
</script>

<?php include __DIR__ . '/admin-footer.php'; ?>
