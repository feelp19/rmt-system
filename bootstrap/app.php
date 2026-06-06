<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Caddy: honor X-Forwarded-* so scheme/HTTPS/IP and secure cookies are correct
        $middleware->trustProxies(at: '*');

        // Sanctum SPA (cookie/session) statefulness for first-party requests
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Regras de negócio violadas (saldo insuficiente, anúncio indisponível,
        // etc.) viram 422 com mensagem limpa — sem stack trace para o cliente.
        $exceptions->render(function (\DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        // Falha de integração externa (PushinPay) → 503, mensagem genérica.
        $exceptions->render(function (\App\Exceptions\PushinPayException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 503);
            }
        });
    })->create();
