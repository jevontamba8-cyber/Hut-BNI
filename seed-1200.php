<?php
/**
 * BNI Anniversary Celebration
 * Helper Script: Generate 1,200 Realistic Indonesian Participants
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin(['superadmin', 'admin']);

$db = getDB();
$message = '';
$countInserted = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstNames = [
        'Jevon', 'Ahmad', 'Budi', 'Siti', 'Dewi', 'Rina', 'Andi', 'Fajar', 'Indra', 'Muhammad',
        'Putri', 'Bambang', 'Dian', 'Eko', 'Hendra', 'Nurul', 'Agus', 'Tri', 'Wahyu', 'Sri',
        'Bayu', 'Rizky', 'Maya', 'Nita', 'Farhan', 'Kevin', 'Grace', 'Jessica', 'David', 'Michael',
        'Chandra', 'Yuliana', 'Aris', 'Ilham', 'Dimas', 'Gita', 'Ratna', 'Anisa', 'Fikri', 'Taufik',
        'Rian', 'Lestari', 'Surya', 'Mega', 'Sari', 'Gunawan', 'Danang', 'Pratama', 'Wulandari', 'Utami'
    ];

    $middleNames = [
        'Hot Asi', 'Kurniawan', 'Pratama', 'Hidayat', 'Saputra', 'Permana', 'Nugroho', 'Setiawan',
        'Wijaya', 'Kusuma', 'Santoso', 'Utomo', 'Siregar', 'Simanjuntak', 'Nasution', 'Lubis',
        'Pangestu', 'Wibowo', 'Wahyudi', 'Firmansyah', 'Budiman', 'Maulana', 'Ramadhan', 'Hakiem'
    ];

    $lastNames = [
        'Alexander Tamba', 'Rahmawati', 'Fauzi', 'Saraswati', 'Santoso', 'Pranata', 'Wicaksono',
        'Kusuma', 'Hartono', 'Salim', 'Sudarsono', 'Hutapea', 'Sitorus', 'Manurung', 'Sinaga',
        'Subekti', 'Prasetyo', 'Nurcahyo', 'Wardhana', 'Sulaeman', 'Kurnia', 'Hermawan', 'Mulyadi'
    ];

    $divisions = [
        'Divisi Teknologi Informasi (IT)',
        'Divisi Human Capital (HC)',
        'Divisi Consumer Banking',
        'Divisi Commercial & SME Banking',
        'Divisi Corporate Banking',
        'Divisi Risk Management',
        'Divisi Treasury & Global Markets',
        'Divisi Compliance & Governance',
        'Divisi Operasional & Layanan',
        'Divisi Digital Banking Solutions',
        'Divisi Corporate Secretary',
        'Divisi Internal Audit'
    ];

    $branches = [
        'Kantor Pusat Grha BNI Jakarta',
        'Kantor Wilayah 01 Medan',
        'Kantor Wilayah 02 Padang',
        'Kantor Wilayah 03 Palembang',
        'Kantor Wilayah 04 Bandung',
        'Kantor Wilayah 05 Semarang',
        'Kantor Wilayah 06 Surabaya',
        'Kantor Wilayah 07 Makassar',
        'Kantor Wilayah 08 Denpasar',
        'Kantor Wilayah 09 Banjarmasin',
        'Kantor Wilayah 10 Manado',
        'Kantor Wilayah 11 Papua',
        'Kantor Wilayah 12 Jakarta Senayan',
        'Kantor Wilayah 14 Jakarta Kota',
        'Kantor Wilayah 15 Jakarta BSD'
    ];

    $targetCount = 1200;
    $autoCheckinCount = isset($_POST['auto_checkin']) ? (int)$_POST['auto_checkin'] : 750;

    try {
        // Optional clear existing participants
        if (isset($_POST['reset_first']) && $_POST['reset_first'] === '1') {
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');
            $db->exec('TRUNCATE TABLE draw_results');
            $db->exec('TRUNCATE TABLE participants');
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $db->beginTransaction();

        $stmt = $db->prepare('
            INSERT INTO participants (nik, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran, waktu_checkin)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        // Indonesian provincial prefixes for authentic NIK
        $provinces = ['3171', '3172', '3173', '3174', '3175', '3201', '3204', '3273', '3301', '3374', '3578', '1271', '5171', '7371'];

        for ($i = 1; $i <= $targetCount; $i++) {
            $prov = $provinces[array_rand($provinces)];
            $dist = sprintf('%02d', random_int(1, 15));
            $dob = sprintf('%02d%02d%02d', random_int(1, 28), random_int(1, 12), random_int(75, 99));
            $seq = sprintf('%04d', $i);
            $nik = $prov . $dist . $dob . $seq;

            $nomorUndian = sprintf('BNI-%04d', $i);

            // Special: let index 1 be Jevon Hot Asi Alexander Tamba as mentioned in prompt example
            if ($i === 1) {
                $nik = '3171012802990001';
                $fullName = 'Jevon Hot Asi Alexander Tamba';
                $div = 'Divisi Teknologi Informasi (IT)';
                $cab = 'Kantor Pusat Grha BNI Jakarta';
            } else {
                $f = $firstNames[array_rand($firstNames)];
                $m = $middleNames[array_rand($middleNames)];
                $l = $lastNames[array_rand($lastNames)];
                $fullName = "$f $m $l";
                $div = $divisions[array_rand($divisions)];
                $cab = $branches[array_rand($branches)];
            }

            // Decide check-in status (first N participants checked-in)
            $status = ($i <= $autoCheckinCount) ? 'HADIR' : 'BELUM HADIR';
            $checkinTime = ($status === 'HADIR') ? date('Y-m-d H:i:s', time() - random_int(300, 14400)) : null;

            $stmt->execute([
                $nik,
                $nomorUndian,
                $fullName,
                $div,
                $cab,
                $status,
                $checkinTime
            ]);
            $countInserted++;
        }

        $db->commit();
        logAudit(getCurrentUser()['id'], 'SEED_PARTICIPANTS', "Men-generate $countInserted peserta uji (Auto check-in: $autoCheckinCount)");

        setFlash('success', "Berhasil menambahkan $countInserted peserta dengan $autoCheckinCount telah check-in!");
        header('Location: ' . getBaseUrl() . '/admin/participants.php');
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Gagal men-generate peserta: ' . $e->getMessage();
    }
}

$pageTitle = 'Generate 1.200 Peserta Uji';
$activeMenu = 'import';
include __DIR__ . '/admin/admin-header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="bni-card shadow p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-warning bg-opacity-10 text-warning mb-3">
                        <i class="fa-solid fa-bolt fs-1"></i>
                    </div>
                    <h3 class="fw-bold text-navy mb-1">Generate 1.200 Peserta Realistis</h3>
                    <p class="text-muted small">
                        Fitur otomatis untuk memenuhi kriteria pengujian 1.200 peserta lengkap dengan NIK 16 digit, Nomor Undian (BNI-0001 s.d BNI-1200), nama asli Indonesia, serta simulasi kehadiran.
                    </p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger mb-4"><?= e($message) ?></div>
                <?php endif; ?>

                <form method="POST" action="seed-1200.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Jumlah Peserta yang Dibuat</label>
                        <input type="number" class="form-control" value="1200" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Simulasi Peserta Sudah Check-in (Hadir)</label>
                        <input type="number" name="auto_checkin" class="form-control" value="850" min="0" max="1200">
                        <div class="form-text text-muted">Hanya peserta berstatus HADIR yang dapat masuk ke putaran Magic Wheel.</div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="reset_first" value="1" id="resetCheck" checked>
                        <label class="form-check-label small fw-bold text-danger" for="resetCheck">
                            Kosongkan data peserta lama sebelum men-generate (Disarankan untuk pengujian baru)
                        </label>
                    </div>

                    <button type="submit" class="btn btn-bni-orange btn-lg w-100 justify-content-center shadow mb-2" onclick="return confirm('Mulai proses generate 1.200 peserta?')">
                        <i class="fa-solid fa-play me-2"></i> Mulai Generate 1.200 Peserta
                    </button>
                    <a href="<?= $baseUrl ?>/admin/participants.php" class="btn btn-outline-secondary w-100 mt-2">
                        Kembali ke Data Peserta
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/admin/admin-footer.php'; ?>
