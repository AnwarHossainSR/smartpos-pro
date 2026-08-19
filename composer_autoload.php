#!/usr/bin/env php
<?php

// Simple script to run composer dump-autoload
chdir('D:\Workspace\own\smartpos-pro');
exec('php composer.phar dump-autoload', $output, $return_code);
print_r($output);
echo "Return code: $return_code\n";
