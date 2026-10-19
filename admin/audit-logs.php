<?php
/**
 * BNI Anniversary Celebration
 * Security & Audit Logs Viewer
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin']);

$pageTitle = 'Audit Log Keamanan';
$activeMenu = 'audit-logs';
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$totalRows = (int)$db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $db->prepare('
    SELECT 
        al.id,
        al.aktivitas,
        al.detail_aktivitas,
        al.waktu_aktivitas,
        a.nama_admin,
        a.username,
        a.role
    FROM audit_logs al
    LEFT JOIN admins a ON al.admin_id = a.id
    ORDER BY al.id DESC 
    LIMIT ? OFFSET ?
');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

include __DIR__ . '/admin-header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="fa-solid fa-clipboard-list me-2 text-warning"></i>Audit Trail & Log Aktivitas</h3>
        <p class="text-muted small mb-0">Catatan kronologis seluruh perubahan data, pengundian, dan otentikasi pengguna.</p>
    </div>
</div>

<div class="bni-card p-0 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="bni-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 60px;">ID</th>
                    <th>Waktu Aktivitas</th>
                    <th>Pelaksana (Admin)</th>
                    <th>Aktivitas</th>
                    <th>Detail Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">Belum ada riwayat aktivitas yang tercatat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="text-center text-muted small"><?= $l['id'] ?></td>
                            <td class="small text-muted" style="white-space: nowrap;">
                                <?= formatIndonesianDate($l['waktu_aktivitas']) ?>
                            </td>
                            <td>
                                <?php if (!empty($l['nama_admin'])): ?>
                                    <strong class="text-navy"><?= e($l['nama_admin']) ?></strong>
                                    <div class="small text-muted font-monospace"><?= e($l['username']) ?> (<?= e($l['role']) ?>)</div>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sistem / Publik</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-monospace"><?= e($l['aktivitas']) ?></span>
                            </td>
                            <td class="small text-muted"><?= e($l['detail_aktivitas']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="p-3 bg-light border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="small text-muted">Halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= number_format($totalRows) ?> log)</div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">&laquo; Prev</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>">Next &raquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/admin-footer.php'; ?>
