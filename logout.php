<?php
/**
 * BNI Anniversary Celebration
 * Admin Logout Process
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logoutUser();
header('Location: ' . getBaseUrl() . '/index.php');
exit;
