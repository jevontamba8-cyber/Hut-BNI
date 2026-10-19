<?php
/**
 * BNI Anniversary Celebration
 * Public Winners Showcase & Draw Results
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Hasil Undian Magic Wheel';
$activeNav = 'winners';
$settings = getSettings();
$baseUrl = getBaseUrl();
$db = getDB();

// Filters & Search
$prizeFilter = isset($_GET['prize_id']) ? (int)$_GET['prize_id'] : 0;
$search = trim($_GET['search'] ?? '');

// Fetch Prize list for dropdown filter
$prizesStmt = $db->query('SELECT id, nama_hadiah, jumlah_pemenang FROM prizes ORDER BY urutan ASC');
$prizesList = $prizesStmt->fetchAll();

// Build Query
$sql = "
    SELECT 
        dr.id AS draw_id,
        dr.draw_session_id,
        dr.waktu_undian,
        p.nama_lengkap,
        p.nomor_undian,
        p.divisi,
        p.cabang,
        pr.id AS prize_id,
        pr.nama_hadiah
    FROM draw_results dr
    JOIN participants p ON dr.participant_id = p.id
    JOIN prizes pr ON dr.prize_id = pr.id
    WHERE dr.status_hasil = 'SAH'
";

$params = [];

if ($prizeFilter > 0) {
    $sql .= " AND dr.prize_id = ? ";
    $params[] = $prizeFilter;
}

if (!empty($search)) {
    $sql .= " AND (p.nama_lengkap LIKE ? OR p.nomor_undian LIKE ? OR p.cabang LIKE ?) ";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY dr.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$winners = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <!-- Header banner -->
    <div class="bni-card mb-4 bg-gradient text-white p-4 p-md-5" style="background: linear-gradient(135deg, #0B2240 0%, #005E54 100%);">
        <div class="row align-items-center g-4">
            <div class="col-md-8">
                <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill mb-2">
                    <i class="fa-solid fa-trophy me-1"></i> Official Draw Results
                </span>
                <h1 class="fw-bold mb-2">Daftar Pemenang Magic Wheel</h1>
                <p class="text-white-50 mb-0">
                    Pengumuman resmi pemenang doorprize dan hadiah utama <strong><?= e($settings['event_title'] ?? 'BNI Anniversary Celebration') ?></strong>.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-inline-flex align-items-center gap-3 bg-white bg-opacity-10 p-3 rounded-4 border border-white border-opacity-20">
                    <i class="fa-solid fa-award text-warning fs-1"></i>
                    <div class="text-start">
                        <div class="h2 mb-0 fw-bold text-warning"><?= count($winners) ?></div>
                        <div class="small text-white-50">Total Pemenang Terpilih</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bni-card p-3 mb-4">
        <form method="GET" action="winners.php" class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama pemenang, nomor undian, atau cabang..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="prize_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- Semua Kategori Hadiah --</option>
                    <?php foreach ($prizesList as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $prizeFilter === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nama_hadiah']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-bni-orange flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <?php if ($prizeFilter > 0 || !empty($search)): ?>
                    <a href="winners.php" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
                <a href="<?= $baseUrl ?>/export-winners.php?format=csv&prize_id=<?= $prizeFilter ?>" class="btn btn-success" title="Export CSV / Excel">
                    <i class="fa-solid fa-file-excel"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Winners Table -->
    <div class="bni-card p-0 overflow-hidden shadow-sm">
        <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-navy">
                <i class="fa-solid fa-list-ol me-2 text-warning"></i> Rekap Data Pemenang Sah
            </h5>
            <div class="small text-muted">
                Menampilkan <?= count($winners) ?> data pemenang
            </div>
        </div>

        <div class="table-responsive">
            <table class="bni-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;">No</th>
                        <th>Hadiah</th>
                        <th>Nama Lengkap</th>
                        <th class="text-center">No. Undian</th>
                        <th>Divisi / Unit Kerja</th>
                        <th>Cabang</th>
                        <th>Waktu Undian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($winners)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-trophy fs-1 d-block mb-2 text-muted opacity-50"></i>
                                Belum ada pemenang yang tercatat atau data tidak sesuai pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($winners as $idx => $row): ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <span class="badge bg-warning-subtle text-warning-emphasis fw-bold px-3 py-1 rounded-pill">
                                        <i class="fa-solid fa-gift me-1"></i> <?= e($row['nama_hadiah']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-navy"><?= e($row['nama_lengkap']) ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary text-white font-monospace px-3 py-1 fs-6">
                                        <?= e($row['nomor_undian']) ?>
                                    </span>
                                </td>
                                <td><?= e($row['divisi']) ?></td>
                                <td><?= e($row['cabang']) ?></td>
                                <td class="small text-muted">
                                    <i class="fa-regular fa-clock me-1 text-secondary"></i> <?= formatIndonesianDate($row['waktu_undian']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
