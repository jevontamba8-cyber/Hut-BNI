<?php
/**
 * BNI Anniversary Celebration
 * API: Confirm Participant Check-in (Atomic Database Transaction)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$nik = trim((string)($input['nik'] ?? ''));
$token = (string)($input['csrf_token'] ?? '');

// 1. Verify CSRF Token
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Validasi keamanan (CSRF) gagal atau sesi berakhir. Silakan ulangi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validate NIK format
if (!validateNikFormat($nik)) {
    echo json_encode(['success' => false, 'message' => 'Format NIK tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();

    // Atomic update: only updates if current status is strictly 'BELUM HADIR'
    $stmt = $db->prepare("
        UPDATE participants 
        SET status_kehadiran = 'HADIR', waktu_checkin = NOW() 
        WHERE nik = ? AND status_kehadiran = 'BELUM HADIR'
    ");
    $stmt->execute([$nik]);

    if ($stmt->rowCount() === 1) {
        // Fetch updated participant info
        $fetchStmt = $db->prepare('
            SELECT id, nik, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran, waktu_checkin 
            FROM participants 
            WHERE nik = ? 
            LIMIT 1
        ');
        $fetchStmt->execute([$nik]);
        $participant = $fetchStmt->fetch();

        // Store participant in session for check-in-success.php page
        $_SESSION['checked_in_participant'] = [
            'id'            => $participant['id'],
            'nama_lengkap'  => $participant['nama_lengkap'],
            'nomor_undian'  => $participant['nomor_undian'],
            'divisi'        => $participant['divisi'],
            'cabang'        => $participant['cabang'],
            'waktu_checkin' => formatIndonesianDate($participant['waktu_checkin']),
            'status'        => 'HADIR'
        ];

        // Audit log (without logging raw full NIK for privacy compliance)
        logAudit(null, 'PARTICIPANT_CHECKIN', "Peserta {$participant['nama_lengkap']} (No: {$participant['nomor_undian']}) berhasil check-in.");

        echo json_encode([
            'success'      => true,
            'message'      => 'Check-in berhasil dikonfirmasi! Selamat datang di BNI Anniversary Celebration.',
            'redirect_url' => getBaseUrl() . '/check-in-success.php',
            'participant'  => $_SESSION['checked_in_participant']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Row count was 0: check why
    $checkStmt = $db->prepare('SELECT status_kehadiran, waktu_checkin FROM participants WHERE nik = ? LIMIT 1');
    $checkStmt->execute([$nik]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        if ($existing['status_kehadiran'] === 'HADIR') {
            echo json_encode([
                'success' => false,
                'message' => 'Anda sudah melakukan check-in pada ' . formatIndonesianDate($existing['waktu_checkin']) . '.'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kendala saat memperbarui status. Silakan coba kembali.'
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'NIK tidak terdaftar dalam database peserta.'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log('Confirm Checkin Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memproses check-in ke database.'
    ], JSON_UNESCAPED_UNICODE);
}
