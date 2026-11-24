<?php
// Reads settings from environment variables (Render / TiDB).
// Falls back to local XAMPP defaults when they are not set.
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = getenv('DB_NAME') ?: 'campusconnect';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

// TiDB Cloud requires an encrypted connection; local XAMPP does not.
$use_ssl = !in_array($db_host, ['localhost', '127.0.0.1'], true);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();

if ($use_ssl) {
    // Debian/Ubuntu system CA bundle (present in the php:apache Docker image)
    mysqli_ssl_set($conn, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);
    $flags = MYSQLI_CLIENT_SSL;
} else {
    $flags = 0;
}

if (!@mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port, NULL, $flags)) {
    die("Connection failed: " . mysqli_connect_error());
}

$conn->set_charset("utf8mb4");
?>