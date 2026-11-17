<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/inertia-ping', fn () => Inertia::render('Ping', ['status' => 'ok']));