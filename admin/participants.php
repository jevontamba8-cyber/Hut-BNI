<?php
/**
 * BNI Anniversary Celebration
 * Participant Management - Search, Filter, Pagination, CRUD
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin', 'panitia']);

$pageTitle = 'Data Peserta';
$activeMenu = 'participants';
$db = getDB();
$currentUser = getCurrentUser();

// Handle Actions (Create, Update, Delete, Toggle Check-in)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' && hasRole(['superadmin', 'admin'])) {
        $nik = trim($_POST['nik'] ?? '');
        $nomorUndian = trim($_POST['nomor_undian'] ?? '');
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $divisi = trim($_POST['divisi'] ?? '');
        $cabang = trim($_POST['cabang'] ?? '');

        if (!validateNikFormat($nik)) {
            setFlash('danger', 'NIK harus 16 digit angka.');
        } elseif (empty($nomorUndian) || empty($namaLengkap) || empty($divisi) || empty($cabang)) {
            setFlash('danger', 'Seluruh kolom wajib diisi.');
        } else {
            try {
                $stmt = $db->prepare('INSERT INTO participants (nik, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran) VALUES (?, ?, ?, ?, ?, "BELUM HADIR")');
                $stmt->execute([$nik, $nomorUndian, $namaLengkap, $divisi, $cabang]);
                logAudit($currentUser['id'], 'CREATE_PARTICIPANT', "Menambahkan peserta baru: $namaLengkap ($nik)");
                setFlash('success', "Peserta $namaLengkap berhasil ditambahkan.");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlash('danger', 'Gagal menambahkan: NIK atau Nomor Undian sudah digunakan.');
                } else {
                    setFlash('danger', 'Terjadi kesalahan database: ' . $e->getMessage());
                }
            }
        }
        header('Location: participants.php');
        exit;
    }

    if ($action === 'update' && hasRole(['superadmin', 'admin'])) {
        $id = (int)($_POST['id'] ?? 0);
        $nik = trim($_POST['nik'] ?? '');
        $nomorUndian = trim($_POST['nomor_undian'] ?? '');
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $divisi = trim($_POST['divisi'] ?? '');
        $cabang = trim($_POST['cabang'] ?? '');
        $status = $_POST['status_kehadiran'] === 'HADIR' ? 'HADIR' : 'BELUM HADIR';

        if (!validateNikFormat($nik)) {
            setFlash('danger', 'NIK harus 16 digit angka.');
        } else {
            try {
                $stmt = $db->prepare('
                    UPDATE participants 
                    SET nik = ?, nomor_undian = ?, nama_lengkap = ?, divisi = ?, cabang = ?, status_kehadiran = ?, 
                        waktu_checkin = CASE WHEN ? = "HADIR" AND waktu_checkin IS NULL THEN NOW() ELSE waktu_checkin END
                    WHERE id = ?
                ');
                $stmt->execute([$nik, $nomorUndian, $namaLengkap, $divisi, $cabang, $status, $status, $id]);
                logAudit($currentUser['id'], 'UPDATE_PARTICIPANT', "Mengubah data peserta id $id: $namaLengkap");
                setFlash('success', "Data peserta $namaLengkap berhasil diperbarui.");
            } catch (PDOException $e) {
                setFlash('danger', 'Gagal memperbarui: NIK atau Nomor Undian mungkin duplikat.');
            }
        }
        header('Location: participants.php');
        exit;
    }

    if ($action === 'delete' && hasRole(['superadmin', 'admin'])) {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare('DELETE FROM participants WHERE id = ?');
            $stmt->execute([$id]);
            logAudit($currentUser['id'], 'DELETE_PARTICIPANT', "Menghapus peserta id $id");
            setFlash('success', 'Peserta berhasil dihapus.');
        } catch (Exception $e) {
            setFlash('danger', 'Gagal menghapus peserta: ' . $e->getMessage());
        }
        header('Location: participants.php');
        exit;
    }

    if ($action === 'toggle_checkin') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'HADIR' ? 'HADIR' : 'BELUM HADIR';
        $waktu = $newStatus === 'HADIR' ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare('UPDATE participants SET status_kehadiran = ?, waktu_checkin = ? WHERE id = ?');
        $stmt->execute([$newStatus, $waktu, $id]);
        logAudit($currentUser['id'], 'TOGGLE_CHECKIN', "Manual update status peserta $id menjadi $newStatus");
        setFlash('success', "Status kehadiran berhasil diubah menjadi $newStatus.");
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Filters & Pagination
$search = trim($_GET['search'] ?? '');
$filterDivisi = trim($_GET['divisi'] ?? '');
$filterCabang = trim($_GET['cabang'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Distinct divisions & branches for dropdowns
$divisions = $db->query("SELECT DISTINCT divisi FROM participants WHERE divisi != '' ORDER BY divisi ASC")->fetchAll(PDO::FETCH_COLUMN);
$branches = $db->query("SELECT DISTINCT cabang FROM participants WHERE cabang != '' ORDER BY cabang ASC")->fetchAll(PDO::FETCH_COLUMN);

// Build Count & Query
$whereClause = ' WHERE 1=1 ';
$params = [];

if (!empty($search)) {
    $whereClause .= ' AND (nama_lengkap LIKE ? OR nik LIKE ? OR nomor_undian LIKE ?) ';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filterDivisi)) {
    $whereClause .= ' AND divisi = ? ';
    $params[] = $filterDivisi;
}

if (!empty($filterCabang)) {
    $whereClause .= ' AND cabang = ? ';
    $params[] = $filterCabang;
}

if (!empty($filterStatus)) {
    $whereClause .= ' AND status_kehadiran = ? ';
    $params[] = $filterStatus;
}

// Total Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM participants $whereClause");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch Data
$dataSql = "SELECT * FROM participants $whereClause ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($dataSql);
$stmt->execute($params);
$participants = $stmt->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<!-- Header Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1">Database Peserta (1.200+)</h3>
        <p class="text-muted small mb-0">Total terdaftar: <strong><?= number_format($totalRows) ?></strong> pegawai</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/export-participants.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success">
            <i class="fa-solid fa-file-excel me-1"></i> Export Data
        </a>
        <a href="<?= $baseUrl ?>/admin/import.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-file-import me-1"></i> Import Excel
        </a>
        <?php if (hasRole(['superadmin', 'admin'])): ?>
            <button class="btn btn-bni-orange" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                <i class="fa-solid fa-plus me-1"></i> Tambah Peserta
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Bar -->
<div class="bni-card p-3 mb-4">
    <form method="GET" action="participants.php" class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control" placeholder="Cari Nama, NIK, No Undian..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="divisi" class="form-select">
                <option value="">-- Semua Divisi --</option>
                <?php foreach ($divisions as $d): ?>
                    <option value="<?= e($d) ?>" <?= $filterDivisi === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="cabang" class="form-select">
                <option value="">-- Semua Cabang --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b) ?>" <?= $filterCabang === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">-- Status Hadir --</option>
                <option value="HADIR" <?= $filterStatus === 'HADIR' ? 'selected' : '' ?>>HADIR</option>
                <option value="BELUM HADIR" <?= $filterStatus === 'BELUM HADIR' ? 'selected' : '' ?>>BELUM HADIR</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-bni-orange flex-grow-1">Filter</button>
            <a href="participants.php" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </form>
</div>

<!-- Participants Table -->
<div class="bni-card p-0 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="bni-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">ID</th>
                    <th>Nama Lengkap</th>
                    <th class="text-center">No. Undian</th>
                    <th>NIK</th>
                    <th>Divisi</th>
                    <th>Cabang</th>
                    <th class="text-center">Status Kehadiran</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($participants)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-users-slash fs-1 d-block mb-2 text-muted opacity-50"></i>
                            Tidak ada data peserta yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($participants as $p): ?>
                        <tr>
                            <td class="text-center text-muted small"><?= $p['id'] ?></td>
                            <td>
                                <strong class="text-navy"><?= e($p['nama_lengkap']) ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary font-monospace px-2 py-1"><?= e($p['nomor_undian']) ?></span>
                            </td>
                            <td class="font-monospace small">
                                <?php if (hasRole(['superadmin', 'admin'])): ?>
                                    <?= e($p['nik']) ?>
                                <?php else: ?>
                                    <?= maskNik($p['nik']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e($p['divisi']) ?></td>
                            <td><?= e($p['cabang']) ?></td>
                            <td class="text-center">
                                <?php if ($p['status_kehadiran'] === 'HADIR'): ?>
                                    <span class="badge-hadir" title="Check-in: <?= formatIndonesianDate($p['waktu_checkin']) ?>">
                                        <i class="fa-solid fa-check"></i> HADIR
                                    </span>
                                <?php else: ?>
                                    <span class="badge-belum">
                                        <i class="fa-solid fa-clock"></i> BELUM
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <!-- Toggle Check-in -->
                                    <form method="POST" action="participants.php" class="d-inline" onsubmit="return confirm('Ubah status kehadiran peserta ini?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_checkin">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $p['status_kehadiran'] === 'HADIR' ? 'BELUM HADIR' : 'HADIR' ?>">
                                        <button type="submit" class="btn btn-outline-<?= $p['status_kehadiran'] === 'HADIR' ? 'warning' : 'success' ?>" title="<?= $p['status_kehadiran'] === 'HADIR' ? 'Batalkan Hadir' : 'Tandai Hadir' ?>">
                                            <i class="fa-solid <?= $p['status_kehadiran'] === 'HADIR' ? 'fa-user-minus' : 'fa-user-check' ?>"></i>
                                        </button>
                                    </form>

                                    <?php if (hasRole(['superadmin', 'admin'])): ?>
                                        <!-- Edit button -->
                                        <button class="btn btn-outline-secondary" onclick='openEditModal(<?= json_encode($p) ?>)' title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Delete button -->
                                        <form method="POST" action="participants.php" class="d-inline" onsubmit="return confirm('Hapus peserta <?= e($p['nama_lengkap']) ?>?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-3 bg-light border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="small text-muted">
                Halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= number_format($totalRows) ?> data)
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $queryParams = $_GET;
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($queryParams, ['page' => $prevPage])) ?>">&laquo; Prev</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($queryParams, ['page' => $nextPage])) ?>">Next &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Add Participant -->
<div class="modal fade" id="addParticipantModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="participants.php" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-user-plus me-2 text-warning"></i>Tambah Peserta Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">NIK (16 Digit Angka) <span class="text-danger">*</span></label>
                    <input type="text" name="nik" class="form-control" maxlength="16" pattern="[0-9]{16}" required placeholder="3171xxxxxxxxxxxx">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nomor Undian <span class="text-danger">*</span></label>
                    <input type="text" name="nomor_undian" class="form-control" required placeholder="BNI-0001">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" required placeholder="Nama Lengkap Pegawai">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Divisi / Unit Kerja <span class="text-danger">*</span></label>
                    <input type="text" name="divisi" class="form-control" required placeholder="Divisi Teknologi Informasi">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cabang / Wilayah <span class="text-danger">*</span></label>
                    <input type="text" name="cabang" class="form-control" required placeholder="Kantor Pusat Grha BNI">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-bni-orange">Simpan Peserta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Participant -->
<div class="modal fade" id="editParticipantModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="participants.php" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-user-pen me-2 text-primary"></i>Edit Data Peserta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">NIK (16 Digit Angka) <span class="text-danger">*</span></label>
                    <input type="text" name="nik" id="edit_nik" class="form-control" maxlength="16" pattern="[0-9]{16}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nomor Undian <span class="text-danger">*</span></label>
                    <input type="text" name="nomor_undian" id="edit_nomor_undian" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Divisi / Unit Kerja <span class="text-danger">*</span></label>
                    <input type="text" name="divisi" id="edit_divisi" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cabang / Wilayah <span class="text-danger">*</span></label>
                    <input type="text" name="cabang" id="edit_cabang" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Status Kehadiran</label>
                    <select name="status_kehadiran" id="edit_status" class="form-select">
                        <option value="BELUM HADIR">BELUM HADIR</option>
                        <option value="HADIR">HADIR</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-bni-orange">Perbarui Data</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nik').value = data.nik;
    document.getElementById('edit_nomor_undian').value = data.nomor_undian;
    document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
    document.getElementById('edit_divisi').value = data.divisi;
    document.getElementById('edit_cabang').value = data.cabang;
    document.getElementById('edit_status').value = data.status_kehadiran;

    new bootstrap.Modal(document.getElementById('editParticipantModal')).show();
}
</script>

<?php include __DIR__ . '/admin-footer.php'; ?>
