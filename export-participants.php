<?php
/**
 * BNI Anniversary Celebration
 * Export Participants to CSV / Excel
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin(['superadmin', 'admin', 'panitia']);

$db = getDB();

$search = trim($_GET['search'] ?? '');
$filterDivisi = trim($_GET['divisi'] ?? '');
$filterCabang = trim($_GET['cabang'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$whereClause = ' WHERE 1=1 ';
$params = [];

if (!empty($search)) {
    $whereClause .= ' AND (nama_lengkap LIKE ? OR nik LIKE ? OR nomor_undian LIKE ?) ';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filterDivisi)) {
    $whereClause .= ' AND divisi = ? ';
    $params[] = $filterDivisi;
}
if (!empty($filterCabang)) {
    $whereClause .= ' AND cabang = ? ';
    $params[] = $filterCabang;
}
if (!empty($filterStatus)) {
    $whereClause .= ' AND status_kehadiran = ? ';
    $params[] = $filterStatus;
}

$stmt = $db->prepare("SELECT id, nik, nomor_undian, nama_lengkap, divisi, cabang, status_kehadiran, waktu_checkin, created_at FROM participants $whereClause ORDER BY id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'Data_Peserta_BNI_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($out, ['ID', 'NIK', 'Nomor Undian', 'Nama Lengkap', 'Divisi', 'Cabang', 'Status Kehadiran', 'Waktu Check-in', 'Tanggal Dibuat']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        // Prefix with single quote so Excel displays 16-digit string without scientific notation
        "'" . $r['nik'],
        $r['nomor_undian'],
        $r['nama_lengkap'],
        $r['divisi'],
        $r['cabang'],
        $r['status_kehadiran'],
        $r['waktu_checkin'] ?: '-',
        $r['created_at']
    ]);
}

fclose($out);
exit;
