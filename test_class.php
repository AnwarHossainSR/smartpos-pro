<?php

use Illuminate\Database\Migration;

require 'vendor/autoload.php';
spl_autoload_register(function ($class) {
    echo "Attempting to load: $class\n";
});
$migration = new Migration;
echo "Migration loaded successfully\n";
