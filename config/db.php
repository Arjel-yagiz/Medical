<?php
/**
 * Database configuration (XAMPP defaults).
 * 1. Start Apache + MySQL in the XAMPP Control Panel
 * 2. Import database.sql in phpMyAdmin
 * 3. Change the values below only if your setup is different
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'medical_appointment_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is an empty password

date_default_timezone_set('Asia/Manila');

// Auto-detect the URL folder of the project (e.g. /medical_appointment_system)
$docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$proj    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
define('BASE_URL', ($docRoot !== '' && strpos($proj, $docRoot) === 0)
    ? substr($proj, strlen($docRoot))
    : '/medical_appointment_system');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
        ]
    );
} catch (PDOException $e) {
    die('<h3>Database connection failed.</h3><p>Make sure MySQL is running, you imported <code>database.sql</code> in phpMyAdmin, and the settings in <code>config/db.php</code> are correct.</p>');
}
