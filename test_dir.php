<?php

$dir = 'D:\Workspace\own\smartpos-pro\vendor\laravel\framework\src\Illuminate\Database';
echo 'Directory exists: '.(is_dir($dir) ? 'yes' : 'no')."\n";
if (is_dir($dir)) {
    echo 'Contents: '.implode(', ', scandir($dir))."\n";
}
