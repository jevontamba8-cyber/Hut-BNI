<?php
/**
 * BNI Anniversary Celebration
 * Export Draw Results & Winners
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$prizeId = isset($_GET['prize_id']) ? (int)$_GET['prize_id'] : 0;
$db = getDB();

$sql = "
    SELECT 
        dr.id,
        dr.draw_session_id,
        dr.waktu_undian,
        pr.nama_hadiah,
        p.nama_lengkap,
        p.nomor_undian,
        p.divisi,
        p.cabang
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
$sql .= " ORDER BY dr.id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'Pemenang_Undian_BNI_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['No', 'Session ID', 'Nama Hadiah', 'Nama Lengkap Pemenang', 'Nomor Undian', 'Divisi', 'Cabang', 'Waktu Pengundian']);

foreach ($rows as $idx => $r) {
    fputcsv($out, [
        $idx + 1,
        $r['draw_session_id'],
        $r['nama_hadiah'],
        $r['nama_lengkap'],
        $r['nomor_undian'],
        $r['divisi'],
        $r['cabang'],
        formatIndonesianDate($r['waktu_undian'])
    ]);
}

fclose($out);
exit;
