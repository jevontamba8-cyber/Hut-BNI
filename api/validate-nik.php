<?php
/**
 * BNI Anniversary Celebration
 * API: Validate NIK for Participant Check-in
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$nik = trim((string)($input['nik'] ?? ''));

// 1. Rate limiting per IP to prevent NIK brute forcing
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateKey = 'nik_check_' . md5($clientIp);
if (!checkRateLimit($rateKey, 20, 300)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Terlalu banyak permintaan validasi NIK. Mohon tunggu beberapa saat.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validate NIK 16 digit format
if (!validateNikFormat($nik)) {
    hitRateLimit($rateKey);
    echo json_encode([
        'success' => false,
        'message' => 'Format NIK tidak valid. NIK harus terdiri dari 16 digit angka.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT id, nik, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran, waktu_checkin 
        FROM participants 
        WHERE nik = ? 
        LIMIT 1
    ');
    $stmt->execute([$nik]);
    $participant = $stmt->fetch();

    if (!$participant) {
        hitRateLimit($rateKey);
        echo json_encode([
            'success' => false,
            'status'  => 'NOT_FOUND',
            'message' => 'NIK tidak terdaftar. Silakan hubungi panitia registrasi BNI.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($participant['status_kehadiran'] === 'HADIR') {
        echo json_encode([
            'success'     => false,
            'status'      => 'ALREADY_CHECKED_IN',
            'message'     => 'Anda sudah melakukan check-in sebelumnya.',
            'participant' => [
                'nama_lengkap'     => $participant['nama_lengkap'],
                'nomor_undian'     => $participant['nomor_undian'],
                'divisi'           => $participant['divisi'],
                'cabang'           => $participant['cabang'],
                'waktu_checkin'    => formatIndonesianDate($participant['waktu_checkin']),
                'masked_nik'       => maskNik($participant['nik'])
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Participant valid & ready to confirm
    echo json_encode([
        'success'     => true,
        'status'      => 'READY_TO_CONFIRM',
        'message'     => 'Data peserta ditemukan. Silakan konfirmasi kehadiran Anda.',
        'csrf_token'  => generateCsrfToken(),
        'participant' => [
            'id'           => (int)$participant['id'],
            'nama_lengkap' => $participant['nama_lengkap'],
            'nomor_undian' => $participant['nomor_undian'],
            'divisi'       => $participant['divisi'],
            'cabang'       => $participant['cabang'],
            'masked_nik'   => maskNik($participant['nik'])
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Validate NIK Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kendala pada sistem. Silakan coba kembali.'
    ], JSON_UNESCAPED_UNICODE);
}
