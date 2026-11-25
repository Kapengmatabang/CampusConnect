<?php
require 'db.php'; // <-- change to your connection file

$dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];

$cols = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = '$dbName'
      AND DATA_TYPE IN ('varchar','text','tinytext','mediumtext','longtext')
");

echo "<pre>";
while ($c = $cols->fetch_assoc()) {
    $t = $c['TABLE_NAME'];
    $col = $c['COLUMN_NAME'];

    $rows = $conn->query("SELECT `$col` AS v FROM `$t`
        WHERE `$col` REGEXP '\\\\.(jpg|jpeg|png|gif|webp)$'");
    if (!$rows) continue;

    while ($r = $rows->fetch_assoc()) {
        $raw  = $r['v'];
        $norm = str_replace('\\', '/', $raw);
        $full = __DIR__ . '/' . ltrim($norm, '/');
        $status = file_exists($full) ? "OK     " : "MISSING";
        echo "$status | $t.$col | $raw\n";
    }
}
echo "\nDone</pre>";