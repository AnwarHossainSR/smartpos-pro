<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/auth.php';
    require __DIR__.'/portal.php';
    require __DIR__.'/super-admin.php';
    require __DIR__.'/app.php';
});
