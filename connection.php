<?php
// Show all errors (important for debugging white screen)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$db   = "campusconnect";

// Create connection
$database = new mysqli($host, $user, $pass, $db);

// Check connection
if ($database->connect_error) {
    die("❌ Database Connection Failed: " . $database->connect_error);
}

// Set charset (important for text handling)
if (!$database->set_charset("utf8mb4")) {
    die("❌ Error loading charset: " . $database->error);
}

// Optional: confirm connection (REMOVE after testing)
// echo "✅ Connected to database successfully!";
?>