<?php
/**
 * BNI Anniversary Celebration
 * API: Get Eligible Participants for Magic Wheel
 * Only returns checked-in participants who have not yet won (based on win_rule).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Access restricted to authorized operators and admins
if (!isLoggedIn() || !hasRole(['superadmin', 'admin', 'operator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login sebagai operator/admin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();
    $winRule = getSetting('win_rule', 'one_win_only');

    $sql = "
        SELECT id, nomor_undian, nama_lengkap, divisi, cabang 
        FROM participants 
        WHERE status_kehadiran = 'HADIR'
    ";

    if ($winRule === 'one_win_only') {
        $sql .= " AND id NOT IN (SELECT participant_id FROM draw_results WHERE status_hasil = 'SAH') ";
    }

    $sql .= " ORDER BY nama_lengkap ASC";

    $stmt = $db->query($sql);
    $participants = $stmt->fetchAll();

    echo json_encode([
        'success'      => true,
        'win_rule'     => $winRule,
        'count'        => count($participants),
        'participants' => $participants
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Get Eligible Participants Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil data peserta undian dari database.'
    ], JSON_UNESCAPED_UNICODE);
}
