<?php
/**
 * BNI Anniversary Celebration
 * Draw History & Results Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin', 'operator']);

$pageTitle = 'Riwayat Undian';
$activeMenu = 'draw-history';
$db = getDB();
$currentUser = getCurrentUser();

// Handle Cancellation of a draw result (e.g. if winner is absent or disqualified)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel_draw' && hasRole(['superadmin', 'admin'])) {
        $drawId = (int)($_POST['draw_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Peserta tidak hadir saat dipanggil');

        try {
            $stmt = $db->prepare("UPDATE draw_results SET status_hasil = 'DIBATALKAN' WHERE id = ?");
            $stmt->execute([$drawId]);

            // Reactivate prize if it was marked SELESAI
            $prizeStmt = $db->prepare("
                UPDATE prizes 
                SET status_hadiah = 'AKTIF' 
                WHERE id = (SELECT prize_id FROM draw_results WHERE id = ?)
            ");
            $prizeStmt->execute([$drawId]);

            logAudit($currentUser['id'], 'CANCEL_DRAW_RESULT', "Membatalkan hasil undian id $drawId dengan alasan: $reason");
            setFlash('warning', 'Hasil undian berhasil dibatalkan. Kuota hadiah telah dibuka kembali.');
        } catch (Exception $e) {
            setFlash('danger', 'Gagal membatalkan hasil: ' . $e->getMessage());
        }
        header('Location: draw-history.php');
        exit;
    }
}

// Filters
$prizeFilter = isset($_GET['prize_id']) ? (int)$_GET['prize_id'] : 0;
$statusFilter = trim($_GET['status'] ?? 'SAH');
$search = trim($_GET['search'] ?? '');

$prizes = $db->query("SELECT id, nama_hadiah FROM prizes ORDER BY urutan ASC")->fetchAll();

$sql = "
    SELECT 
        dr.id AS draw_id,
        dr.draw_session_id,
        dr.waktu_undian,
        dr.status_hasil,
        p.id AS participant_id,
        p.nama_lengkap,
        p.nomor_undian,
        p.nik,
        p.divisi,
        p.cabang,
        pr.id AS prize_id,
        pr.nama_hadiah
    FROM draw_results dr
    JOIN participants p ON dr.participant_id = p.id
    JOIN prizes pr ON dr.prize_id = pr.id
    WHERE 1=1
";
$params = [];

if ($prizeFilter > 0) {
    $sql .= " AND dr.prize_id = ? ";
    $params[] = $prizeFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND dr.status_hasil = ? ";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (p.nama_lengkap LIKE ? OR p.nomor_undian LIKE ? OR p.nik LIKE ?) ";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY dr.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$draws = $stmt->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1">Riwayat Pengundian Magic Wheel</h3>
        <p class="text-muted small mb-0">Seluruh catatan kemenangan tersimpan permanen di database MySQL.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/export-winners.php?prize_id=<?= $prizeFilter ?>" class="btn btn-outline-success">
            <i class="fa-solid fa-file-excel me-1"></i> Export Excel / CSV
        </a>
        <a href="<?= $baseUrl ?>/winners.php" target="_blank" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Halaman Publik
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="bni-card p-3 mb-4">
    <form method="GET" action="draw-history.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Cari nama, no undian, NIK..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="prize_id" class="form-select">
                <option value="0">-- Semua Hadiah --</option>
                <?php foreach ($prizes as $pr): ?>
                    <option value="<?= $pr['id'] ?>" <?= $prizeFilter === (int)$pr['id'] ? 'selected' : '' ?>>
                        <?= e($pr['nama_hadiah']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">-- Semua Status --</option>
                <option value="SAH" <?= $statusFilter === 'SAH' ? 'selected' : '' ?>>SAH</option>
                <option value="DIBATALKAN" <?= $statusFilter === 'DIBATALKAN' ? 'selected' : '' ?>>DIBATALKAN</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-bni-orange flex-grow-1">Filter</button>
            <a href="draw-history.php" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </form>
</div>

<!-- History Table -->
<div class="bni-card p-0 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="bni-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">ID</th>
                    <th>Hadiah</th>
                    <th>Nama Lengkap Pemenang</th>
                    <th class="text-center">No. Undian</th>
                    <th>Divisi & Cabang</th>
                    <th>Waktu Undian</th>
                    <th class="text-center">Status</th>
                    <?php if (hasRole(['superadmin', 'admin'])): ?>
                        <th class="text-center" style="width: 100px;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($draws)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            Belum ada riwayat undian yang sesuai filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($draws as $d): ?>
                        <tr class="<?= $d['status_hasil'] === 'DIBATALKAN' ? 'table-light text-muted' : '' ?>">
                            <td class="text-center small text-muted"><?= $d['draw_id'] ?></td>
                            <td>
                                <strong class="text-navy"><?= e($d['nama_hadiah']) ?></strong>
                                <div class="text-muted small font-monospace"><?= e($d['draw_session_id']) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold fs-6 <?= $d['status_hasil'] === 'DIBATALKAN' ? 'text-decoration-line-through text-muted' : 'text-dark' ?>">
                                    <?= e($d['nama_lengkap']) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary font-monospace px-2 py-1"><?= e($d['nomor_undian']) ?></span>
                            </td>
                            <td class="small">
                                <div><?= e($d['divisi']) ?></div>
                                <div class="text-muted"><?= e($d['cabang']) ?></div>
                            </td>
                            <td class="small text-muted">
                                <?= formatIndonesianDate($d['waktu_undian']) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($d['status_hasil'] === 'SAH'): ?>
                                    <span class="badge bg-success px-3 py-1 rounded-pill">SAH</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-1 rounded-pill">BATAL</span>
                                <?php endif; ?>
                            </td>
                            <?php if (hasRole(['superadmin', 'admin'])): ?>
                                <td class="text-center">
                                    <?php if ($d['status_hasil'] === 'SAH'): ?>
                                        <form method="POST" action="draw-history.php" onsubmit="return confirm('Batalkan kemenangan <?= e($d['nama_lengkap']) ?>? Kuota hadiah akan dibuka kembali.')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="cancel_draw">
                                            <input type="hidden" name="draw_id" value="<?= $d['draw_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan Hasil Undian">
                                                <i class="fa-solid fa-ban me-1"></i> Batalkan
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/admin-footer.php'; ?>
