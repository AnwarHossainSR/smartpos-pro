<?php

require 'vendor/autoload.php';
$r = new ReflectionClass('Illuminate\Database\Connection');
echo 'Connection found: '.($r->getFileName() ? $r->getFileName() : 'no file');
echo "\n";
