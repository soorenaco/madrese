<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Modules\Auth\Middleware\CheckPermission::class,
            'role'       => \App\Modules\Auth\Middleware\CheckRole::class,
        ]);

        // پروژه پشت ArvanCloud (CDN/Reverse Proxy) اجرا می‌شود؛ بدون این تنظیم،
        // $request->ip() همیشه IP سرورهای Arvan را برمی‌گرداند و Rate Limiter
        // مبتنی بر IP (auth-login-ip) برای کل سامانه یک شمارنده مشترک می‌شود.
        //
        // هشدار امنیتی: '*' فقط وقتی امن است که سرور اصلی فقط از طریق Arvan
        // قابل دسترسی باشد (فایروال فقط IPهای Arvan را بپذیرد). در غیر این صورت
        // مهاجم می‌تواند X-Forwarded-For را جعل کند و Rate Limit را دور بزند.
        // TODO: قبل از deploy، '*' را با لیست IPهای رسمی Arvan جایگزین کن.
    
        $middleware->trustProxies(
        at: '*',
        headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );


        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('auth.login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('auth.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();