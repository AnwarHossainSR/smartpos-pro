<?php

// Check if Migration class is in the autoload map
$prefixes = include 'vendor/composer/autoload_namespaces.php';
echo "Illuminate\\Database\\Mapping check:\n";
echo isset($prefixes['Illuminate\Database\\']) ? $prefixes['Illuminate\Database\\'][0] : 'not found';
echo "\n";
echo "Illuminate\\Database\\Mappings check:\n";
echo isset($prefixes['Illuminate\Database\Migrations\\']) ? $prefixes['Illuminate\Database\Migrations\\'][0] : 'not found';
echo "\n";
