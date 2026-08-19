<?php

$file = 'D:\Workspace\own\smartpos-pro\database\migrations\2026_08_18_000001_create_users_table.php';
echo file_exists($file) ? 'exists' : 'not found';
if (file_exists($file)) {
    $contents = file($file);
    for ($i = 0; $i < min(15, count($contents)); $i++) {
        echo $i.': '.$contents[$i];
    }
}
