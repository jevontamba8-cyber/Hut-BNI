<?php
/**
 * BNI Anniversary Celebration
 * Admin & Committee User Management (Super Admin Only)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin']);

$pageTitle = 'Manajemen Akun Panitia';
$activeMenu = 'users';
$db = getDB();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $namaAdmin = trim($_POST['nama_admin'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['superadmin', 'admin', 'operator', 'panitia'], true) ? $_POST['role'] : 'admin';
        $status = $_POST['status_akun'] === 'NONAKTIF' ? 'NONAKTIF' : 'AKTIF';

        if (empty($namaAdmin) || empty($username) || strlen($password) < 6) {
            setFlash('danger', 'Password minimal 6 karakter dan semua kolom wajib diisi.');
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO admins (nama_admin, username, password_hash, role, status_akun) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$namaAdmin, $username, $hash, $role, $status]);
                logAudit($currentUser['id'], 'CREATE_USER', "Menambahkan akun panitia baru: $username ($role)");
                setFlash('success', "Akun $username berhasil dibuat.");
            } catch (PDOException $e) {
                setFlash('danger', 'Username sudah digunakan. Pilih username lain.');
            }
        }
        header('Location: users.php');
        exit;
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $namaAdmin = trim($_POST['nama_admin'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['superadmin', 'admin', 'operator', 'panitia'], true) ? $_POST['role'] : 'admin';
        $status = $_POST['status_akun'] === 'NONAKTIF' ? 'NONAKTIF' : 'AKTIF';
        $newPassword = trim((string)($_POST['new_password'] ?? ''));

        // Prevent self-demotion or self-deactivation
        if ($id === (int)$currentUser['id'] && ($role !== 'superadmin' || $status !== 'AKTIF')) {
            setFlash('danger', 'Anda tidak dapat mengubah peran atau menonaktifkan akun Anda sendiri.');
            header('Location: users.php');
            exit;
        }

        try {
            if (!empty($newPassword)) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE admins SET nama_admin = ?, role = ?, status_akun = ?, password_hash = ? WHERE id = ?');
                $stmt->execute([$namaAdmin, $role, $status, $hash, $id]);
            } else {
                $stmt = $db->prepare('UPDATE admins SET nama_admin = ?, role = ?, status_akun = ? WHERE id = ?');
                $stmt->execute([$namaAdmin, $role, $status, $id]);
            }
            logAudit($currentUser['id'], 'UPDATE_USER', "Mengubah profil akun ID $id ($namaAdmin)");
            setFlash('success', 'Akun berhasil diperbarui.');
        } catch (Exception $e) {
            setFlash('danger', 'Gagal memperbarui akun: ' . $e->getMessage());
        }
        header('Location: users.php');
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$currentUser['id']) {
            setFlash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            $stmt = $db->prepare('DELETE FROM admins WHERE id = ?');
            $stmt->execute([$id]);
            logAudit($currentUser['id'], 'DELETE_USER', "Menghapus akun ID $id");
            setFlash('success', 'Akun berhasil dihapus.');
        }
        header('Location: users.php');
        exit;
    }
}

$admins = $db->query('SELECT id, nama_admin, username, role, status_akun, created_at FROM admins ORDER BY id ASC')->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1">Manajemen Pengguna & Hak Akses</h3>
        <p class="text-muted small mb-0">Atur akun panitia untuk Super Admin, Admin, Operator Magic Wheel, dan Petugas Registrasi.</p>
    </div>
    <button class="btn btn-bni-orange" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fa-solid fa-user-plus me-1"></i> Tambah Akun Baru
    </button>
</div>

<div class="bni-card p-0 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="bni-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">ID</th>
                    <th>Nama Panitia</th>
                    <th>Username</th>
                    <th>Peran (Role)</th>
                    <th class="text-center">Status</th>
                    <th>Dibuat</th>
                    <th class="text-center" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $u): ?>
                    <tr>
                        <td class="text-center text-muted"><?= $u['id'] ?></td>
                        <td>
                            <strong class="text-navy"><?= e($u['nama_admin']) ?></strong>
                            <?php if ($u['id'] === $currentUser['id']): ?>
                                <span class="badge bg-info-subtle text-info border ms-1">Akun Anda</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace text-primary fw-bold"><?= e($u['username']) ?></td>
                        <td>
                            <span class="badge bg-secondary text-uppercase"><?= e($u['role']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $u['status_akun'] === 'AKTIF' ? 'success' : 'danger' ?>">
                                <?= e($u['status_akun']) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick='openEditUserModal(<?= json_encode($u) ?>)' title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if ($u['id'] !== $currentUser['id']): ?>
                                    <form method="POST" action="users.php" class="d-inline" onsubmit="return confirm('Hapus akun <?= e($u['username']) ?>?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_user">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-user-plus me-2 text-warning"></i>Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Lengkap Panitia <span class="text-danger">*</span></label>
                    <input type="text" name="nama_admin" class="form-control" required placeholder="Contoh: Rian Pratama">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="Contoh: operator1">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small">Peran (Role)</label>
                        <select name="role" class="form-select">
                            <option value="operator">Operator Undian</option>
                            <option value="panitia">Panitia Check-in</option>
                            <option value="admin">Administrator</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small">Status Akun</label>
                        <select name="status_akun" class="form-select">
                            <option value="AKTIF">AKTIF</option>
                            <option value="NONAKTIF">NONAKTIF</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-bni-orange">Buat Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" id="edit_u_id" value="">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-navy"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Edit Akun Panitia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nama Panitia</label>
                    <input type="text" name="nama_admin" id="edit_u_nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Username</label>
                    <input type="text" id="edit_u_username" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Ganti Password (Kosongkan jika tidak diubah)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Password baru (opsional)">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold small">Peran (Role)</label>
                        <select name="role" id="edit_u_role" class="form-select">
                            <option value="operator">Operator Undian</option>
                            <option value="panitia">Panitia Check-in</option>
                            <option value="admin">Administrator</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold small">Status Akun</label>
                        <select name="status_akun" id="edit_u_status" class="form-select">
                            <option value="AKTIF">AKTIF</option>
                            <option value="NONAKTIF">NONAKTIF</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-bni-orange">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(user) {
    document.getElementById('edit_u_id').value = user.id;
    document.getElementById('edit_u_nama').value = user.nama_admin;
    document.getElementById('edit_u_username').value = user.username;
    document.getElementById('edit_u_role').value = user.role;
    document.getElementById('edit_u_status').value = user.status_akun;

    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php include __DIR__ . '/admin-footer.php'; ?>
