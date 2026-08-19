<?php

$dir = 'D:\Workspace\own\smartpos-pro\database\migrations';
echo 'Directory exists: '.(is_dir($dir) ? 'yes' : 'no')."\n";
$files = scandir($dir);
echo "Files:\n";
foreach ($files as $f) {
    if ($f !== '.' && $f !== '..') {
        echo "  $f\n";
    }
}
