<?php
/**
 * BNI Anniversary Celebration
 * API: Get Draw Results & Recent Winners
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$prizeId = isset($_GET['prize_id']) ? (int)$_GET['prize_id'] : 0;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;

try {
    $db = getDB();

    $sql = "
        SELECT 
            dr.id AS draw_id,
            dr.draw_session_id,
            dr.waktu_undian,
            dr.status_hasil,
            p.id AS participant_id,
            p.nomor_undian,
            p.nama_lengkap,
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
    if ($prizeId > 0) {
        $sql .= " AND dr.prize_id = ? ";
        $params[] = $prizeId;
    }

    $sql .= " ORDER BY dr.id DESC LIMIT " . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    $formatted = array_map(function($row) {
        return [
            'draw_id'         => (int)$row['draw_id'],
            'session_id'      => $row['draw_session_id'],
            'waktu_undian'    => formatIndonesianDate($row['waktu_undian']),
            'nama_lengkap'    => $row['nama_lengkap'],
            'nomor_undian'    => $row['nomor_undian'],
            'divisi'          => $row['divisi'],
            'cabang'          => $row['cabang'],
            'prize_id'        => (int)$row['prize_id'],
            'nama_hadiah'     => $row['nama_hadiah']
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'count'   => count($formatted),
        'winners' => $formatted
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Get Draw Results Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data pemenang.'], JSON_UNESCAPED_UNICODE);
}
