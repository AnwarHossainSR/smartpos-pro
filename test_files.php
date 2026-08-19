<?php

$files = [
    'D:\Workspace\own\smartpos-pro\tests\Feature\Api\CheckoutE2ETest.php',
    'D:\Workspace\own\smartpos-pro\tests\Feature\Api\ConcurrencyTest.php',
    'D:\Workspace\own\smartpos-pro\tests\Feature\Api\PermissionMatrixTest.php',
    'D:\Workspace\own\smartpos-pro\tests\Feature\Api\PlanLimitTest.php',
];

foreach ($files as $file) {
    echo basename($file).': '.(file_exists($file) ? 'exists' : 'NOT found')."\n";
}
