<?php

$file = 'D:\Workspace\own\smartpos-pro\database\migrations\2026_08_18_000001_create_users_table.php';
$contents = file($file);
echo "File: $file\n";
for ($i = 0; $i < min(20, count($contents)); $i++) {
    echo $i.': '.$contents[$i];
}
