<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'SmartPOS Pro API',
        'status' => 'ok',
        'api' => url('/api/v1'),
    ]);
});
