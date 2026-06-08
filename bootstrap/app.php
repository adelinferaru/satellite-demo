<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cross-service flow tracing: replaces the package's auto-http root span
        // (FLOW_AUTO_HTTP) with one that links to the inbound caller's span.
        $middleware->prependToGroup('api', \App\Http\Middleware\ContinueFlowTrace::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
