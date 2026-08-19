<?php

require 'vendor/autoload.php';
// Check the prefixes for Illuminate\Database
$prefixes = include 'vendor/composer/autoload_prefixes.php';
echo "Database prefixes:\n";
print_r($prefixes);
echo "\nDatabase Migrations prefixes:\n";
print_r($prefixes['Illuminate\Database\\Migrations'] ?? 'not found');
