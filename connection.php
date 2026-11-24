<?php
// Show errors only when running locally
$is_local = !getenv('DB_HOST');
error_reporting(E_ALL);
ini_set('display_errors', $is_local ? 1 : 0);

// Database settings from environment variables (Render / TiDB),
// with local XAMPP defaults as fallback.
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'campusconnect';
$port = (int)(getenv('DB_PORT') ?: 3306);

// TiDB Cloud requires SSL; local XAMPP does not.
$use_ssl = !in_array($host, ['localhost', '127.0.0.1'], true);

mysqli_report(MYSQLI_REPORT_OFF);
$database = mysqli_init();

if ($use_ssl) {
    mysqli_ssl_set($database, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);
    $flags = MYSQLI_CLIENT_SSL;
} else {
    $flags = 0;
}

if (!@mysqli_real_connect($database, $host, $user, $pass, $db, $port, NULL, $flags)) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Set charset (important for text handling)
if (!$database->set_charset("utf8mb4")) {
    die("Error loading charset: " . $database->error);
}
?>