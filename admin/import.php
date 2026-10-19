<?php
/**
 * BNI Anniversary Celebration
 * Bulk Import Participants (1,200+ capacity) via PhpSpreadsheet / CSV
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin(['superadmin', 'admin']);

$pageTitle = 'Import Peserta';
$activeMenu = 'import';
$db = getDB();
$currentUser = getCurrentUser();

$previewData = [];
$importSummary = null;
$errors = [];

// Handle File Upload & Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();
    $step = $_POST['step'] ?? 'upload';

    if ($step === 'upload') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'Harap pilih file Excel (.xlsx) atau CSV yang valid untuk diunggah.');
            header('Location: import.php');
            exit;
        }

        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate size (max 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            setFlash('danger', 'Ukuran file melebihi batas maksimal 10MB.');
            header('Location: import.php');
            exit;
        }

        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            setFlash('danger', 'Format file tidak didukung. Harap unggah file .xlsx atau .csv.');
            header('Location: import.php');
            exit;
        }

        $rawRows = [];

        // Parse file based on extension
        if ($ext === 'csv') {
            $handle = fopen($fileTmp, 'r');
            if ($handle !== false) {
                // Strip possible UTF-8 BOM
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    if (!empty(array_filter($data))) {
                        $rawRows[] = $data;
                    }
                }
                fclose($handle);
            }
        } else {
            // Excel using PhpSpreadsheet
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmp);
                $worksheet = $spreadsheet->getActiveSheet();
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = (string)$cell->getValue();
                    }
                    if (!empty(array_filter($rowData))) {
                        $rawRows[] = $rowData;
                    }
                }
            } catch (Exception $e) {
                setFlash('danger', 'Gagal membaca file Excel: ' . $e->getMessage());
                header('Location: import.php');
                exit;
            }
        }

        if (count($rawRows) <= 1) {
            setFlash('warning', 'File kosong atau hanya berisi baris header.');
            header('Location: import.php');
            exit;
        }

        // Remove header row
        $header = array_shift($rawRows);

        // Fetch existing NIKs and Lottery numbers to detect duplicates against MySQL
        $existingNiks = $db->query('SELECT nik FROM participants')->fetchAll(PDO::FETCH_COLUMN);
        $existingNikSet = array_flip($existingNiks);

        $existingTickets = $db->query('SELECT nomor_undian FROM participants')->fetchAll(PDO::FETCH_COLUMN);
        $existingTicketSet = array_flip($existingTickets);

        $seenFileNiks = [];
        $seenFileTickets = [];

        $validRows = [];
        $invalidRows = [];

        foreach ($rawRows as $idx => $row) {
            $rowNum = $idx + 2; // 1-based, accounting for header
            $nik = trim((string)($row[0] ?? ''));
            // Remove leading apostrophe if present
            $nik = ltrim($nik, "'");
            $nomorUndian = trim((string)($row[1] ?? ''));
            $namaLengkap = trim((string)($row[2] ?? ''));
            $divisi = trim((string)($row[3] ?? ''));
            $cabang = trim((string)($row[4] ?? ''));

            $rowErrors = [];

            // 1. Validate NIK
            if (!validateNikFormat($nik)) {
                $rowErrors[] = 'NIK tidak valid (harus tepat 16 digit angka)';
            } elseif (isset($existingNikSet[$nik])) {
                $rowErrors[] = 'NIK sudah terdaftar di database';
            } elseif (isset($seenFileNiks[$nik])) {
                $rowErrors[] = "NIK duplikat di dalam file (sama dengan baris {$seenFileNiks[$nik]})";
            } else {
                $seenFileNiks[$nik] = $rowNum;
            }

            // 2. Validate Nomor Undian
            if (empty($nomorUndian)) {
                $rowErrors[] = 'Nomor undian tidak boleh kosong';
            } elseif (isset($existingTicketSet[$nomorUndian])) {
                $rowErrors[] = 'Nomor undian sudah terdaftar di database';
            } elseif (isset($seenFileTickets[$nomorUndian])) {
                $rowErrors[] = "Nomor undian duplikat di file (sama dengan baris {$seenFileTickets[$nomorUndian]})";
            } else {
                $seenFileTickets[$nomorUndian] = $rowNum;
            }

            // 3. Validate Nama
            if (empty($namaLengkap)) {
                $rowErrors[] = 'Nama lengkap tidak boleh kosong';
            }

            // 4. Validate Divisi & Cabang
            if (empty($divisi)) $divisi = 'Umum';
            if (empty($cabang)) $cabang = 'Kantor Pusat';

            $parsedRecord = [
                'row'          => $rowNum,
                'nik'          => $nik,
                'nomor_undian' => $nomorUndian,
                'nama_lengkap' => $namaLengkap,
                'divisi'       => $divisi,
                'cabang'       => $cabang,
                'errors'       => $rowErrors
            ];

            if (empty($rowErrors)) {
                $validRows[] = $parsedRecord;
            } else {
                $invalidRows[] = $parsedRecord;
            }
        }

        // Store parsed valid rows in session for execution
        $_SESSION['pending_import'] = [
            'valid_rows'   => $validRows,
            'invalid_rows' => $invalidRows,
            'file_name'    => $fileName,
            'total_rows'   => count($rawRows)
        ];
    }

    if ($step === 'confirm_save') {
        $pending = $_SESSION['pending_import'] ?? null;
        if (!$pending || empty($pending['valid_rows'])) {
            setFlash('danger', 'Tidak ada data valid yang siap disimpan.');
            header('Location: import.php');
            exit;
        }

        $validRows = $pending['valid_rows'];
        $insertedCount = 0;

        try {
            $db->beginTransaction();

            $insertStmt = $db->prepare('
                INSERT INTO participants (nik, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran)
                VALUES (?, ?, ?, ?, ?, "BELUM HADIR")
            ');

            foreach ($validRows as $r) {
                $insertStmt->execute([
                    $r['nik'],
                    $r['nomor_undian'],
                    $r['nama_lengkap'],
                    $r['divisi'],
                    $r['cabang']
                ]);
                $insertedCount++;
            }

            $db->commit();

            logAudit($currentUser['id'], 'IMPORT_PARTICIPANTS', "Berhasil mengimpor $insertedCount peserta dari file {$pending['file_name']}");
            unset($_SESSION['pending_import']);

            setFlash('success', "Sukses! Sebanyak " . number_format($insertedCount) . " peserta berhasil diimpor ke database.");
            header('Location: participants.php');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Gagal menyimpan ke database: ' . $e->getMessage());
            header('Location: import.php');
            exit;
        }
    }

    if ($step === 'cancel') {
        unset($_SESSION['pending_import']);
        header('Location: import.php');
        exit;
    }
}

$pending = $_SESSION['pending_import'] ?? null;

include __DIR__ . '/admin-header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <!-- Upload Card -->
        <div class="bni-card shadow-sm mb-4">
            <h4 class="fw-bold text-navy mb-3"><i class="fa-solid fa-file-import me-2 text-warning"></i>Unggah File Peserta</h4>
            <p class="text-muted small mb-4">
                Mendukung hingga 1.200+ peserta dalam satu kali unggah. Sistem akan memvalidasi NIK 16 digit, nomor undian, dan mendeteksi data duplikat secara otomatis.
            </p>

            <form method="POST" action="import.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="step" value="upload">

                <div class="mb-3">
                    <label for="excelFile" class="form-label fw-bold small">Pilih File (.xlsx atau .csv) <span class="text-danger">*</span></label>
                    <input type="file" name="file" id="excelFile" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <div class="form-text text-muted">Maksimal ukuran file: 10 MB.</div>
                </div>

                <button type="submit" class="btn btn-bni-orange w-100 justify-content-center">
                    <i class="fa-solid fa-cloud-arrow-up me-2"></i> Unggah & Periksa Data
                </button>
            </form>
        </div>

        <!-- Download Template Card -->
        <div class="bni-card bg-light border shadow-sm">
            <h5 class="fw-bold text-navy mb-2"><i class="fa-solid fa-download me-2 text-primary"></i>Download Template Data</h5>
            <p class="text-muted small mb-3">
                Gunakan format kolom yang telah disesuaikan agar proses import berjalan sempurna tanpa kesalahan.
            </p>
            <div class="d-flex gap-2">
                <a href="<?= $baseUrl ?>/download-template.php?format=xlsx" class="btn btn-sm btn-success flex-grow-1">
                    <i class="fa-solid fa-file-excel me-1"></i> Template Excel (.xlsx)
                </a>
                <a href="<?= $baseUrl ?>/download-template.php?format=csv" class="btn btn-sm btn-outline-secondary flex-grow-1">
                    <i class="fa-solid fa-file-csv me-1"></i> Template CSV
                </a>
            </div>
        </div>

        <!-- Quick Generator Tool -->
        <div class="bni-card border-warning border-opacity-50 mt-4">
            <h6 class="fw-bold text-navy mb-1"><i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i>Alat Pengujian Cepat:</h6>
            <p class="small text-muted mb-2">Ingin langsung menguji dengan 1.200 peserta realistis Indonesia?</p>
            <a href="<?= $baseUrl ?>/seed-1200.php" class="btn btn-sm btn-outline-warning text-dark fw-bold w-100">
                <i class="fa-solid fa-bolt me-1"></i> Generate 1.200 Data Uji Otomatis
            </a>
        </div>
    </div>

    <!-- Preview & Validation Results -->
    <div class="col-lg-7">
        <?php if ($pending): ?>
            <div class="bni-card shadow-sm p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <h4 class="fw-bold text-navy mb-0">Hasil Validasi File: <?= e($pending['file_name']) ?></h4>
                        <div class="small text-muted">Total baris data terdeteksi: <strong><?= number_format($pending['total_rows']) ?></strong></div>
                    </div>
                    <form method="POST" action="import.php" class="d-flex gap-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="step" value="cancel">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Batalkan</button>
                    </form>
                </div>

                <!-- Summary Badges -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 text-center">
                            <div class="h3 fw-bold text-success mb-0"><?= count($pending['valid_rows']) ?></div>
                            <div class="small fw-bold text-success">Data Valid (Siap Disimpan)</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 text-center">
                            <div class="h3 fw-bold text-danger mb-0"><?= count($pending['invalid_rows']) ?></div>
                            <div class="small fw-bold text-danger">Data Gagal / Duplikat</div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Action -->
                <?php if (!empty($pending['valid_rows'])): ?>
                    <form method="POST" action="import.php" class="mb-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="step" value="confirm_save">
                        <button type="submit" class="btn btn-bni-orange btn-lg w-100 justify-content-center shadow">
                            <i class="fa-solid fa-check-double me-2"></i> Konfirmasi & Simpan <?= count($pending['valid_rows']) ?> Peserta Valid
                        </button>
                    </form>
                <?php endif; ?>

                <!-- Error Details (if any) -->
                <?php if (!empty($pending['invalid_rows'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-danger mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Rincian Baris Gagal:</h6>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-bordered small">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Baris</th>
                                        <th>NIK</th>
                                        <th>Nama</th>
                                        <th>Penyebab Kesalahan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending['invalid_rows'] as $inv): ?>
                                        <tr>
                                            <td class="fw-bold text-center"><?= $inv['row'] ?></td>
                                            <td class="font-monospace"><?= e($inv['nik']) ?></td>
                                            <td><?= e($inv['nama_lengkap']) ?></td>
                                            <td class="text-danger fw-semibold"><?= implode(', ', $inv['errors']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Valid Sample Preview -->
                <div>
                    <h6 class="fw-bold text-navy mb-2"><i class="fa-solid fa-eye me-1 text-primary"></i> Preview Data Valid (10 Baris Pertama):</h6>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-striped small">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>No. Undian</th>
                                    <th>Nama Lengkap</th>
                                    <th>NIK</th>
                                    <th>Divisi</th>
                                    <th>Cabang</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($pending['valid_rows'], 0, 10) as $idx => $v): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td><span class="badge bg-primary font-monospace"><?= e($v['nomor_undian']) ?></span></td>
                                        <td class="fw-bold"><?= e($v['nama_lengkap']) ?></td>
                                        <td class="font-monospace text-muted"><?= e($v['nik']) ?></td>
                                        <td><?= e($v['divisi']) ?></td>
                                        <td><?= e($v['cabang']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bni-card text-center p-5 shadow-sm">
                <i class="fa-solid fa-file-excel fs-1 text-muted opacity-50 mb-3 d-block"></i>
                <h5 class="fw-bold text-navy">Belum Ada File yang Diproses</h5>
                <p class="text-muted small mb-0">
                    Pilih file template Excel atau CSV yang telah diisi di sebelah kiri untuk melihat ringkasan validasi sebelum disimpan secara permanen.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/admin-footer.php'; ?>
