<?php
/**
 * BNI Anniversary Celebration
 * Prize Management & Win Rules Configuration
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin']);

$pageTitle = 'Manajemen Hadiah & Aturan';
$activeMenu = 'prizes';
$db = getDB();
$currentUser = getCurrentUser();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $action = $_POST['action'] ?? '';

    // Update Win Rule Setting
    if ($action === 'update_win_rule') {
        $rule = $_POST['win_rule'] === 'multiple_allowed' ? 'multiple_allowed' : 'one_win_only';
        updateSetting('win_rule', $rule);
        logAudit($currentUser['id'], 'UPDATE_WIN_RULE', "Mengubah aturan kemenangan menjadi: $rule");
        setFlash('success', 'Aturan kemenangan undian berhasil diperbarui.');
        header('Location: prizes.php');
        exit;
    }

    // Add Prize
    if ($action === 'create_prize') {
        $namaHadiah = trim($_POST['nama_hadiah'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $jumlahPemenang = max(1, (int)($_POST['jumlah_pemenang'] ?? 1));
        $urutan = max(1, (int)($_POST['urutan'] ?? 1));
        $status = in_array($_POST['status_hadiah'] ?? '', ['AKTIF', 'NONAKTIF', 'SELESAI'], true) ? $_POST['status_hadiah'] : 'AKTIF';

        if (empty($namaHadiah)) {
            setFlash('danger', 'Nama hadiah wajib diisi.');
        } else {
            $stmt = $db->prepare('INSERT INTO prizes (nama_hadiah, deskripsi, jumlah_pemenang, urutan, status_hadiah) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$namaHadiah, $deskripsi, $jumlahPemenang, $urutan, $status]);
            logAudit($currentUser['id'], 'CREATE_PRIZE', "Menambahkan hadiah baru: $namaHadiah (Kuota: $jumlahPemenang)");
            setFlash('success', "Hadiah '$namaHadiah' berhasil ditambahkan.");
        }
        header('Location: prizes.php');
        exit;
    }

    // Update Prize
    if ($action === 'update_prize') {
        $id = (int)($_POST['id'] ?? 0);
        $namaHadiah = trim($_POST['nama_hadiah'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $jumlahPemenang = max(1, (int)($_POST['jumlah_pemenang'] ?? 1));
        $urutan = max(1, (int)($_POST['urutan'] ?? 1));
        $status = in_array($_POST['status_hadiah'] ?? '', ['AKTIF', 'NONAKTIF', 'SELESAI'], true) ? $_POST['status_hadiah'] : 'AKTIF';

        $stmt = $db->prepare('UPDATE prizes SET nama_hadiah = ?, deskripsi = ?, jumlah_pemenang = ?, urutan = ?, status_hadiah = ? WHERE id = ?');
        $stmt->execute([$namaHadiah, $deskripsi, $jumlahPemenang, $urutan, $status, $id]);
        logAudit($currentUser['id'], 'UPDATE_PRIZE', "Mengubah data hadiah id $id: $namaHadiah");
        setFlash('success', "Data hadiah berhasil diperbarui.");
        header('Location: prizes.php');
        exit;
    }

    // Delete Prize
    if ($action === 'delete_prize') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM prizes WHERE id = ?');
        $stmt->execute([$id]);
        logAudit($currentUser['id'], 'DELETE_PRIZE', "Menghapus hadiah id $id");
        setFlash('success', 'Hadiah berhasil dihapus.');
        header('Location: prizes.php');
        exit;
    }
}

// Fetch Prizes with Win Counts
$prizesStmt = $db->query("
    SELECT 
        pr.*,
        COUNT(CASE WHEN dr.status_hasil = 'SAH' THEN 1 END) AS pemenang_terpilih
    FROM prizes pr
    LEFT JOIN draw_results dr ON pr.id = dr.prize_id
    GROUP BY pr.id
    ORDER BY pr.urutan ASC, pr.id ASC
");
$prizes = $prizesStmt->fetchAll();

$currentWinRule = getSetting('win_rule', 'one_win_only');

include __DIR__ . '/admin-header.php';
?>

<!-- Header Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1">Manajemen Hadiah & Doorprize</h3>
        <p class="text-muted small mb-0">Kelola kategori hadiah, kuota pemenang, dan aturan pengundian Magic Wheel.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/magic-wheel.php" class="btn btn-warning fw-bold">
            <i class="fa-solid fa-arrows-spin me-1"></i> Buka Arena Undian
        </a>
        <button class="btn btn-bni-orange" data-bs-toggle="modal" data-bs-target="#addPrizeModal">
            <i class="fa-solid fa-plus me-1"></i> Tambah Hadiah Baru
        </button>
    </div>
</div>

<!-- Win Rule Settings Box -->
<div class="bni-card mb-4 border-start border-4 border-warning p-4 bg-light shadow-sm">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h5 class="fw-bold text-navy mb-1"><i class="fa-solid fa-scale-balanced me-2 text-warning"></i>Aturan Kemenangan Undian</h5>
            <p class="text-muted small mb-0">
                Pilih apakah satu peserta hanya berhak memenangkan satu hadiah selama rangkaian acara, atau diperbolehkan memenangkan beberapa hadiah di kategori berbeda.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <form method="POST" action="prizes.php" class="d-inline-flex align-items-center gap-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_win_rule">
                <select name="win_rule" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="one_win_only" <?= $currentWinRule === 'one_win_only' ? 'selected' : '' ?>>
                        Satu Peserta Maksimal 1x Menang (Default)
                    </option>
                    <option value="multiple_allowed" <?= $currentWinRule === 'multiple_allowed' ? 'selected' : '' ?>>
                        Boleh Menang Lebih Dari 1x
                    </option>
                </select>
            </form>
        </div>
    </div>
</div>

<!-- Prizes List Table -->
<div class="bni-card p-0 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="bni-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 70px;">Urutan</th>
                    <th>Nama Hadiah</th>
                    <th>Deskripsi</th>
                    <th class="text-center">Progres Pemenang</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prizes)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            Belum ada data hadiah. Klik tombol "Tambah Hadiah Baru" di atas.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prizes as $pz): ?>
                        <?php 
                        $won = (int)$pz['pemenang_terpilih'];
                        $total = (int)$pz['jumlah_pemenang'];
                        $pct = $total > 0 ? min(100, round(($won / $total) * 100)) : 0;
                        ?>
                        <tr>
                            <td class="text-center fw-bold fs-5 text-muted"><?= $pz['urutan'] ?></td>
                            <td>
                                <strong class="text-navy fs-6"><?= e($pz['nama_hadiah']) ?></strong>
                            </td>
                            <td class="text-muted small"><?= e($pz['deskripsi'] ?: '-') ?></td>
                            <td class="text-center" style="min-width: 170px;">
                                <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                    <span class="fw-bold <?= $won >= $total ? 'text-success' : 'text-primary' ?>"><?= $won ?></span>
                                    <span class="text-muted">/ <?= $total ?> Pemenang</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar <?= $won >= $total ? 'bg-success' : 'bg-warning' ?>" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($pz['status_hadiah'] === 'AKTIF'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-play me-1"></i> AKTIF
                                    </span>
                                <?php elseif ($pz['status_hadiah'] === 'SELESAI'): ?>
                                    <span class="badge bg-secondary px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-check-double me-1"></i> SELESAI
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-pause me-1"></i> NONAKTIF
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick='openEditPrizeModal(<?= json_encode($pz) ?>)' title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" action="prizes.php" class="d-inline" onsubmit="return confirm('Hapus hadiah <?= e($pz['nama_hadiah']) ?>?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_prize">
                                        <input type="hidden" name="id" value="<?= $pz['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Prize -->
<div class="modal fade" id="addPrizeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="prizes.php" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_prize">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-gift me-2 text-warning"></i>Tambah Hadiah Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Hadiah <span class="text-danger">*</span></label>
                    <input type="text" name="nama_hadiah" class="form-control" required placeholder="Contoh: Logam Mulia Antam 50 Gram">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Deskripsi Detail</label>
                    <textarea name="deskripsi" class="form-control" rows="2" placeholder="Keterangan spesifikasi hadiah"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small">Jumlah Kuota Pemenang <span class="text-danger">*</span></label>
                        <input type="number" name="jumlah_pemenang" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small">Urutan Prioritas Undian</label>
                        <input type="number" name="urutan" class="form-control" value="1" min="1" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Status Hadiah</label>
                    <select name="status_hadiah" class="form-select">
                        <option value="AKTIF">AKTIF (Siap Diundi)</option>
                        <option value="NONAKTIF">NONAKTIF (Ditangguhkan)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-bni-orange">Simpan Hadiah</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Prize -->
<div class="modal fade" id="editPrizeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="prizes.php" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_prize">
            <input type="hidden" name="id" id="edit_pz_id" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Data Hadiah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Hadiah <span class="text-danger">*</span></label>
                    <input type="text" name="nama_hadiah" id="edit_pz_nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Deskripsi Detail</label>
                    <textarea name="deskripsi" id="edit_pz_deskripsi" class="form-control" rows="2"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small">Jumlah Kuota Pemenang</label>
                        <input type="number" name="jumlah_pemenang" id="edit_pz_jumlah" class="form-control" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small">Urutan Prioritas</label>
                        <input type="number" name="urutan" id="edit_pz_urutan" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Status Hadiah</label>
                    <select name="status_hadiah" id="edit_pz_status" class="form-select">
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                        <option value="SELESAI">SELESAI</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-bni-orange">Perbarui Hadiah</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPrizeModal(data) {
    document.getElementById('edit_pz_id').value = data.id;
    document.getElementById('edit_pz_nama').value = data.nama_hadiah;
    document.getElementById('edit_pz_deskripsi').value = data.deskripsi || '';
    document.getElementById('edit_pz_jumlah').value = data.jumlah_pemenang;
    document.getElementById('edit_pz_urutan').value = data.urutan;
    document.getElementById('edit_pz_status').value = data.status_hadiah;

    new bootstrap.Modal(document.getElementById('editPrizeModal')).show();
}
</script>

<?php include __DIR__ . '/admin-footer.php'; ?>
