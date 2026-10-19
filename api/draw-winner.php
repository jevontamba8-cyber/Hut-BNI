<?php
/**
 * BNI Anniversary Celebration
 * API: Execute Lottery Draw (Backend-Determined Randomness & ACID Transaction)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Authorization Check
if (!isLoggedIn() || !hasRole(['superadmin', 'admin', 'operator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengundi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$prizeId = (int)($input['prize_id'] ?? 0);
$token = (string)($input['csrf_token'] ?? '');

// 2. CSRF Token Verification
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi kedaluwarsa atau token keamanan tidak valid. Refresh halaman.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($prizeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Silakan pilih hadiah yang akan diundi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDB();

try {
    // 3. Begin ACID Database Transaction
    $db->beginTransaction();

    // Check Prize Existence and Active Status with Lock
    $prizeStmt = $db->prepare('SELECT id, nama_hadiah, deskripsi, jumlah_pemenang, status_hadiah FROM prizes WHERE id = ? FOR UPDATE');
    $prizeStmt->execute([$prizeId]);
    $prize = $prizeStmt->fetch();

    if (!$prize) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Data hadiah tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($prize['status_hadiah'] !== 'AKTIF') {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Hadiah ini sudah selesai diundi atau sedang nonaktif.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Count existing valid winners for this specific prize
    $wCountStmt = $db->prepare("SELECT COUNT(*) AS total FROM draw_results WHERE prize_id = ? AND status_hasil = 'SAH'");
    $wCountStmt->execute([$prizeId]);
    $currentWinnersCount = (int)$wCountStmt->fetch()['total'];

    if ($currentWinnersCount >= (int)$prize['jumlah_pemenang']) {
        // Auto mark as SELESAI
        $upStmt = $db->prepare("UPDATE prizes SET status_hadiah = 'SELESAI' WHERE id = ?");
        $upStmt->execute([$prizeId]);
        $db->commit();

        echo json_encode([
            'success' => false,
            'message' => "Kuota pemenang untuk hadiah '{$prize['nama_hadiah']}' sudah terpenuhi ({$prize['jumlah_pemenang']} pemenang)."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Check Win Rule setting
    $winRule = getSetting('win_rule', 'one_win_only');

    // 4. Fetch Eligible Candidates (MUST be Checked-in, plus win rule constraint)
    $candidateSql = "
        SELECT id, nomor_undian, nama_lengkap, divisi, cabang 
        FROM participants 
        WHERE status_kehadiran = 'HADIR'
    ";

    if ($winRule === 'one_win_only') {
        $candidateSql .= " AND id NOT IN (SELECT participant_id FROM draw_results WHERE status_hasil = 'SAH') ";
    } else {
        // If multiple allowed, still disallow winning the exact same prize twice
        $candidateSql .= " AND id NOT IN (SELECT participant_id FROM draw_results WHERE prize_id = {$prizeId} AND status_hasil = 'SAH') ";
    }

    $candidateStmt = $db->query($candidateSql);
    $candidates = $candidateStmt->fetchAll();

    $eligibleCount = count($candidates);
    if ($eligibleCount === 0) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada peserta yang memenuhi syarat undian saat ini (Pastikan peserta sudah check-in).'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5. Select Winner using Cryptographically Secure Random integer
    $winnerIndex = random_int(0, $eligibleCount - 1);
    $winner = $candidates[$winnerIndex];

    // Generate unique draw session ID
    $drawSessionId = 'DRAW_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

    // 6. Insert Draw Result permanently into Database
    $insertStmt = $db->prepare('
        INSERT INTO draw_results (participant_id, prize_id, draw_session_id, waktu_undian, status_hasil)
        VALUES (?, ?, ?, NOW(), "SAH")
    ');
    $insertStmt->execute([
        $winner['id'],
        $prize['id'],
        $drawSessionId
    ]);
    $drawId = (int)$db->lastInsertId();

    // Check if prize quota is now fulfilled
    $newWinnerCount = $currentWinnersCount + 1;
    if ($newWinnerCount >= (int)$prize['jumlah_pemenang']) {
        $finishStmt = $db->prepare("UPDATE prizes SET status_hadiah = 'SELESAI' WHERE id = ?");
        $finishStmt->execute([$prize['id']]);
    }

    // Write Audit Log
    $currentUser = getCurrentUser();
    $adminId = $currentUser ? (int)$currentUser['id'] : null;
    $adminName = $currentUser ? $currentUser['nama_admin'] : 'System';
    logAudit(
        $adminId,
        'DRAW_WINNER_SUCCESS',
        "Pemenang: {$winner['nama_lengkap']} (No: {$winner['nomor_undian']}) untuk hadiah {$prize['nama_hadiah']} (Undian ke-{$newWinnerCount}/{$prize['jumlah_pemenang']}) oleh $adminName"
    );

    // 7. Commit Transaction
    $db->commit();

    // 8. Return Winner Payload for Frontend Wheel Alignment
    echo json_encode([
        'success'      => true,
        'message'      => 'Pengundian berhasil dilakukan!',
        'draw_id'      => $drawId,
        'session_id'   => $drawSessionId,
        'winner'       => [
            'id'           => (int)$winner['id'],
            'nama_lengkap' => $winner['nama_lengkap'],
            'nomor_undian' => $winner['nomor_undian'],
            'divisi'       => $winner['divisi'],
            'cabang'       => $winner['cabang']
        ],
        'prize'        => [
            'id'              => (int)$prize['id'],
            'nama_hadiah'     => $prize['nama_hadiah'],
            'winner_sequence' => $newWinnerCount,
            'total_quota'     => (int)$prize['jumlah_pemenang'],
            'is_finished'     => $newWinnerCount >= (int)$prize['jumlah_pemenang']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Draw Winner Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem saat memproses pengundian.'
    ], JSON_UNESCAPED_UNICODE);
}
