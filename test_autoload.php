<?php

use Composer\Autoload\Autoload;

require 'vendor/autoload.php';
$autoloader = Autoload::getAutoload();
$prefixes = $autoloader->getPrefixesPsr4('Illuminate\Database\\');
echo 'Database Mapping: '.($prefixes[0] ?? 'not found')."\n";
foreach ($prefixes as $p) {
    echo "  Prefix: $p\n";
}
