<?php
/**
 * BNI Anniversary Celebration
 * Database Configuration & Connection via PDO
 */

declare(strict_types=1);

// Prevent direct file access if called outside PHP
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Silence deprecation notices from newer PHP versions (PHP 8.4/8.5+) on production
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
if (!empty(getenv('VERCEL')) || !empty(getenv('VERCEL_ENV'))) {
    ini_set('display_errors', '0');
}

// Automatically load Composer dependencies if present
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'bni_anniversary');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection instance
 *
 * @return PDO
 * @throws PDOException
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        // Enable SSL support for Cloud MySQL databases (TiDB, Aiven, PlanetScale, etc.)
        $isRemote = !in_array(DB_HOST, ['127.0.0.1', 'localhost', '']);
        if ($isRemote || getenv('DB_SSL') === 'true') {
            // In PHP 8.5+, constants moved from PDO:: to Pdo\Mysql::
            if (defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
            } elseif (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                @$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            } else {
                $options[1014] = false; // Internal integer fallback for MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
            }
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Set utf8mb4 collation directly on connection (avoids deprecated INIT_COMMAND constant)
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Log real error for admin/developer, never expose raw DB credentials to users
            error_log('Database Connection Error: ' . $e->getMessage());
            throw new PDOException('Koneksi ke database gagal. Pastikan MySQL sudah berjalan.');
        }
    }

    return $pdo;
}
