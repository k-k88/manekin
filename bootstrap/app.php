<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        // 🔹 CSRF除外
        $middleware->validateCsrfTokens(except: [
            '/line/webhook',
        ]);

        // 🔹 Webグループに追加（クロージャは prependMiddleware で渡す）
      //  $middleware->web()->prependMiddleware(function (Request $request, $next) {
         //   $user = auth()->user();
           // if ($user && $user->role !== 'admin') {
             //   session(['company_id' => $user->company_id]);
            //}
           // return $next($request);
        //});
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
