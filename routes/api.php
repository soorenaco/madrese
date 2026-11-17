<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// لود کردن روت‌های ماژول احراز هویت
require base_path('app/Modules/Auth/Routes/api.php');