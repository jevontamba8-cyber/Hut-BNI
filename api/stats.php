<?php
/**
 * BNI Anniversary Celebration
 * API: Real-time Event Statistics
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$stats = getEventStats();
echo json_encode(['success' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
