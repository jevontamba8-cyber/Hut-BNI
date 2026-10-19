<?php
/**
 * BNI Anniversary Celebration
 * Global Helper Functions & Business Logic
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Dynamically detect application base URL
 */
function getBaseUrl(): string
{
    if (defined('BASE_URL')) {
        return BASE_URL;
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine subdirectory from SCRIPT_NAME
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Normalize path separators
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Remove /admin or /api if we are inside subfolders
    $basePath = preg_replace('#/(admin|api)(/.*)?$#', '', $scriptDir);
    $basePath = rtrim($basePath, '/');

    return $protocol . $host . $basePath;
}

/**
 * Sanitize output for HTML display
 */
function e(?string $string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Mask NIK to protect privacy on public screens (shows first 4 and last 4 digits)
 * Example: 3171012345670001 -> 3171********0001
 */
function maskNik(string $nik): string
{
    $clean = trim($nik);
    $len = strlen($clean);
    if ($len < 8) {
        return str_repeat('*', $len);
    }
    return substr($clean, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($clean, -4);
}

/**
 * Strict 16-digit numeric NIK validation
 */
function validateNikFormat(string $nik): bool
{
    $clean = trim($nik);
    return (bool)preg_match('/^[0-9]{16}$/', $clean);
}

/**
 * Load all event settings from database
 */
function getSettings(): array
{
    static $settingsCache = null;
    if ($settingsCache !== null) {
        return $settingsCache;
    }

    try {
        $db = getDB();
        $stmt = $db->query('SELECT setting_key, setting_value FROM event_settings');
        $rows = $stmt->fetchAll();
        $settingsCache = [];
        foreach ($rows as $row) {
            $settingsCache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log('Error loading settings: ' . $e->getMessage());
        $settingsCache = [];
    }

    return $settingsCache;
}

/**
 * Get single setting value with fallback
 */
function getSetting(string $key, string $default = ''): string
{
    $settings = getSettings();
    return $settings[$key] ?? $default;
}

/**
 * Update single setting value in database
 */
function updateSetting(string $key, string $value): bool
{
    try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO event_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log("Failed to update setting $key: " . $e->getMessage());
        return false;
    }
}

/**
 * Format datetime to Indonesian standard string
 */
function formatIndonesianDate(?string $datetime, bool $withTime = true): string
{
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return '-';
    }

    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $d = date('j', $timestamp);
    $m = $months[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);

    if ($withTime) {
        $time = date('H:i:s', $timestamp);
        return "$d $m $y, $time WIB";
    }

    return "$d $m $y";
}

/**
 * Fetch real-time event statistics from MySQL
 */
function getEventStats(): array
{
    try {
        $db = getDB();

        // Participants stats
        $pStmt = $db->query("
            SELECT 
                COUNT(*) AS total_participants,
                SUM(CASE WHEN status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) AS hadir,
                SUM(CASE WHEN status_kehadiran = 'BELUM HADIR' THEN 1 ELSE 0 END) AS belum_hadir
            FROM participants
        ");
        $pStats = $pStmt->fetch() ?: ['total_participants' => 0, 'hadir' => 0, 'belum_hadir' => 0];

        $totalP = (int)$pStats['total_participants'];
        $hadir = (int)$pStats['hadir'];
        $belumHadir = (int)$pStats['belum_hadir'];
        $kehadiranPersen = $totalP > 0 ? round(($hadir / $totalP) * 100, 1) : 0;

        // Prizes stats
        $prStmt = $db->query("
            SELECT 
                COUNT(*) AS total_prizes,
                COALESCE(SUM(jumlah_pemenang), 0) AS total_quota
            FROM prizes 
            WHERE status_hadiah = 'AKTIF'
        ");
        $prStats = $prStmt->fetch() ?: ['total_prizes' => 0, 'total_quota' => 0];

        // Winners stats (sah only)
        $wStmt = $db->query("
            SELECT COUNT(DISTINCT participant_id) AS total_winners, COUNT(*) AS total_draws
            FROM draw_results 
            WHERE status_hasil = 'SAH'
        ");
        $wStats = $wStmt->fetch() ?: ['total_winners' => 0, 'total_draws' => 0];

        $totalQuota = (int)$prStats['total_quota'];
        $totalWinners = (int)$wStats['total_winners'];
        $remainingQuota = max(0, $totalQuota - $totalWinners);

        return [
            'total_participants' => $totalP,
            'hadir'              => $hadir,
            'belum_hadir'        => $belumHadir,
            'kehadiran_persen'   => $kehadiranPersen,
            'total_prizes'       => (int)$prStats['total_prizes'],
            'total_prize_quota'  => $totalQuota,
            'total_winners'      => $totalWinners,
            'remaining_prizes'   => $remainingQuota
        ];
    } catch (Exception $e) {
        error_log('getEventStats Error: ' . $e->getMessage());
        return [
            'total_participants' => 0,
            'hadir'              => 0,
            'belum_hadir'        => 0,
            'kehadiran_persen'   => 0,
            'total_prizes'       => 0,
            'total_prize_quota'  => 0,
            'total_winners'      => 0,
            'remaining_prizes'   => 0
        ];
    }
}

/**
 * Flash notification messaging
 */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}
