<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'Judger AI API',
        'version' => '1.0.0',
    ]);
});
